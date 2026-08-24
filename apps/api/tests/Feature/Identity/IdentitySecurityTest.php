<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Domains\Identity\Actions\IssueEmailVerificationToken;
use App\Domains\Identity\Actions\IssuePasswordResetToken;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Models\EmailVerificationToken;
use App\Domains\Identity\Models\PasswordResetToken;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Models\UserRole;
use Illuminate\Support\Facades\DB;

final class IdentitySecurityTest extends IdentityTestCase
{
    public function test_no_raw_token_is_persisted_anywhere(): void
    {
        $user = $this->makeUser(status: UserStatus::PendingEmailVerification, verifiedAt: null);

        $verification = app(IssueEmailVerificationToken::class)->execute($user, queueEmail: true);
        $reset = app(IssuePasswordResetToken::class)->execute($user->email, queueEmail: true);

        foreach (['email_verification_tokens', 'password_reset_tokens', 'email_outbox', 'users', 'audit_logs'] as $table) {
            foreach (DB::table($table)->get() as $row) {
                foreach ((array) $row as $value) {
                    if (! is_string($value)) {
                        continue;
                    }
                    $this->assertStringNotContainsString($verification, $value, "raw verification token leaked into {$table}");
                    $this->assertStringNotContainsString((string) $reset, $value, "raw reset token leaked into {$table}");
                }
            }
        }
    }

    public function test_token_hash_columns_are_hidden_from_model_serialization(): void
    {
        $user = $this->makeUser(status: UserStatus::PendingEmailVerification, verifiedAt: null);
        app(IssueEmailVerificationToken::class)->execute($user, queueEmail: false);
        app(IssuePasswordResetToken::class)->execute($user->email, queueEmail: false);

        $this->assertArrayNotHasKey('token_hash', EmailVerificationToken::query()->first()->toArray());
        $this->assertArrayNotHasKey('token_hash', PasswordResetToken::query()->first()->toArray());
    }

    public function test_lifecycle_attributes_resist_mass_assignment(): void
    {
        $user = $this->makeUser(status: UserStatus::PendingEmailVerification, verifiedAt: null);

        // A request payload must never be able to activate an account or
        // pre-verify an email — those move only through Actions.
        $user->fill([
            'status' => UserStatus::Active->value,
            'email_verified_at' => now(),
            'disabled_at' => now(),
        ]);
        $user->save();

        $fresh = $user->fresh();
        $this->assertSame(UserStatus::PendingEmailVerification, $fresh->status);
        $this->assertNull($fresh->email_verified_at);
        $this->assertNull($fresh->disabled_at);
    }

    public function test_user_role_revocation_attributes_resist_mass_assignment(): void
    {
        $user = $this->makeUser();
        $assignment = $this->assignRole($user, \App\Domains\Identity\Enums\RoleCode::Auditor);

        $assignment->fill(['revoked_at' => now(), 'revoked_by' => $user->getKey()]);
        $assignment->save();

        $this->assertNull($assignment->fresh()->revoked_at);
    }

    public function test_password_credential_hash_resists_mass_assignment(): void
    {
        $user = $this->makeUser(password: 'correct-horse-battery');
        $credential = $user->passwordCredential;
        $original = $credential->password_hash;

        $credential->fill(['password_hash' => 'not-a-real-hash']);
        $credential->save();

        $this->assertSame($original, $credential->fresh()->password_hash);
    }

    public function test_password_hash_uses_the_configured_adaptive_hasher(): void
    {
        $user = $this->makeUser(password: 'correct-horse-battery');
        $hash = $user->passwordCredential->password_hash;

        $info = password_get_info($hash);
        $this->assertNotNull($info['algo']);
        $this->assertStringNotContainsString('correct-horse-battery', $hash);
    }

    public function test_account_status_vocabulary_matches_the_database_check(): void
    {
        $applicationValues = array_map(static fn (UserStatus $s): string => $s->value, UserStatus::cases());
        sort($applicationValues);

        $check = DB::selectOne("
            select pg_get_constraintdef(oid) as def
            from pg_constraint where conname = 'chk_users_status'
        ");

        foreach ($applicationValues as $value) {
            $this->assertStringContainsString($value, $check->def);
        }
        // And no application value exists that PostgreSQL would reject.
        $this->assertSame(['ACTIVE', 'DISABLED', 'PENDING_EMAIL_VERIFICATION', 'SUSPENDED'], $applicationValues);
    }

    public function test_no_personal_access_tokens_table_exists(): void
    {
        // Sanctum is reserved for /api/v1 and deliberately not activated.
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasTable('personal_access_tokens'));
    }

    public function test_users_table_has_no_laravel_default_auth_columns(): void
    {
        foreach (['password', 'remember_token', 'deleted_at'] as $column) {
            $this->assertFalse(
                \Illuminate\Support\Facades\Schema::hasColumn('users', $column),
                "users.{$column} must not exist in the frozen schema."
            );
        }
    }

    public function test_outbox_row_never_carries_a_credential(): void
    {
        $user = $this->makeUser(password: 'correct-horse-battery');
        app(IssuePasswordResetToken::class)->execute($user->email, queueEmail: true);

        $payload = (string) DB::table('email_outbox')->value('payload_reference');

        $this->assertStringNotContainsString('correct-horse-battery', $payload);
        $this->assertStringNotContainsString('$2y$', $payload);
        $this->assertStringNotContainsString('token', strtolower($payload));
    }
}
