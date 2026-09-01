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
 * Career Center Company Verification & Vacancy Moderation Frontend Slice v4 —
 * the new Inertia page routes (`/verifikasi-perusahaan`,
 * `/verifikasi-perusahaan/{company}`, `/moderasi-lowongan`,
 * `/moderasi-lowongan/{vacancy}`).
 *
 * These prove component/prop shape, the Career-Center-persona gate, the
 * server-derived `eligible_actions` against the frozen transition graphs, the
 * moderator privacy posture (Career Center IS authorised to see
 * `internal_note`), enumeration-safe 404, and that Career Center gains no
 * applicant-selection surface. Business rules already covered by the JSON
 * moderation suites are not re-tested here.
 */
final class CareerCenterModerationPagesTest extends VacancyTestCase
{
    private function candidateUser(string $email): \App\Domains\Identity\Models\User
    {
        $user = $this->makeUser($email, UserStatus::Active);
        $this->assignRole($user, RoleCode::CandidateAlumni);

        return $user;
    }

    public function test_verification_queue_renders_for_career_center_only(): void
    {
        $this->companyWithRecruiter('v4-queue-a@example.test', CompanyStatus::PendingVerification);
        $this->companyWithRecruiter('v4-queue-b@example.test', CompanyStatus::Verified);
        $moderator = $this->moderator('v4-queue-mod@example.test');

        $this->actingAs($moderator)->get('/verifikasi-perusahaan')->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('career-center/VerifikasiPerusahaan')
                ->has('items')
                ->has('pagination'),
        );

