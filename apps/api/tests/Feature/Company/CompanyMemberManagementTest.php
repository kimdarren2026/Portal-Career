<?php

declare(strict_types=1);

namespace Tests\Feature\Company;

use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\CompanyMember;
use App\Domains\Company\Support\CompanyMemberInviteLimiter;
use App\Domains\Identity\Enums\RoleCode;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Identity\IdentityTestCase;

/** FR-COMP-004 + closed D-1: membership surface and the last-admin invariant. */
final class CompanyMemberManagementTest extends IdentityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_company_admin_adds_member_with_explicit_role_and_no_implicit_default(): void
    {
        [$admin, $company] = $this->companyWithAdmin('add-admin@example.test');
        $invitee = $this->user('invitee@example.test');

        // D-1: role is mandatory for every member after the creator.
        $this->actingAs($admin)->postJson("/companies/{$company->id}/members", ['email' => $invitee->email])
            ->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_FAILED');

        $this->actingAs($admin)->postJson("/companies/{$company->id}/members", [
            'email' => $invitee->email, 'company_role' => 'COMPANY_RECRUITER',
        ])->assertCreated()->assertJsonPath('data.company_role', 'COMPANY_RECRUITER');

        $this->assertDatabaseHas('company_members', [
            'company_id' => $company->id, 'user_id' => $invitee->id,
            'company_role' => 'COMPANY_RECRUITER', 'status' => 'ACTIVE',
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'company_member_added']);
        $this->assertDatabaseHas('email_outbox', ['recipient' => $invitee->email, 'template_reference' => 'company.member.invited']);
    }

    public function test_duplicate_active_membership_is_conflict_not_server_error(): void
    {
        [$admin, $company] = $this->companyWithAdmin('dup-admin@example.test');
        $invitee = $this->user('dup-invitee@example.test');
        $payload = ['email' => $invitee->email, 'company_role' => 'COMPANY_RECRUITER'];

        $this->actingAs($admin)->postJson("/companies/{$company->id}/members", $payload)->assertCreated();
        $this->actingAs($admin)->postJson("/companies/{$company->id}/members", $payload)
            ->assertStatus(409)->assertJsonPath('error.code', 'MEMBER_ALREADY_ACTIVE');
    }

    public function test_unknown_email_is_not_supported_and_leaks_no_account_signal(): void
    {
        [$admin, $company] = $this->companyWithAdmin('unknown-admin@example.test');
        $foreign = $this->user('foreign-for-probe@example.test');
        $foreignMembership = CompanyMember::query()->where('user_id', $foreign->id)->first();

        // Approved 25 August 2026 (Part X item 10): an address with no account is
        // NOT SUPPORTED — no membership, no email, no pending invitation.
        $unknown = $this->actingAs($admin)->postJson("/companies/{$company->id}/members", [
            'email' => 'nobody@example.test', 'company_role' => 'COMPANY_ADMIN',
        ])->assertNotFound()->assertJsonPath('error.code', 'NOT_FOUND');

        $this->assertSame(1, DB::table('company_members')->where('company_id', $company->id)->count());
        $this->assertDatabaseMissing('email_outbox', ['recipient' => 'nobody@example.test']);

        // The unknown-account answer must be indistinguishable from an
        // out-of-scope member id, or the route becomes an enumeration oracle.
        $outOfScope = $this->actingAs($admin)->patchJson(
            "/companies/{$company->id}/members/".($foreignMembership?->id ?? 999999),
            ['company_role' => 'COMPANY_RECRUITER'],
        )->assertNotFound();

        $this->assertSame(
            $outOfScope->json('error.code'),
            $unknown->json('error.code'),
            'Unknown account and out-of-scope member must return the same error code.',
        );
    }

    public function test_member_invitation_is_rate_limited_per_admin_identity(): void
    {
        [$admin, $company] = $this->companyWithAdmin('rate-admin@example.test');
        app(CompanyMemberInviteLimiter::class)->clear($admin);

        // Attempts are counted, not successes: a probing loop costs the same.
        for ($attempt = 0; $attempt < CompanyMemberInviteLimiter::LIMIT; $attempt++) {
            $this->actingAs($admin)->postJson("/companies/{$company->id}/members", [
                'email' => "probe{$attempt}@example.test", 'company_role' => 'COMPANY_RECRUITER',
            ])->assertNotFound();
        }

        $blocked = $this->actingAs($admin)->postJson("/companies/{$company->id}/members", [
            'email' => 'probe-final@example.test', 'company_role' => 'COMPANY_RECRUITER',
        ])->assertStatus(429)->assertJsonPath('error.code', 'RATE_LIMITED');
        $this->assertNotEmpty($blocked->headers->get('Retry-After'));

        app(CompanyMemberInviteLimiter::class)->clear($admin);
    }

    public function test_last_active_company_admin_cannot_be_demoted_or_revoked_over_http(): void
    {
        [$admin, $company] = $this->companyWithAdmin('last-admin@example.test');
        $membership = $this->membership($company, $admin);

        $this->actingAs($admin)->patchJson("/companies/{$company->id}/members/{$membership->id}", ['company_role' => 'COMPANY_RECRUITER'])
            ->assertStatus(409)->assertJsonPath('error.code', 'MEMBER_LAST_ADMIN');
        $this->actingAs($admin)->deleteJson("/companies/{$company->id}/members/{$membership->id}")
            ->assertStatus(409)->assertJsonPath('error.code', 'MEMBER_LAST_ADMIN');

        $this->assertDatabaseHas('company_members', ['id' => $membership->id, 'company_role' => 'COMPANY_ADMIN', 'revoked_at' => null]);
    }

    public function test_admin_may_step_down_once_a_second_active_admin_exists(): void
    {
        [$admin, $company] = $this->companyWithAdmin('step-admin@example.test');
        $second = $this->user('second-admin@example.test');
        $this->actingAs($admin)->postJson("/companies/{$company->id}/members", [
            'email' => $second->email, 'company_role' => 'COMPANY_ADMIN',
        ])->assertCreated();

        $membership = $this->membership($company, $admin);
        $this->actingAs($admin)->patchJson("/companies/{$company->id}/members/{$membership->id}", ['company_role' => 'COMPANY_RECRUITER'])
            ->assertOk()->assertJsonPath('data.company_role', 'COMPANY_RECRUITER');
        $this->assertDatabaseHas('audit_logs', ['action' => 'company_member_role_changed']);
    }

    public function test_revoke_preserves_the_row_and_removes_company_scope(): void
    {
        [$admin, $company] = $this->companyWithAdmin('revoke-admin@example.test');
        $member = $this->user('revoked-member@example.test');
        $this->actingAs($admin)->postJson("/companies/{$company->id}/members", [
            'email' => $member->email, 'company_role' => 'COMPANY_RECRUITER',
        ])->assertCreated();
        $membership = $this->membership($company, $member);

        $this->actingAs($admin)->deleteJson("/companies/{$company->id}/members/{$membership->id}")->assertOk();

        // FR-COMP-004: revoking never deletes history.
        $this->assertDatabaseHas('company_members', ['id' => $membership->id, 'user_id' => $member->id]);
        $this->assertNotNull(DB::table('company_members')->where('id', $membership->id)->value('revoked_at'));
        $this->actingAs($member)->getJson("/companies/{$company->id}")->assertNotFound();
    }

    public function test_ordinary_recruiter_cannot_manage_members_but_may_leave(): void
    {
        [$admin, $company] = $this->companyWithAdmin('mgmt-admin@example.test');
        $recruiter = $this->user('plain-recruiter@example.test');
        $this->actingAs($admin)->postJson("/companies/{$company->id}/members", [
            'email' => $recruiter->email, 'company_role' => 'COMPANY_RECRUITER',
        ])->assertCreated();
        $adminMembership = $this->membership($company, $admin);
        $ownMembership = $this->membership($company, $recruiter);

        $this->actingAs($recruiter)->postJson("/companies/{$company->id}/members", [
            'email' => 'x@example.test', 'company_role' => 'COMPANY_RECRUITER',
        ])->assertForbidden()->assertJsonPath('error.code', 'AUTH_FORBIDDEN');
        $this->actingAs($recruiter)->deleteJson("/companies/{$company->id}/members/{$adminMembership->id}")
            ->assertForbidden();

        // Leaving is the member's own act.
        $this->actingAs($recruiter)->deleteJson("/companies/{$company->id}/members/{$ownMembership->id}")->assertOk();
    }

    public function test_member_routes_are_scoped_and_report_not_found_rather_than_forbidden(): void
    {
        [$admin, $company] = $this->companyWithAdmin('scoped-admin@example.test');
        [$otherAdmin, $otherCompany] = $this->companyWithAdmin('other-admin@example.test');
        $foreignMembership = $this->membership($otherCompany, $otherAdmin);

        $this->actingAs($admin)->getJson("/companies/{$otherCompany->id}/members")
            ->assertNotFound()->assertJsonPath('error.code', 'NOT_FOUND');
        // A membership of another company must not be reachable through this company.
        $this->actingAs($admin)->patchJson("/companies/{$company->id}/members/{$foreignMembership->id}", ['company_role' => 'COMPANY_RECRUITER'])
            ->assertNotFound()->assertJsonPath('error.code', 'NOT_FOUND');
    }

    private function companyWithAdmin(string $email): array
    {
        $admin = $this->user($email);
        app(CompanyMemberInviteLimiter::class)->clear($admin);
        $this->actingAs($admin)->postJson('/companies', ['name' => 'Company '.$this->sequence])->assertCreated();
        $company = Company::query()->where('created_by', $admin->id)->firstOrFail();

        return [$admin, $company];
    }

    private function membership(Company $company, User $user): CompanyMember
    {
        return CompanyMember::query()->where('company_id', $company->getKey())->where('user_id', $user->getKey())->firstOrFail();
    }

    private function user(string $email, UserStatus $status = UserStatus::Active): User
    {
        $user = $this->makeUser($email, $status);
        $this->assignRole($user, RoleCode::CompanyRecruiter);

        return $user;
    }
}
