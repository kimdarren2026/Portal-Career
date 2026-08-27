<?php

declare(strict_types=1);

namespace Tests\Feature\Recruitment;

use App\Domains\Identity\Enums\RoleCode;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\Feature\Vacancy\VacancyTestCase;

/**
 * Evaluation / Scoring Foundation v1 — create, list, detail, update, submit.
 * EV-1, EV-2, RC-1 all approved and CLOSED. Item-set mutation via PATCH,
 * company-side Selector Assignment, schedule complete/no-show, offering,
 * outcome, reopen, Campus recruitment, and External Apply all remain
 * deliberately unimplemented.
 */
final class EvaluationTest extends VacancyTestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ---------------------------------------------------------------
    // Route inventory
    // ---------------------------------------------------------------

    public function test_route_inventory_matches_frozen_surface(): void
    {
        $registered = collect(Route::getRoutes())->map(
            static fn ($route): string => strtoupper(implode('|', $route->methods())).' '.$route->uri(),
        );

        self::assertTrue($registered->contains('POST applications/{application}/evaluations'));
        self::assertTrue($registered->contains('GET|HEAD applications/{application}/evaluations'));
        self::assertTrue($registered->contains('GET|HEAD evaluations/{evaluation}'));
        self::assertTrue($registered->contains('PATCH evaluations/{evaluation}'));
        self::assertTrue($registered->contains('POST evaluations/{evaluation}/submit'));

        // 'offers' and 'recruitment-outcomes' are deliberately excluded here:
        // Offering Foundation v1 (OF-1/OF-2/RC-1) and Recruitment Outcome
        // Foundation v1 (OC-1/RC-2), both approved and CLOSED, are separately
        // frozen milestones that legitimately register their own routes
        // after this one. The remaining entries are still unshipped in any
        // milestone.
        foreach (['bulk-evaluation', 'selector-assignment'] as $absent) {
            self::assertFalse(
                $registered->contains(fn (string $r): bool => str_contains($r, $absent)),
                "No route may exist for {$absent} in this milestone.",
            );
        }
        self::assertFalse($registered->contains(fn (string $r): bool => str_contains($r, 'DELETE') && str_contains($r, 'evaluation')));
        self::assertFalse($registered->contains(fn (string $r): bool => str_contains($r, 'api/v1') && str_contains($r, 'evaluation')));
    }

    // ---------------------------------------------------------------
    // Authorization
    // ---------------------------------------------------------------

    public function test_recruiter_admin_and_super_admin_can_create(): void
    {
        [$admin, $company, $vacancyId] = $this->openVacancy('ev-write-actors@example.test');
        $stage = $this->createStage($admin, $vacancyId, 'A', 0);
        $recruiter = $this->recruiterMember($company->id, 'ev-write-actors-r@example.test');
        $superAdmin = $this->moderator('ev-write-actors-sa@example.test', RoleCode::SuperAdmin);

        foreach ([$admin, $recruiter, $superAdmin] as $actor) {
            [$candidate] = $this->candidate('ev-write-actors-c-'.$actor->id.'@example.test');
            $applicationId = $this->submitApplication($candidate, $vacancyId);

            $this->actingAs($actor)->postJson("/applications/{$applicationId}/evaluations", $this->evaluationPayload($stage))
                ->assertCreated()->assertJsonPath('data.evaluator_user_id', $actor->id);
        }
    }

    public function test_revoked_membership_cannot_create(): void
    {
        [$admin, $company, $vacancyId] = $this->openVacancy('ev-revoked@example.test');
        $stage = $this->createStage($admin, $vacancyId, 'A', 0);
        [$candidate] = $this->candidate('ev-revoked-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);

        DB::table('company_members')->where('company_id', $company->id)->where('user_id', $admin->id)
            ->update(['status' => 'INACTIVE']);

        $this->actingAs($admin->fresh())->postJson("/applications/{$applicationId}/evaluations", $this->evaluationPayload($stage))
            ->assertNotFound();
    }

    public function test_cross_company_recruiter_gets_enumeration_safe_404(): void
    {
        [$admin, , $vacancyId] = $this->openVacancy('ev-cross-a@example.test');
        $stage = $this->createStage($admin, $vacancyId, 'A', 0);
        [, $companyB] = $this->verifiedCompanyWithRecruiter('ev-cross-b@example.test');
        [$candidate] = $this->candidate('ev-cross-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);
        $recruiterB = $this->recruiterMember($companyB->id, 'ev-cross-recruiter-b@example.test');

        $this->actingAs($recruiterB)->postJson("/applications/{$applicationId}/evaluations", $this->evaluationPayload($stage))
            ->assertNotFound()->assertJsonPath('error.code', 'NOT_FOUND');

        $evaluationId = $this->createEvaluation($admin, $applicationId, $stage);
        $this->actingAs($recruiterB)->getJson("/evaluations/{$evaluationId}")
            ->assertNotFound()->assertJsonPath('error.code', 'NOT_FOUND');
    }

    public function test_career_center_auditor_and_candidate_denied(): void
    {
        [$admin, , $vacancyId] = $this->openVacancy('ev-deny@example.test');
        $stage = $this->createStage($admin, $vacancyId, 'A', 0);
        [$candidate] = $this->candidate('ev-deny-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);
        $evaluationId = $this->createEvaluation($admin, $applicationId, $stage);

        $careerCenter = $this->moderator('ev-deny-cc@example.test', RoleCode::CareerCenterStaff);
        $auditor = $this->moderator('ev-deny-aud@example.test', RoleCode::Auditor);
        $selector = $this->moderator('ev-deny-sel@example.test', RoleCode::Selector);

        foreach ([$careerCenter, $auditor, $selector, $candidate] as $actor) {
            $this->actingAs($actor)->postJson("/applications/{$applicationId}/evaluations", $this->evaluationPayload($stage))
                ->assertStatus(403)->assertJsonPath('error.code', 'AUTH_FORBIDDEN');
            $this->actingAs($actor)->getJson("/applications/{$applicationId}/evaluations")
                ->assertStatus(403)->assertJsonPath('error.code', 'AUTH_FORBIDDEN');
            $this->actingAs($actor)->getJson("/evaluations/{$evaluationId}")
                ->assertStatus(403)->assertJsonPath('error.code', 'AUTH_FORBIDDEN');
            $this->actingAs($actor)->patchJson("/evaluations/{$evaluationId}", ['comments' => 'x'])
                ->assertStatus(403)->assertJsonPath('error.code', 'AUTH_FORBIDDEN');
            $this->actingAs($actor)->postJson("/evaluations/{$evaluationId}/submit", [])
                ->assertStatus(403)->assertJsonPath('error.code', 'AUTH_FORBIDDEN');
        }

        self::assertSame(1, DB::table('evaluations')->where('application_id', $applicationId)->count());
        self::assertNull(DB::table('evaluations')->where('id', $evaluationId)->value('submitted_at'));
    }

    // ---------------------------------------------------------------
    // EV-1 — terminal application
    // ---------------------------------------------------------------

    public function test_ev1_create_update_submit_denied_for_terminal_application(): void
    {
        [$admin, , $vacancyId] = $this->openVacancy('ev1@example.test');
        $stageA = $this->createStage($admin, $vacancyId, 'A', 0);

        foreach (['WITHDRAWN', 'HIRED', 'REJECTED', 'NO_SHOW'] as $status) {
            [$candidate] = $this->candidate('ev1-'.mb_strtolower($status).'@example.test');
            $applicationId = $this->submitApplication($candidate, $vacancyId);
            $evaluationId = $this->createEvaluation($admin, $applicationId, $stageA);

            $update = ['current_status' => $status];
            if ($status === 'WITHDRAWN') {
                $update['withdrawn_at'] = now();
            }
            DB::table('applications')->where('id', $applicationId)->update($update);

            $this->actingAs($admin)->postJson("/applications/{$applicationId}/evaluations", $this->evaluationPayload($stageA))
                ->assertStatus(409)->assertJsonPath('error.code', 'APPLICATION_TERMINAL');

            $this->actingAs($admin)->patchJson("/evaluations/{$evaluationId}", ['comments' => 'late edit'])
                ->assertStatus(409)->assertJsonPath('error.code', 'APPLICATION_TERMINAL');

            $this->actingAs($admin)->postJson("/evaluations/{$evaluationId}/submit", [])
                ->assertStatus(409)->assertJsonPath('error.code', 'APPLICATION_TERMINAL');

            self::assertNull(DB::table('evaluations')->where('id', $evaluationId)->value('submitted_at'));
        }
    }

    // ---------------------------------------------------------------
    // EV-2 — stage alignment
    // ---------------------------------------------------------------

    public function test_ev2_create_targets_any_active_same_vacancy_stage(): void
    {
        [$admin, , $vacancyId] = $this->openVacancy('ev2@example.test');
        $stageA = $this->createStage($admin, $vacancyId, 'A', 0);
        $stageC = $this->createStage($admin, $vacancyId, 'C', 2);
        [$candidate] = $this->candidate('ev2-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);
        DB::table('applications')->where('id', $applicationId)->update(['current_stage_id' => $stageA]);

        $this->actingAs($admin)->postJson("/applications/{$applicationId}/evaluations", $this->evaluationPayload($stageC))
            ->assertCreated();

        self::assertSame($stageA, DB::table('applications')->where('id', $applicationId)->value('current_stage_id'));
    }

    public function test_wrong_vacancy_stage_rejected(): void
    {
        [$admin, , $vacancyId] = $this->openVacancy('ev2-wrong-vacancy@example.test');
        [$otherAdmin, , $otherVacancyId] = $this->openVacancy('ev2-wrong-vacancy-other@example.test');
        $foreignStage = $this->createStage($otherAdmin, $otherVacancyId, 'Foreign', 0);
        [$candidate] = $this->candidate('ev2-wrong-vacancy-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);

        $this->actingAs($admin)->postJson("/applications/{$applicationId}/evaluations", $this->evaluationPayload($foreignStage))
            ->assertStatus(422)->assertJsonPath('error.code', 'STAGE_NOT_IN_VACANCY');
    }

    public function test_ev2_inactive_target_denied_for_create_and_update_but_submit_allowed(): void
    {
        [$admin, , $vacancyId] = $this->openVacancy('ev2-inactive@example.test');
        $stageA = $this->createStage($admin, $vacancyId, 'A', 0);
        $stageB = $this->createStage($admin, $vacancyId, 'B', 1);
        [$candidate] = $this->candidate('ev2-inactive-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);

        // Draft created while stage active.
        $evaluationId = $this->createEvaluation($admin, $applicationId, $stageA);

        $this->actingAs($admin)->patchJson("/vacancies/{$vacancyId}/stages/{$stageA}", ['active' => false])->assertOk();

        // CREATE against the now-inactive stage: DENY.
        $this->actingAs($admin)->postJson("/applications/{$applicationId}/evaluations", $this->evaluationPayload($stageA))
            ->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_FAILED');

        // UPDATE trying to move to the inactive stage: DENY.
        $this->actingAs($admin)->patchJson("/evaluations/{$evaluationId}", ['recruitment_stage_id' => $stageA])
            ->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_FAILED');

        // SUBMIT of the existing draft, whose stage is now inactive: ALLOW.
        $this->actingAs($admin)->postJson("/evaluations/{$evaluationId}/submit", [])
            ->assertOk()->assertJsonPath('data.recruitment_stage_id', $stageA);

        self::assertNotNull(DB::table('evaluations')->where('id', $evaluationId)->value('submitted_at'));
    }

    // ---------------------------------------------------------------
    // RA-2 (RC-1)
    // ---------------------------------------------------------------

    public function test_ra2_create_update_submit_gated_super_admin_no_bypass(): void
    {
        [$admin, $company, $vacancyId] = $this->openVacancy('ev-ra2@example.test');
        $stage = $this->createStage($admin, $vacancyId, 'A', 0);
        [$candidate] = $this->candidate('ev-ra2-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);
        $evaluationId = $this->createEvaluation($admin, $applicationId, $stage);

        // Super Admin's own evaluation, created while the vacancy is still
        // processable, isolates the RA-2 submit assertion below from the
        // author-only rule (covered separately by test_author_only_update_and_submit).
        [$candidateForSuper] = $this->candidate('ev-ra2-super-admin-c@example.test');
        $applicationForSuper = $this->submitApplication($candidateForSuper, $vacancyId);
        $superAdmin = $this->moderator('ev-ra2-super-admin@example.test', RoleCode::SuperAdmin);
        $superEvaluationId = (int) $this->actingAs($superAdmin)->postJson("/applications/{$applicationForSuper}/evaluations", $this->evaluationPayload($stage))
            ->assertCreated()->json('data.id');

        DB::table('vacancies')->where('id', $vacancyId)->update(['current_status' => 'SUSPENDED']);
        $this->actingAs($admin)->postJson("/applications/{$applicationId}/evaluations", $this->evaluationPayload($stage))
            ->assertStatus(409)->assertJsonPath('error.code', 'APPLICATION_VACANCY_NOT_PROCESSABLE');
        $this->actingAs($admin)->patchJson("/evaluations/{$evaluationId}", ['comments' => 'x'])
            ->assertStatus(409)->assertJsonPath('error.code', 'APPLICATION_VACANCY_NOT_PROCESSABLE');
        $this->actingAs($admin)->postJson("/evaluations/{$evaluationId}/submit", [])
            ->assertStatus(409)->assertJsonPath('error.code', 'APPLICATION_VACANCY_NOT_PROCESSABLE');
        $this->actingAs($superAdmin)->postJson("/evaluations/{$superEvaluationId}/submit", [])
            ->assertStatus(409)->assertJsonPath('error.code', 'APPLICATION_VACANCY_NOT_PROCESSABLE');

        DB::table('vacancies')->where('id', $vacancyId)->update(['current_status' => 'PUBLISHED']);
        DB::table('companies')->where('id', $company->id)->update(['verification_status' => 'SUSPENDED']);
        $this->actingAs($admin)->postJson("/applications/{$applicationId}/evaluations", $this->evaluationPayload($stage))
            ->assertStatus(403)->assertJsonPath('error.code', 'VACANCY_COMPANY_NOT_VERIFIED');
    }

    // ---------------------------------------------------------------
    // Scoring — no formula
    // ---------------------------------------------------------------

    public function test_no_automatic_scoring_formula_and_open_recommendation(): void
    {
        [$admin, , $vacancyId] = $this->openVacancy('ev-scoring@example.test');
        $stage = $this->createStage($admin, $vacancyId, 'A', 0);
        [$candidate] = $this->candidate('ev-scoring-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);

        $response = $this->actingAs($admin)->postJson("/applications/{$applicationId}/evaluations", [
            'recruitment_stage_id' => $stage,
            'recommendation' => 'STRONG_HIRE',
            'total_score' => null,
            'items' => [
                ['criterion' => 'Technical', 'weight' => 70, 'score' => 80, 'sort_order' => 0],
                ['criterion' => 'Communication', 'weight' => 30, 'score' => 60, 'sort_order' => 1],
            ],
        ])->assertCreated();

        // total_score persisted exactly as supplied (null) — never derived from items.
        self::assertNull($response->json('data.total_score'));
        self::assertNull(DB::table('evaluations')->where('id', $response->json('data.id'))->value('total_score'));
        self::assertSame('STRONG_HIRE', $response->json('data.recommendation'));
        self::assertCount(2, $response->json('data.items'));
    }

    public function test_create_without_any_scores_is_accepted(): void
    {
        [$admin, , $vacancyId] = $this->openVacancy('ev-no-scores@example.test');
        $stage = $this->createStage($admin, $vacancyId, 'A', 0);
        [$candidate] = $this->candidate('ev-no-scores-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);

        $this->actingAs($admin)->postJson("/applications/{$applicationId}/evaluations", [
            'recruitment_stage_id' => $stage,
            'comments' => 'Comments only, no numbers.',
        ])->assertCreated()->assertJsonPath('data.total_score', null);
    }

    // ---------------------------------------------------------------
    // Author-only
    // ---------------------------------------------------------------

    public function test_author_only_update_and_submit(): void
    {
        [$admin, $company, $vacancyId] = $this->openVacancy('ev-author@example.test');
        $stage = $this->createStage($admin, $vacancyId, 'A', 0);
        $recruiterB = $this->recruiterMember($company->id, 'ev-author-b@example.test');
        [$candidate] = $this->candidate('ev-author-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);
        $evaluationId = $this->createEvaluation($admin, $applicationId, $stage);

        // Different company member: read succeeds, write is denied.
        $this->actingAs($recruiterB)->getJson("/evaluations/{$evaluationId}")->assertOk();
        $this->actingAs($recruiterB)->patchJson("/evaluations/{$evaluationId}", ['comments' => 'x'])
            ->assertStatus(403)->assertJsonPath('error.code', 'EVALUATION_NOT_OWNED');
        $this->actingAs($recruiterB)->postJson("/evaluations/{$evaluationId}/submit", [])
            ->assertStatus(403)->assertJsonPath('error.code', 'EVALUATION_NOT_OWNED');

        // Super Admin does not proxy-edit or proxy-submit another author's evaluation either.
        $superAdmin = $this->moderator('ev-author-super-admin@example.test', RoleCode::SuperAdmin);
        $this->actingAs($superAdmin)->getJson("/evaluations/{$evaluationId}")->assertOk();
        $this->actingAs($superAdmin)->patchJson("/evaluations/{$evaluationId}", ['comments' => 'x'])
            ->assertStatus(403)->assertJsonPath('error.code', 'EVALUATION_NOT_OWNED');
        $this->actingAs($superAdmin)->postJson("/evaluations/{$evaluationId}/submit", [])
            ->assertStatus(403)->assertJsonPath('error.code', 'EVALUATION_NOT_OWNED');

        // The actual author succeeds.
        $this->actingAs($admin)->patchJson("/evaluations/{$evaluationId}", ['comments' => 'author edit'])->assertOk();
    }

    // ---------------------------------------------------------------
    // Update lifecycle
    // ---------------------------------------------------------------

    public function test_update_rejected_after_submission(): void
    {
        [$admin, , $vacancyId] = $this->openVacancy('ev-post-submit@example.test');
        $stage = $this->createStage($admin, $vacancyId, 'A', 0);
        [$candidate] = $this->candidate('ev-post-submit-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);
        $evaluationId = $this->createEvaluation($admin, $applicationId, $stage);

        $this->actingAs($admin)->postJson("/evaluations/{$evaluationId}/submit", [])->assertOk();

        $this->actingAs($admin)->patchJson("/evaluations/{$evaluationId}", ['comments' => 'too late'])
            ->assertStatus(409)->assertJsonPath('error.code', 'EVALUATION_ALREADY_SUBMITTED');
    }

    // ---------------------------------------------------------------
    // Submit
    // ---------------------------------------------------------------

    public function test_submit_success_full_side_effects(): void
    {
        [$admin, $company, $vacancyId] = $this->openVacancy('ev-submit@example.test');
        $stage = $this->createStage($admin, $vacancyId, 'A', 0);
        [$candidate] = $this->candidate('ev-submit-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);
        $evaluationId = $this->createEvaluation($admin, $applicationId, $stage);

        $response = $this->actingAs($admin)->postJson("/evaluations/{$evaluationId}/submit", [])->assertOk();
        self::assertNotNull($response->json('data.submitted_at'));

        self::assertSame(1, DB::table('audit_logs')->where('action', 'evaluation_submitted')->where('object_id', $evaluationId)->count());
        self::assertSame(1, DB::table('notifications')->where('related_object_type', 'evaluation')->where('related_object_id', $evaluationId)
            ->where('user_id', $admin->id)->where('body_reference', 'evaluation.submitted.owner')->count());
        self::assertSame(1, DB::table('email_outbox')->where('related_object_type', 'evaluation')->where('related_object_id', $evaluationId)
            ->where('template_reference', 'evaluation.submitted.owner')->count());

        // Never the candidate.
        self::assertSame(0, DB::table('notifications')->where('related_object_type', 'evaluation')->where('related_object_id', $evaluationId)
            ->where('user_id', $candidate->id ?? 0)->count());

        // Status independence.
        self::assertSame('APPLIED', DB::table('applications')->where('id', $applicationId)->value('current_status'));
        self::assertNull(DB::table('applications')->where('id', $applicationId)->value('current_stage_id'));
    }

    public function test_submit_rejected_when_already_submitted(): void
    {
        [$admin, , $vacancyId] = $this->openVacancy('ev-double-submit@example.test');
        $stage = $this->createStage($admin, $vacancyId, 'A', 0);
        [$candidate] = $this->candidate('ev-double-submit-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);
        $evaluationId = $this->createEvaluation($admin, $applicationId, $stage);

        $this->actingAs($admin)->postJson("/evaluations/{$evaluationId}/submit", [])->assertOk();

        $this->actingAs($admin)->postJson("/evaluations/{$evaluationId}/submit", [])
            ->assertStatus(409)->assertJsonPath('error.code', 'EVALUATION_ALREADY_SUBMITTED');
    }

    public function test_submit_idempotency_replay(): void
    {
        [$admin, , $vacancyId] = $this->openVacancy('ev-idem-submit@example.test');
        $stage = $this->createStage($admin, $vacancyId, 'A', 0);
        [$candidate] = $this->candidate('ev-idem-submit-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);
        $evaluationId = $this->createEvaluation($admin, $applicationId, $stage);
        $key = 'ev-submit-'.uniqid('', true);

        $first = $this->actingAs($admin)->withHeaders(['Idempotency-Key' => $key])
            ->postJson("/evaluations/{$evaluationId}/submit", [])->assertOk();
        $replay = $this->actingAs($admin)->withHeaders(['Idempotency-Key' => $key])
            ->postJson("/evaluations/{$evaluationId}/submit", [])->assertOk();

        self::assertEquals($first->json('data'), $replay->json('data'));
        self::assertSame(1, DB::table('audit_logs')->where('action', 'evaluation_submitted')->where('object_id', $evaluationId)->count());
        self::assertSame(1, DB::table('notifications')->where('related_object_type', 'evaluation')->where('related_object_id', $evaluationId)->count());
    }

    // ---------------------------------------------------------------
    // Stage Authoring / Movement boundary
    // ---------------------------------------------------------------

    public function test_stage_reorder_and_rename_never_affect_evaluations(): void
    {
        [$admin, , $vacancyId] = $this->openVacancy('ev-stage-boundary@example.test');
        $stageA = $this->createStage($admin, $vacancyId, 'A', 0);
        $stageB = $this->createStage($admin, $vacancyId, 'B', 1);
        [$candidate] = $this->candidate('ev-stage-boundary-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);
        $evaluationId = $this->createEvaluation($admin, $applicationId, $stageA);

        $this->actingAs($admin)->patchJson("/vacancies/{$vacancyId}/stages/{$stageA}", ['name' => 'Renamed'])->assertOk();
        $this->actingAs($admin)->postJson("/vacancies/{$vacancyId}/stages/reorder", ['stage_ids' => [$stageB, $stageA]])->assertOk();

        self::assertSame($stageA, DB::table('evaluations')->where('id', $evaluationId)->value('recruitment_stage_id'));
    }

    // ---------------------------------------------------------------
    // Fixtures
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
        $approver = $this->moderator('approver-ev-'.$vacancyId.'@example.test');
        $this->actingAs($approver)->postJson("/vacancies/{$vacancyId}/approve")->assertOk();
        DB::table('vacancies')->where('id', $vacancyId)->update([
            'current_status' => 'PUBLISHED', 'published_at' => $open,
        ]);
        Carbon::setTestNow($close->copy()->subDays(10));

        return [$recruiter, $company, $vacancyId];
    }

    private function recruiterMember(int $companyId, string $email): User
    {
        $user = $this->makeUser($email, UserStatus::Active);
        $this->assignRole($user, RoleCode::CompanyRecruiter);
        $this->addMember($companyId, $user, 'COMPANY_RECRUITER');

        return $user->fresh();
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

    private function createStage(User $admin, int $vacancyId, string $name, int $sortOrder, bool $active = true): int
    {
        return (int) $this->actingAs($admin)->postJson("/vacancies/{$vacancyId}/stages", [
            'name' => $name, 'stage_type' => 'GENERAL', 'sort_order' => $sortOrder, 'active' => $active,
        ])->assertCreated()->json('data.id');
    }

    private function createEvaluation(User $admin, int $applicationId, int $stageId): int
    {
        return (int) $this->actingAs($admin)->postJson("/applications/{$applicationId}/evaluations", $this->evaluationPayload($stageId))
            ->assertCreated()->json('data.id');
    }

    /** @return array<string, mixed> */
    private function evaluationPayload(int $stageId, array $overrides = []): array
    {
        return array_merge([
            'recruitment_stage_id' => $stageId,
            'recommendation' => 'HIRE',
            'comments' => 'Solid technical performance.',
            'total_score' => 82.5,
        ], $overrides);
    }
}
