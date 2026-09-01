<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Domains\Identity\Enums\RoleCode;
use App\Domains\Identity\Enums\UserStatus;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Vacancy\VacancyTestCase;

/**
 * Recruiter Account Settings Frontend Slice v6 — the `/pengaturan-akun`
 * Inertia page. Proves component selection, the self-only `/me` subset it
 * delivers, the persona boundary and that no credential material is exposed.
 * Password-change behaviour stays covered by the `PUT /me/password` suite.
 */
final class RecruiterAccountSettingsPageTest extends VacancyTestCase
{
    public function test_renders_self_account_context_only(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('v6-acct@example.test');

        $response = $this->actingAs($recruiter)->get('/pengaturan-akun');
        $response->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('recruiter/PengaturanAkun')
                ->where('account.email', $recruiter->email)
                ->where('account.name', $recruiter->name)
                ->has('account.email_verified_at')
                ->has('account.status')
                ->where('roles', ['COMPANY_RECRUITER'])
                ->has('company_memberships', 1)
                ->where('company_memberships.0.name', $company->name)
                ->missing('account.password')
                ->missing('account.password_hash'),
        );

        $body = $response->getContent() ?: '';
        $this->assertStringNotContainsString('password_hash', $body);
        $this->assertStringNotContainsString('reset_token', $body);
    }

    public function test_forbidden_for_non_recruiter_persona(): void
    {
        $candidate = $this->makeUser('v6-acct-candidate@example.test', UserStatus::Active);
        $this->assignRole($candidate, RoleCode::CandidateAlumni);

        $this->actingAs($candidate)->get('/pengaturan-akun')->assertStatus(403);
    }
}
