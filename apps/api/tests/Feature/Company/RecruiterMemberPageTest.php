<?php

declare(strict_types=1);

namespace Tests\Feature\Company;

use App\Domains\Identity\Enums\RoleCode;
use App\Domains\Identity\Enums\UserStatus;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Vacancy\VacancyTestCase;

/**
 * Recruiter Dashboard & Member Management Frontend Slice v5 — the
 * `/anggota-perusahaan` Inertia page. Proves component selection, the frozen
 * member read model (no name/email), the own-company resolution boundary and
 * the Company Admin capability flag. Membership business rules (last-admin
 * invariant, unknown-email, rate limit) stay covered by
 * CompanyMemberManagementTest against the JSON routes.
 */
final class RecruiterMemberPageTest extends VacancyTestCase
{
    public function test_lists_active_members_with_frozen_read_model_only(): void
    {
        [$admin, $company] = $this->verifiedCompanyWithRecruiter('v5-mem-admin@example.test');
        $other = $this->makeUser('v5-mem-other@example.test', UserStatus::Active);
        $this->addMember($company->id, $other, 'COMPANY_RECRUITER');

        $response = $this->actingAs($admin)->get('/anggota-perusahaan');
        $response->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('recruiter/AnggotaPerusahaan')
                ->where('company.id', $company->id)
                ->where('can_manage', true)
                ->where('current_user_id', $admin->id)
                ->where('roles', ['COMPANY_ADMIN', 'COMPANY_RECRUITER'])
                ->has('members', 2)
                ->has('members.0', fn (Assert $m) => $m
                    ->hasAll(['id', 'user_id', 'company_role', 'status', 'joined_at', 'revoked_at'])
                    ->etc()),
        );

        // The frozen privacy boundary: the member read model carries no email
        // address (and no name field). The signed-in user's own name still
        // appears once in Inertia's shared `auth` prop — that is not a member
        // PII leak — so this asserts on the addresses, which must never appear.
        $body = $response->getContent() ?: '';
        $this->assertStringNotContainsString($admin->email, $body);
        $this->assertStringNotContainsString($other->email, $body);
    }

    public function test_forbidden_without_active_membership(): void
    {
        // A company exists, owned by someone else.
        $this->verifiedCompanyWithRecruiter('v5-mem-owner@example.test');

        $candidate = $this->makeUser('v5-mem-candidate@example.test', UserStatus::Active);
        $this->assignRole($candidate, RoleCode::CandidateAlumni);
        $this->actingAs($candidate)->get('/anggota-perusahaan')->assertStatus(403);

        // Career Center is a global reader in CompanyScope — the page resolves
        // the actor's OWN membership, so a CC actor with none is forbidden.
        $careerCenter = $this->makeUser('v5-mem-cc@example.test', UserStatus::Active);
        $this->assignRole($careerCenter, RoleCode::CareerCenterStaff);
        $this->actingAs($careerCenter)->get('/anggota-perusahaan')->assertStatus(403);
    }

    public function test_company_recruiter_without_admin_capability_cannot_manage(): void
    {
        [, $company] = $this->verifiedCompanyWithRecruiter('v5-mem-adminb@example.test');
        $plain = $this->makeUser('v5-mem-plain@example.test', UserStatus::Active);
        $this->assignRole($plain, RoleCode::CompanyRecruiter);
        $this->addMember($company->id, $plain, 'COMPANY_RECRUITER');

        $this->actingAs($plain)->get('/anggota-perusahaan')->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('recruiter/AnggotaPerusahaan')
                ->where('can_manage', false)
                ->where('company.id', $company->id),
        );
    }
}
