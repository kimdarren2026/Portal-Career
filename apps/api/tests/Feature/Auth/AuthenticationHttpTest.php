<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Domains\Identity\Actions\IssueEmailVerificationToken;
use App\Domains\Identity\Actions\IssuePasswordResetToken;
use App\Domains\Identity\Enums\RoleCode;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Models\EmailVerificationToken;
use App\Domains\Identity\Models\PasswordResetToken;
use App\Domains\Identity\Support\TokenHasher;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Tests\Feature\Identity\IdentityTestCase;

final class AuthenticationHttpTest extends IdentityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Redis::connection('cache')->flushdb();
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.'.random_int(1, 250)]);
    }

    public function test_candidate_registration_creates_only_identity_minimum_and_secure_outbox(): void
    {
        $response = $this->postJson('/auth/register/candidate', $this->candidatePayload('Candidate@Example.test'));

        $response->assertAccepted()->assertJsonPath('data.status', 'VERIFICATION_EMAIL_QUEUED');
        $user = DB::table('users')->where('email_normalized', 'candidate@example.test')->first();
        $this->assertSame('PENDING_EMAIL_VERIFICATION', $user->status);
        $this->assertDatabaseHas('candidate_profiles', ['user_id' => $user->id, 'current_candidate_type' => 'EXTERNAL']);
        $this->assertDatabaseHas('user_roles', ['user_id' => $user->id]);
        $this->assertDatabaseHas('password_credentials', ['user_id' => $user->id]);
        $this->assertDatabaseHas('email_verification_tokens', ['user_id' => $user->id]);
        $this->assertSame(0, DB::table('companies')->count());
        $this->assertSame(0, DB::table('company_members')->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'registration', 'object_id' => $user->id]);
        $this->assertStringNotContainsString((string) DB::table('email_verification_tokens')->where('user_id', $user->id)->value('token_hash'), (string) DB::table('email_outbox')->value('payload_reference'));
    }

    public function test_recruiter_registration_does_not_create_company_or_membership(): void
    {
        $this->postJson('/auth/register/recruiter', $this->recruiterPayload('recruiter@example.test'))->assertAccepted();
        $userId = DB::table('users')->where('email_normalized', 'recruiter@example.test')->value('id');

        $this->assertDatabaseHas('user_roles', ['user_id' => $userId, 'role_id' => DB::table('roles')->where('code', 'COMPANY_RECRUITER')->value('id')]);
        $this->assertSame(0, DB::table('companies')->count());
        $this->assertSame(0, DB::table('company_members')->count());
    }

    public function test_registration_duplicate_is_case_insensitive_and_outwardly_identical(): void
    {
        $first = $this->postJson('/auth/register/candidate', $this->candidatePayload('MiXeD@example.test'));
        $second = $this->postJson('/auth/register/recruiter', $this->recruiterPayload('mixed@EXAMPLE.test'));

        $first->assertStatus(202)->assertJsonPath('data.status', $second->json('data.status'));
        $this->assertSame($first->json('meta.warnings'), $second->json('meta.warnings'));
        $this->assertSame(1, DB::table('users')->count());
        $this->assertDatabaseHas('email_outbox', ['template_reference' => 'identity.account-already-registered']);
    }

    public function test_password_policy_is_enforced_by_registration_and_reset(): void
    {
        $this->postJson('/auth/register/candidate', array_replace($this->candidatePayload('policy@example.test'), ['password' => 'lowercase1', 'password_confirmation' => 'lowercase1']))
            ->assertStatus(422)->assertJsonPath('error.code', 'AUTH_PASSWORD_POLICY');
    }

    public function test_verification_get_does_not_consume_and_post_activates(): void
    {
        $user = $this->makeUser('pending@example.test', UserStatus::PendingEmailVerification, 'CorrectPass1', null);
        $token = app(IssueEmailVerificationToken::class)->execute($user, queueEmail: false);

        $this->get('/verify-email/'.$token)->assertOk();
        $this->assertNull(EmailVerificationToken::query()->where('user_id', $user->id)->value('used_at'));
        $this->postJson('/auth/verify-email', ['token' => $token])->assertOk()
            ->assertJsonPath('data.email_verified', true)->assertJsonPath('data.status', 'ACTIVE');
        $this->assertNotNull(EmailVerificationToken::query()->where('user_id', $user->id)->value('used_at'));
        $this->postJson('/auth/verify-email', ['token' => $token])->assertStatus(422)->assertJsonPath('error.code', 'AUTH_TOKEN_ALREADY_USED');
    }

    public function test_login_normalizes_identity_regenerates_session_and_handles_account_status(): void
    {
        $user = $this->makeUser('Mixed.Login@example.test', UserStatus::Active, 'CorrectPass1');
        $this->assignRole($user, RoleCode::CandidateExternal);
        $this->withSession(['before_login' => true]);

        $this->postJson('/auth/login', ['email' => 'MIXED.LOGIN@example.test', 'password' => 'CorrectPass1'])
            ->assertOk()->assertJsonPath('data.user.id', $user->id);
        $this->assertAuthenticatedAs($user);

        $suspended = $this->makeUser('suspended@example.test', UserStatus::Suspended, 'CorrectPass1');
        $this->postJson('/auth/login', ['email' => $suspended->email, 'password' => 'CorrectPass1'])
            ->assertForbidden()->assertJsonPath('error.code', 'AUTH_ACCOUNT_SUSPENDED');
    }

    public function test_invalid_logins_lock_identity_after_eight_failures(): void
    {
        $user = $this->makeUser('locked@example.test', UserStatus::Active, 'CorrectPass1');
        for ($attempt = 1; $attempt < 8; $attempt++) {
            $this->postJson('/auth/login', ['email' => $user->email, 'password' => 'WrongPass1'])->assertStatus(422);
        }
        $this->postJson('/auth/login', ['email' => $user->email, 'password' => 'WrongPass1'])
            ->assertStatus(429)->assertJsonPath('error.code', 'AUTH_ACCOUNT_LOCKED')->assertHeader('Retry-After');
    }

    public function test_successful_login_clears_credential_failure_counter(): void
    {
        $user = $this->makeUser('clear@example.test', UserStatus::Active, 'CorrectPass1');
        for ($attempt = 1; $attempt <= 2; $attempt++) {
            $this->postJson('/auth/login', ['email' => $user->email, 'password' => 'WrongPass1'])->assertStatus(422);
        }
        $this->postJson('/auth/login', ['email' => $user->email, 'password' => 'CorrectPass1'])->assertOk();
        for ($attempt = 1; $attempt <= 6; $attempt++) {
            $this->postJson('/auth/login', ['email' => $user->email, 'password' => 'WrongPass1'])->assertStatus(422);
        }
    }

    public function test_forgot_password_has_identical_public_response_for_known_and_unknown_identity(): void
    {
        $user = $this->makeUser('known@example.test', UserStatus::Active, 'CorrectPass1');
        $known = $this->postJson('/auth/forgot-password', ['email' => $user->email]);
        $unknown = $this->postJson('/auth/forgot-password', ['email' => 'unknown@example.test']);

        $known->assertAccepted()->assertExactJson($unknown->json());
        $this->assertDatabaseHas('password_reset_tokens', ['user_id' => $user->id]);
    }

    public function test_reset_get_does_not_consume_token_and_post_replaces_credential(): void
    {
        $user = $this->makeUser('reset@example.test', UserStatus::Active, 'CorrectPass1');
        $token = app(IssuePasswordResetToken::class)->execute($user->email, queueEmail: false);
        $this->get('/reset-password/'.$token)->assertOk();
        $this->assertNull(PasswordResetToken::query()->where('user_id', $user->id)->value('used_at'));

        $this->postJson('/auth/reset-password', ['token' => $token, 'password' => 'ReplacementPass1', 'password_confirmation' => 'ReplacementPass1'])
            ->assertOk()->assertJsonPath('data.password_reset', true);
        $this->assertTrue(password_verify('ReplacementPass1', (string) DB::table('password_credentials')->where('user_id', $user->id)->value('password_hash')));
        $this->assertDatabaseHas('audit_logs', ['action' => 'password_reset_completed', 'object_id' => $user->id]);
    }

    public function test_me_returns_only_safe_self_context_and_status_is_rechecked_per_request(): void
    {
        $user = $this->makeUser('me@example.test', UserStatus::Active, 'CorrectPass1');
        $this->assignRole($user, RoleCode::CandidateExternal);
        $this->actingAs($user)->getJson('/me')->assertOk()
            ->assertJsonPath('data.user.email', $user->email)
            ->assertJsonMissingPath('data.password_hash')
            ->assertJsonPath('data.roles.0', 'CANDIDATE_EXTERNAL');

        DB::table('users')->where('id', $user->id)->update(['status' => 'SUSPENDED']);
        $this->actingAs($user)->getJson('/me')->assertForbidden()->assertJsonPath('error.code', 'AUTH_ACCOUNT_SUSPENDED');
    }

    public function test_pending_session_is_allowed_but_disabled_session_is_denied_on_next_request(): void
    {
        $pending = $this->makeUser('pending-session@example.test', UserStatus::PendingEmailVerification, 'CorrectPass1', null);
        $this->actingAs($pending)->getJson('/me')->assertOk()->assertJsonPath('data.user.status', 'PENDING_EMAIL_VERIFICATION');

        $disabled = $this->makeUser('disabled-session@example.test', UserStatus::Disabled, 'CorrectPass1');
        $this->actingAs($disabled)->getJson('/me')->assertForbidden()->assertJsonPath('error.code', 'AUTH_ACCOUNT_DISABLED');
    }

    public function test_change_password_requires_current_credential_and_keeps_current_session(): void
    {
        $user = $this->makeUser('change@example.test', UserStatus::Active, 'CorrectPass1');
        $this->actingAs($user)->putJson('/me/password', ['current_password' => 'WrongPass1', 'password' => 'NewPassword1', 'password_confirmation' => 'NewPassword1'])
            ->assertStatus(422)->assertJsonPath('error.code', 'AUTH_CURRENT_PASSWORD_INVALID');
        $this->actingAs($user)->putJson('/me/password', ['current_password' => 'CorrectPass1', 'password' => 'NewPassword1', 'password_confirmation' => 'NewPassword1'])
            ->assertOk()->assertJsonPath('data.password_changed_at', fn ($value): bool => is_string($value));
        $this->assertTrue(password_verify('NewPassword1', (string) DB::table('password_credentials')->where('user_id', $user->id)->value('password_hash')));
    }

    /** @return array<string, mixed> */
    private function candidatePayload(string $email): array
    {
        return ['name' => 'Candidate Test', 'email' => $email, 'password' => 'CandidatePass1', 'password_confirmation' => 'CandidatePass1', 'candidate_type' => 'EXTERNAL', 'accepted_terms' => true];
    }

    /** @return array<string, mixed> */
    private function recruiterPayload(string $email): array
    {
        return ['name' => 'Recruiter Test', 'email' => $email, 'password' => 'RecruiterPass1', 'password_confirmation' => 'RecruiterPass1', 'accepted_terms' => true];
    }
}
