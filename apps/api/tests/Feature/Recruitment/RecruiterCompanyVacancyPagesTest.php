<?php

declare(strict_types=1);

namespace Tests\Feature\Recruitment;

use App\Domains\Company\Enums\CompanyStatus;
use App\Domains\Identity\Enums\RoleCode;
use App\Domains\Identity\Enums\UserStatus;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Vacancy\VacancyTestCase;

/**
 * Recruiter Company & Vacancy Frontend Slice v3 — the new Inertia page routes
 * (`/profil-perusahaan`, `/status-verifikasi`, `/kelola-lowongan`,
 * `/kelola-lowongan/baru`, `/kelola-lowongan/{vacancy}`).
 *
 * These prove component selection, prop shape, the recruiter-safe privacy
 * boundary (`internal_note` never delivered), scope parity with the frozen
 * JSON contracts (company-scoped reads, enumeration-safe 404), and the
 * editable / submit / close capability flags. Business rules already covered
 * by the JSON controllers' own suites are not re-tested here.
 */
final class RecruiterCompanyVacancyPagesTest extends VacancyTestCase
{
    public function test_profil_perusahaan_renders_recruiter_safe_company(): void
    {
        [$recruiter, $company] = $this->companyWithRecruiter('v3-profile@example.test', CompanyStatus::Draft);

        $this->actingAs($recruiter)->get('/profil-perusahaan')->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('recruiter/ProfilPerusahaan')
                ->where('company.id', $company->id)
                ->where('company.verification_status', 'DRAFT')
                ->where('can_edit', true)
                ->where('can_create', false)
                ->missing('company.internal_note')
                ->has('reference_data'),
        );
    }

    public function test_profil_perusahaan_shows_create_form_when_recruiter_has_no_company(): void
    {
        $recruiter = $this->makeUser('v3-nocompany@example.test', UserStatus::Active);
        $this->assignRole($recruiter, RoleCode::CompanyRecruiter);

        $this->actingAs($recruiter)->get('/profil-perusahaan')->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('recruiter/ProfilPerusahaan')
                ->where('company', null)
                ->where('can_create', true),
        );
    }

    public function test_profil_perusahaan_forbidden_for_non_recruiter_without_company(): void
    {
        $candidate = $this->makeUser('v3-candidate@example.test', UserStatus::Active);
        $this->assignRole($candidate, RoleCode::CandidateAlumni);

        $this->actingAs($candidate)->get('/profil-perusahaan')->assertStatus(403);
    }

    public function test_status_verifikasi_exposes_recruiter_safe_trail_only(): void
    {
        [$recruiter, $company] = $this->companyWithRecruiter('v3-verif@example.test', CompanyStatus::RevisionRequired);
        DB::table('company_verification_reviews')->insert([
            'company_id' => $company->id,
            'reviewer_user_id' => $recruiter->id,
            'action' => 'REQUEST_REVISION',
            'from_status' => 'PENDING_VERIFICATION',
            'to_status' => 'REVISION_REQUIRED',
            'reason_category' => 'DOKUMEN',
            'recruiter_visible_note' => 'Mohon unggah ulang akta terbaru.',
            'internal_note' => 'RAHASIA: cross-check NIB manual.',
            'reviewed_at' => now(),
        ]);

        $this->actingAs($recruiter)->get('/status-verifikasi')->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('recruiter/StatusVerifikasi')
                ->where('company.verification_status', 'REVISION_REQUIRED')
                ->where('can_submit', true)
                ->has('trail', 1)
                ->where('trail.0.recruiter_visible_note', 'Mohon unggah ulang akta terbaru.')
                ->where('trail.0.reason_category', 'DOKUMEN')
                ->missing('trail.0.internal_note'),
        );
    }

    public function test_kelola_lowongan_list_is_company_scoped(): void
    {
        [$recruiterA, $companyA] = $this->verifiedCompanyWithRecruiter('v3-list-a@example.test');
        [$recruiterB, $companyB] = $this->verifiedCompanyWithRecruiter('v3-list-b@example.test');
        $mine = $this->vacancyAt($recruiterA, $companyA, 'DRAFT');
        $this->vacancyAt($recruiterB, $companyB, 'DRAFT');

        $this->actingAs($recruiterA)->get('/kelola-lowongan')->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('recruiter/LowonganList')
                ->has('items', 1)
                ->where('items.0.id', $mine)
                ->where('company.can_author_vacancy', true),
        );
    }

    public function test_kelola_lowongan_detail_is_enumeration_safe(): void
    {
        [$recruiterA, $companyA] = $this->verifiedCompanyWithRecruiter('v3-detail-a@example.test');
        [$recruiterB] = $this->verifiedCompanyWithRecruiter('v3-detail-b@example.test');
        $vacancyId = $this->vacancyAt($recruiterA, $companyA, 'DRAFT');

        $this->actingAs($recruiterA)->get("/kelola-lowongan/{$vacancyId}")->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('recruiter/LowonganForm')
                ->where('mode', 'edit')
                ->where('vacancy.id', $vacancyId)
                ->where('editable', true)
                ->where('can_submit', true)
                ->where('can_close', false),
        );

        $this->actingAs($recruiterB)->get("/kelola-lowongan/{$vacancyId}")->assertStatus(404);
    }

    public function test_kelola_lowongan_create_requires_company_membership(): void
    {
        [$recruiter] = $this->verifiedCompanyWithRecruiter('v3-create-ok@example.test');
        $this->actingAs($recruiter)->get('/kelola-lowongan/baru')->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('recruiter/LowonganForm')->where('mode', 'create')->where('editable', true),
        );

        $stranger = $this->makeUser('v3-create-stranger@example.test', UserStatus::Active);
        $this->assignRole($stranger, RoleCode::CompanyRecruiter);
        $this->actingAs($stranger)->get('/kelola-lowongan/baru')->assertStatus(403);
    }

    public function test_published_vacancy_is_read_only_with_owner_close(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('v3-published@example.test');
        $vacancyId = $this->vacancyAt($recruiter, $company, 'PUBLISHED');

        $this->actingAs($recruiter)->get("/kelola-lowongan/{$vacancyId}")->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('recruiter/LowonganForm')
                ->where('editable', false)
                ->where('can_submit', false)
                ->where('can_close', true),
        );
    }

    public function test_revision_required_vacancy_exposes_recruiter_safe_moderation_trail(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('v3-revision@example.test');
        $vacancyId = $this->vacancyAt($recruiter, $company, 'REVISION_REQUIRED');
        DB::table('vacancy_moderation_reviews')->insert([
            'vacancy_id' => $vacancyId,
            'reviewer_user_id' => $recruiter->id,
            'action' => 'REQUEST_REVISION',
            'from_status' => 'PENDING_REVIEW',
            'to_status' => 'REVISION_REQUIRED',
            'reason_category' => 'DESKRIPSI',
            'recruiter_visible_note' => 'Perjelas kualifikasi minimum.',
            'internal_note' => 'RAHASIA: pola template lama.',
            'reviewed_at' => now(),
        ]);

        $this->actingAs($recruiter)->get("/kelola-lowongan/{$vacancyId}")->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('recruiter/LowonganForm')
                ->where('editable', true)
                ->where('can_submit', true)
                ->has('moderation_trail', 1)
                ->where('moderation_trail.0.recruiter_visible_note', 'Perjelas kualifikasi minimum.')
                ->where('moderation_trail.0.reason_category', 'DESKRIPSI')
                ->missing('moderation_trail.0.internal_note'),
        );
    }

    public function test_non_verified_company_cannot_author_vacancy_gate_is_communicated(): void
    {
        [$recruiter] = $this->companyWithRecruiter('v3-unverified@example.test', CompanyStatus::PendingVerification);

        $this->actingAs($recruiter)->get('/kelola-lowongan')->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('recruiter/LowonganList')
                ->where('company.can_author_vacancy', false)
                ->where('company.verification_status', 'PENDING_VERIFICATION'),
        );

        // The create page still renders (form is fillable) — the backend gate is authority.
        $this->actingAs($recruiter)->get('/kelola-lowongan/baru')->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('recruiter/LowonganForm')->where('company.can_author_vacancy', false),
        );
    }
}
