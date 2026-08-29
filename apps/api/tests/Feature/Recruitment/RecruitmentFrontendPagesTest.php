<?php

declare(strict_types=1);

namespace Tests\Feature\Recruitment;

use App\Domains\Identity\Enums\RoleCode;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Vacancy\VacancyTestCase;

/**
 * Recruitment Frontend Vertical Slice v1 — the new Inertia page routes.
 * Every page controller reuses the frozen Query/Scope/Presenter classes
 * directly; these tests prove component selection, prop shape, and
 * authorization/scope parity with the underlying JSON contract — they do
 * not re-test business rules already covered by the JSON controllers' own
 * suites.
 */
final class RecruitmentFrontendPagesTest extends VacancyTestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_dashboard_renders_per_persona(): void
    {
        [$admin] = $this->openVacancy('frontend-dash-recruiter@example.test');
        $this->actingAs($admin)->get('/dashboard')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('recruiter/Dashboard')->has('counts'));

        [$candidate] = $this->candidate('frontend-dash-candidate@example.test');
        $this->actingAs($candidate)->get('/dashboard')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('candidate/Dashboard')->has('counts'));
    }

    public function test_lamaran_saya_lists_only_own_applications(): void
    {
        [$admin, , $vacancyId] = $this->openVacancy('frontend-lamaran@example.test');
        [$candidateA] = $this->candidate('frontend-lamaran-a@example.test');
        [$candidateB] = $this->candidate('frontend-lamaran-b@example.test');
        $applicationA = $this->submitApplication($candidateA, $vacancyId);
        $this->submitApplication($candidateB, $vacancyId);

        $this->actingAs($candidateA)->get('/lamaran-saya')->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('candidate/LamaranSaya')
                ->has('items', 1)
                ->where('items.0.id', $applicationA)
                ->where('items.0.vacancy_title', DB::table('vacancies')->where('id', $vacancyId)->value('title')),
        );
    }

    public function test_application_detail_enumeration_safe_for_foreign_candidate(): void
    {
        [, , $vacancyId] = $this->openVacancy('frontend-appdetail@example.test');
        [$owner] = $this->candidate('frontend-appdetail-owner@example.test');
        [$other] = $this->candidate('frontend-appdetail-other@example.test');
        $applicationId = $this->submitApplication($owner, $vacancyId);

        $this->actingAs($owner)->get("/lamaran-saya/{$applicationId}")->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('candidate/ApplicationDetail')->where('application.id', $applicationId));

        $this->actingAs($other)->get("/lamaran-saya/{$applicationId}")->assertStatus(404);
    }

    public function test_apply_page_blocks_external_ats_vacancy(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('frontend-apply-ext@example.test');
        $close = Carbon::parse('2026-12-01T09:00:00+00:00');
        $open = $close->copy()->subDays(20);
        $vacancyId = $this->vacancyAt($recruiter, $company, 'PENDING_REVIEW', [
            'open_at' => $open->toIso8601String(), 'close_at' => $close->toIso8601String(),
            'application_method' => 'EXTERNAL_ATS', 'external_ats_url' => 'https://example.test/careers',
        ]);
        $slug = DB::table('vacancies')->where('id', $vacancyId)->value('slug');
        $approver = $this->moderator('frontend-apply-ext-approver@example.test');
        $this->actingAs($approver)->postJson("/vacancies/{$vacancyId}/approve")->assertOk();
        DB::table('vacancies')->where('id', $vacancyId)->update(['current_status' => 'PUBLISHED', 'published_at' => $open]);
        Carbon::setTestNow($close->copy()->subDays(10));

        [$candidate] = $this->candidate('frontend-apply-ext-candidate@example.test');
        $this->actingAs($candidate)->get("/lowongan/{$slug}/lamar")->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('candidate/ApplyForm')->where('vacancy.applies_externally', true));
    }

    public function test_apply_page_offers_in_portal_form_and_submit_creates_application(): void
    {
        [$admin, , $vacancyId] = $this->openVacancy('frontend-apply-ok@example.test');
        $slug = DB::table('vacancies')->where('id', $vacancyId)->value('slug');
        [$candidate] = $this->candidate('frontend-apply-ok-candidate@example.test');

        $this->actingAs($candidate)->get("/lowongan/{$slug}/lamar")->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('candidate/ApplyForm')
                ->where('vacancy.applies_externally', false)
                ->where('existing_application_id', null),
        );

        $response = $this->actingAs($candidate)->postJson("/vacancies/{$vacancyId}/applications", [
            'consent' => [
                'consent_version' => \App\Domains\Application\Support\ApplicationConsentVersion::CURRENT,
                'consent_text_hash_reference' => 'sha256:'.str_repeat('a', 64),
                'accepted' => true,
            ],
        ])->assertCreated();

        $applicationId = $response->json('data.id');
        $this->actingAs($candidate)->get("/lowongan/{$slug}/lamar")->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('existing_application_id', $applicationId));
    }

    public function test_jadwal_seleksi_branches_by_persona(): void
    {
        [$admin] = $this->openVacancy('frontend-jadwal@example.test');
        $this->actingAs($admin)->get('/jadwal-seleksi')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('recruiter/JadwalSeleksi'));

        [$candidate] = $this->candidate('frontend-jadwal-candidate@example.test');
        $this->actingAs($candidate)->get('/jadwal-seleksi')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('candidate/JadwalSeleksi'));
    }

    public function test_pelamar_list_denies_candidate_and_scopes_by_company(): void
    {
        [$adminA, , $vacancyIdA] = $this->openVacancy('frontend-pelamar-a@example.test');
        [, , $vacancyIdB] = $this->openVacancy('frontend-pelamar-b@example.test');
        [$candidateA] = $this->candidate('frontend-pelamar-ca@example.test');
        [$candidateB] = $this->candidate('frontend-pelamar-cb@example.test');
        $applicationA = $this->submitApplication($candidateA, $vacancyIdA);
        $this->submitApplication($candidateB, $vacancyIdB);

        $this->actingAs($adminA)->get('/pelamar')->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('recruiter/Pelamar')->has('items', 1)->where('items.0.id', $applicationA),
        );

        $this->actingAs($candidateA)->get('/pelamar')->assertStatus(403);
    }

    public function test_applicant_detail_includes_stages_and_is_enumeration_safe(): void
    {
        [$adminA, , $vacancyIdA] = $this->openVacancy('frontend-appdet-a@example.test');
        [$adminB] = $this->openVacancy('frontend-appdet-b@example.test');
        [$candidate] = $this->candidate('frontend-appdet-candidate@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyIdA);
        DB::table('recruitment_stages')->insert([
            'vacancy_id' => $vacancyIdA, 'name' => 'Wawancara', 'stage_type' => 'INTERVIEW',
            'sort_order' => 1, 'active' => true, 'created_at' => now(),
        ]);

        $this->actingAs($adminA)->get("/pelamar/{$applicationId}")->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('recruiter/ApplicantDetail')
                ->where('application.id', $applicationId)
                ->has('stages', 1)
                ->has('schedules', 0),
        );

        $this->actingAs($adminB)->get("/pelamar/{$applicationId}")->assertStatus(404);
    }

    public function test_transition_move_stage_and_schedule_create_reachable_from_applicant_detail(): void
    {
        [$admin, , $vacancyId] = $this->openVacancy('frontend-actions@example.test');
        [$candidate] = $this->candidate('frontend-actions-candidate@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);
        $stageId = DB::table('recruitment_stages')->insertGetId([
            'vacancy_id' => $vacancyId, 'name' => 'Wawancara', 'stage_type' => 'INTERVIEW',
            'sort_order' => 1, 'active' => true, 'created_at' => now(),
        ]);

        $this->actingAs($admin)->postJson("/applications/{$applicationId}/transition", [
            'to_status' => 'UNDER_REVIEW', 'candidate_visibility' => 'VISIBLE',
        ])->assertOk();

        $this->actingAs($admin)->postJson("/applications/{$applicationId}/move-stage", [
            'to_stage_id' => $stageId, 'candidate_visibility' => 'INTERNAL',
        ])->assertOk();

        $this->actingAs($admin)->postJson("/applications/{$applicationId}/schedules", [
            'recruitment_stage_id' => $stageId, 'selection_type' => 'Wawancara HR',
            'starts_at' => now()->addDays(3)->toIso8601String(), 'timezone' => 'Asia/Jakarta',
            'method' => 'ONLINE', 'meeting_url' => 'https://meet.example.test/abc',
        ])->assertCreated();

        $this->actingAs($admin)->get("/pelamar/{$applicationId}")->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('recruiter/ApplicantDetail')
                ->where('application.current_status', 'UNDER_REVIEW')
                ->where('application.current_stage_id', $stageId)
                ->has('schedules', 1),
        );
    }

    // ---------------------------------------------------------------
    // v2 — Evaluation / Offering / Outcome embedded & page surfaces
    // ---------------------------------------------------------------

    public function test_applicant_detail_embeds_scoped_evaluations_and_offers(): void
    {
        [$admin, , $vacancyId] = $this->openVacancy('frontend-v2-embed@example.test');
        $stageId = $this->createStage($admin, $vacancyId, 'Teknis', 0);
        [$candidate] = $this->candidate('frontend-v2-embed-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);

        $this->actingAs($admin)->postJson("/applications/{$applicationId}/evaluations", [
            'recruitment_stage_id' => $stageId, 'recommendation' => 'HIRE', 'total_score' => 82,
        ])->assertCreated();
        $offerId = (int) $this->actingAs($admin)->postJson("/applications/{$applicationId}/offers", ['note' => 'Selamat bergabung.'])->assertCreated()->json('data.id');
        $this->actingAs($admin)->postJson("/offers/{$offerId}/send")->assertOk();

        $this->actingAs($admin)->get("/pelamar/{$applicationId}")->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('recruiter/ApplicantDetail')
                ->has('evaluations', 1)
                ->where('evaluations.0.recommendation', 'HIRE')
                ->has('evaluations.0.evaluator_user_id')
                ->has('offers', 1)
                ->where('offers.0.status', 'SENT')
                ->has('offers.0.offered_by_user_id'),
        );

        // A different company's admin cannot see this application at all.
        [$otherAdmin] = $this->openVacancy('frontend-v2-embed-other@example.test');
        $this->actingAs($otherAdmin)->get("/pelamar/{$applicationId}")->assertStatus(404);
    }

    public function test_candidate_application_detail_shows_sent_offer_only_with_candidate_allowlist(): void
    {
        [$admin, , $vacancyId] = $this->openVacancy('frontend-v2-candoffer@example.test');
        [$candidate] = $this->candidate('frontend-v2-candoffer-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);

        $offerId = (int) $this->actingAs($admin)->postJson("/applications/{$applicationId}/offers", ['note' => 'Rahasia internal? Tidak.'])->assertCreated()->json('data.id');

        // DRAFT (not yet sent) — candidate sees nothing.
        $this->actingAs($candidate)->get("/lamaran-saya/{$applicationId}")->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('candidate/ApplicationDetail')->has('offers', 0),
        );

        $this->actingAs($admin)->postJson("/offers/{$offerId}/send")->assertOk();

        $response = $this->actingAs($candidate)->get("/lamaran-saya/{$applicationId}")->assertOk();
        $response->assertInertia(
            fn (Assert $page) => $page->component('candidate/ApplicationDetail')
                ->has('offers', 1)
                ->where('offers.0.status', 'SENT')
                ->missing('offers.0.offered_by_user_id')
                ->missing('offers.0.rejection_reason'),
        );
    }

    public function test_outcome_rekrutmen_incomplete_then_recorded(): void
    {
        [$admin, , $vacancyId] = $this->openVacancy('frontend-v2-outcome@example.test');
        [$candidate] = $this->candidate('frontend-v2-outcome-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);
        DB::table('applications')->where('id', $applicationId)->update(['current_status' => 'REJECTED']);

        $this->actingAs($admin)->get('/outcome-rekrutmen')->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('recruiter/OutcomeRekrutmen')
                ->where('can_write', true)
                ->where('incomplete.items.0.application_id', $applicationId)
                ->where('incomplete.items.0.current_status', 'REJECTED')
                ->has('incomplete.items.0.application_code')
                ->has('recorded.items', 0),
        );

        $this->actingAs($admin)->postJson('/recruitment-outcomes', [
            'source_type' => 'INTERNAL_APPLICATION', 'application_id' => $applicationId,
            'outcome' => 'REJECTED', 'reported_by_source' => 'COMPANY',
        ])->assertCreated();

        $this->actingAs($admin)->get('/outcome-rekrutmen')->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('recruiter/OutcomeRekrutmen')
                ->has('incomplete.items', 0)
                ->has('recorded.items', 1)
                ->where('recorded.items.0.outcome', 'REJECTED')
                ->where('recorded.items.0.reported_by_source', 'COMPANY'),
        );
    }

    public function test_outcome_rekrutmen_denies_candidate_with_styled_error(): void
    {
        [$candidate] = $this->candidate('frontend-v2-outcome-deny@example.test');
        $this->actingAs($candidate)->get('/outcome-rekrutmen')->assertStatus(403)
            ->assertInertia(fn (Assert $page) => $page->component('Error')->where('status', 403));
    }

    private function createStage(User $admin, int $vacancyId, string $name, int $sortOrder): int
    {
        return (int) $this->actingAs($admin)->postJson("/vacancies/{$vacancyId}/stages", [
            'name' => $name, 'stage_type' => 'GENERAL', 'sort_order' => $sortOrder, 'active' => true,
        ])->assertCreated()->json('data.id');
    }

    // ---------------------------------------------------------------
    // Fixtures (same shape as RecruitmentOutcomeTest's own private helpers)
    // ---------------------------------------------------------------

    /** @return array{0: User, 1: \App\Domains\Company\Models\Company, 2: int} */
    private function openVacancy(string $email, array $overrides = []): array
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter($email);
        $close = Carbon::parse('2026-12-01T09:00:00+00:00');
        $open = $close->copy()->subDays(20);
        $vacancyId = $this->vacancyAt($recruiter, $company, 'PENDING_REVIEW', array_merge([
            'open_at' => $open->toIso8601String(), 'close_at' => $close->toIso8601String(),
        ], $overrides));
        $approver = $this->moderator('approver-of-'.$vacancyId.'@example.test');
        $this->actingAs($approver)->postJson("/vacancies/{$vacancyId}/approve")->assertOk();
        DB::table('vacancies')->where('id', $vacancyId)->update([
            'current_status' => 'PUBLISHED', 'published_at' => $open,
        ]);
        Carbon::setTestNow($close->copy()->subDays(10));

        return [$recruiter, $company, $vacancyId];
    }

    /** @return array{0: User, 1: int} */
    private function candidate(string $email): array
    {
        $user = $this->makeUser($email, UserStatus::Active);
        $this->assignRole($user, RoleCode::CandidateExternal);
        $profileId = (int) DB::table('candidate_profiles')->insertGetId([
            'user_id' => $user->id, 'current_candidate_type' => 'EXTERNAL', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return [$user, $profileId];
    }

    private function submitApplication(User $candidate, int $vacancyId): int
    {
        return (int) $this->actingAs($candidate)->postJson("/vacancies/{$vacancyId}/applications", [
            'consent' => [
                'consent_version' => \App\Domains\Application\Support\ApplicationConsentVersion::CURRENT,
                'consent_text_hash_reference' => 'sha256:'.str_repeat('a', 64),
                'accepted' => true,
            ],
        ])->assertCreated()->json('data.id');
    }
}