        $this->actingAs($moderator)->get('/verifikasi-perusahaan?status=PENDING_VERIFICATION')->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('career-center/VerifikasiPerusahaan')
                ->where('filters.status', 'PENDING_VERIFICATION'),
        );

        [$recruiter] = $this->companyWithRecruiter('v4-queue-recruiter@example.test', CompanyStatus::Draft);
        $this->actingAs($recruiter)->get('/verifikasi-perusahaan')->assertStatus(403);
        $this->actingAs($this->candidateUser('v4-queue-cand@example.test'))->get('/verifikasi-perusahaan')->assertStatus(403);
    }

    public function test_verification_detail_exposes_full_moderator_trail_including_internal_note(): void
    {
        [$recruiter, $company] = $this->companyWithRecruiter('v4-detail@example.test', CompanyStatus::PendingVerification);
        DB::table('company_verification_reviews')->insert([
            'company_id' => $company->id,
            'reviewer_user_id' => $recruiter->id,
            'action' => 'SUBMIT',
            'from_status' => 'DRAFT',
            'to_status' => 'PENDING_VERIFICATION',
            'reviewed_at' => now()->subMinutes(5),
        ]);
        DB::table('company_verification_reviews')->insert([
            'company_id' => $company->id,
            'reviewer_user_id' => $recruiter->id,
            'action' => 'REQUEST_REVISION',
            'from_status' => 'PENDING_VERIFICATION',
            'to_status' => 'REVISION_REQUIRED',
            'reason_category' => 'DOKUMEN',
            'recruiter_visible_note' => 'Unggah ulang NIB.',
            'internal_note' => 'RAHASIA: cek manual ke AHU.',
            'reviewed_at' => now(),
        ]);

        $moderator = $this->moderator('v4-detail-mod@example.test');
        $this->actingAs($moderator)->get("/verifikasi-perusahaan/{$company->id}")->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('career-center/TinjauPerusahaan')
                ->where('company.id', $company->id)
                ->where('company.verification_status', 'PENDING_VERIFICATION')
                ->where('eligible_actions', ['verify', 'request-revision', 'reject'])
                ->has('company.verification_history', 2)
                ->where('company.verification_history.1.internal_note', 'RAHASIA: cek manual ke AHU.')
                ->where('company.verification_history.1.recruiter_visible_note', 'Unggah ulang NIB.'),
        );
    }

    public function test_verification_detail_eligible_actions_track_status(): void
    {
        $moderator = $this->moderator('v4-elig-mod@example.test');

        [, $verified] = $this->companyWithRecruiter('v4-elig-verified@example.test', CompanyStatus::Verified);
        $this->actingAs($moderator)->get("/verifikasi-perusahaan/{$verified->id}")->assertOk()->assertInertia(
            fn (Assert $page) => $page->where('eligible_actions', ['suspend']),
        );

        [, $suspended] = $this->companyWithRecruiter('v4-elig-suspended@example.test', CompanyStatus::Suspended);
        $this->actingAs($moderator)->get("/verifikasi-perusahaan/{$suspended->id}")->assertOk()->assertInertia(
            fn (Assert $page) => $page->where('eligible_actions', ['restore']),
        );

        [, $draft] = $this->companyWithRecruiter('v4-elig-draft@example.test', CompanyStatus::Draft);
        $this->actingAs($moderator)->get("/verifikasi-perusahaan/{$draft->id}")->assertOk()->assertInertia(
            fn (Assert $page) => $page->where('eligible_actions', []),
        );
    }

    public function test_verification_detail_is_enumeration_safe(): void
    {
        $moderator = $this->moderator('v4-enum-mod@example.test');
        $this->actingAs($moderator)->get('/verifikasi-perusahaan/99999')->assertStatus(404);
    }

    public function test_moderation_queue_renders_for_career_center_only(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('v4-modq@example.test');
        $vacancyId = $this->vacancyAt($recruiter, $company, 'PENDING_REVIEW');
        $moderator = $this->moderator('v4-modq-mod@example.test');

        $this->actingAs($moderator)->get('/moderasi-lowongan')->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('career-center/ModerasiLowongan')
                ->has('items')
                ->where('items.0.id', $vacancyId)
                ->where('items.0.company_name', $company->name),
        );

        $this->actingAs($recruiter)->get('/moderasi-lowongan')->assertStatus(403);
        $this->actingAs($this->candidateUser('v4-modq-cand@example.test'))->get('/moderasi-lowongan')->assertStatus(403);
    }

    public function test_moderation_detail_eligible_actions_and_internal_note_visible_to_career_center(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('v4-moddetail@example.test');
        $vacancyId = $this->vacancyAt($recruiter, $company, 'PENDING_REVIEW');
        DB::table('vacancy_moderation_reviews')->insert([
            'vacancy_id' => $vacancyId,
            'reviewer_user_id' => $recruiter->id,
            'action' => 'REQUEST_REVISION',
            'from_status' => 'PENDING_REVIEW',
            'to_status' => 'REVISION_REQUIRED',
            'reason_category' => 'DESKRIPSI',
            'recruiter_visible_note' => 'Perjelas kualifikasi.',
            'internal_note' => 'RAHASIA MODERATOR.',
            'reviewed_at' => now(),
        ]);

        $moderator = $this->moderator('v4-moddetail-mod@example.test');
        $this->actingAs($moderator)->get("/moderasi-lowongan/{$vacancyId}")->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('career-center/TinjauLowongan')
                ->where('vacancy.id', $vacancyId)
                ->where('eligible_actions', ['approve', 'request-revision', 'reject'])
                // submittablePayload() sets open_at = now()+1d, so approve would SCHEDULE (B-1).
                ->where('approve_effect', 'scheduled')
                ->has('moderation_trail', 1)
                ->where('moderation_trail.0.internal_note', 'RAHASIA MODERATOR.')
                ->where('moderation_trail.0.recruiter_visible_note', 'Perjelas kualifikasi.'),
        );
    }

    public function test_moderation_detail_eligible_actions_track_status(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('v4-modstatus@example.test');
        $moderator = $this->moderator('v4-modstatus-mod@example.test');

        $published = $this->vacancyAt($recruiter, $company, 'PUBLISHED');
        $this->actingAs($moderator)->get("/moderasi-lowongan/{$published}")->assertOk()->assertInertia(
            fn (Assert $page) => $page->where('eligible_actions', ['suspend', 'close']),
        );

        $suspended = $this->vacancyAt($recruiter, $company, 'SUSPENDED');
        $this->actingAs($moderator)->get("/moderasi-lowongan/{$suspended}")->assertOk()->assertInertia(
            fn (Assert $page) => $page->where('eligible_actions', ['restore']),
        );

        $draft = $this->vacancyAt($recruiter, $company, 'DRAFT');
        $this->actingAs($moderator)->get("/moderasi-lowongan/{$draft}")->assertOk()->assertInertia(
            fn (Assert $page) => $page->where('eligible_actions', []),
        );
    }

    public function test_moderation_detail_is_enumeration_safe(): void
    {
        $moderator = $this->moderator('v4-modenum-mod@example.test');
        $this->actingAs($moderator)->get('/moderasi-lowongan/99999')->assertStatus(404);
    }

    public function test_career_center_gains_no_applicant_selection_surface(): void
    {
        $moderator = $this->moderator('v4-noselect-mod@example.test');
        // The recruiter applicant workspace (where transitions/stage moves are driven) stays closed to Career Center.
        $this->actingAs($moderator)->get('/pelamar')->assertStatus(403);
    }
}
