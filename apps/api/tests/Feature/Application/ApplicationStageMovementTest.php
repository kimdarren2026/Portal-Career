<?php

declare(strict_types=1);

namespace Tests\Feature\Application;

use App\Domains\Identity\Enums\RoleCode;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\Feature\Vacancy\VacancyTestCase;

/**
 * Application Stage Movement Foundation v1 — `POST /applications/{application}/move-stage`.
 * MS-3 (same-stage rejection) and MS-4 (arbitrary same-vacancy movement),
 * both approved and CLOSED. Selector assignment, bulk-transition, new RA-1
 * edges, evaluation, schedules, offers, outcome, reopen, and Campus
 * recruitment remain deliberately unimplemented.
 */
final class ApplicationStageMovementTest extends VacancyTestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ---------------------------------------------------------------
    // Route
    // ---------------------------------------------------------------

    public function test_move_stage_route_exists_bulk_and_reopen_remain_absent(): void
    {
        $registered = collect(Route::getRoutes())->map(
            static fn ($route): string => strtoupper(implode('|', $route->methods())).' '.$route->uri(),
        );

        self::assertTrue($registered->contains('POST applications/{application}/move-stage'));

        // application-documents download is now routed (PGC-V1 / PD-A, supersedes
        // RA-3 for the download operation — API_CONTRACT.md Part X items 22 / 62).
        self::assertTrue($registered->contains('GET|HEAD application-documents/{applicationDocument}/download'));

        foreach (['bulk-transition', 'reopen'] as $absent) {
            self::assertFalse(
                $registered->contains(fn (string $r): bool => str_contains($r, $absent)),
                "No route may exist for {$absent} in this milestone.",
            );
        }
    }

    // ---------------------------------------------------------------
    // Happy path
    // ---------------------------------------------------------------

    public function test_recruiter_admin_and_super_admin_can_move_between_active_stages(): void
    {
        [$admin, $company, $vacancyId] = $this->openVacancy('ms-actors@example.test');
        $stageA = $this->createStage($admin, $vacancyId, 'Stage A', 0);
        $stageB = $this->createStage($admin, $vacancyId, 'Stage B', 1);
        $recruiter = $this->recruiterMember($company->id, 'ms-actors-recruiter@example.test');
        $superAdmin = $this->moderator('ms-actors-super@example.test', RoleCode::SuperAdmin);

        foreach ([$admin, $recruiter, $superAdmin] as $actor) {
            [$candidate] = $this->candidate('ms-actors-c-'.$actor->id.'@example.test');
            $applicationId = $this->submitApplication($candidate, $vacancyId);
            DB::table('applications')->where('id', $applicationId)->update(['current_stage_id' => $stageA]);

            $this->actingAs($actor)->postJson("/applications/{$applicationId}/move-stage", [
                'to_stage_id' => $stageB, 'candidate_visibility' => 'INTERNAL',
            ])->assertOk()->assertJsonPath('data.current_stage_id', $stageB);
        }
    }

    public function test_null_current_stage_moves_to_first_stage_with_null_from_stage_id(): void
    {
        [$admin, , $vacancyId] = $this->openVacancy('ms-null@example.test');
        $stage = $this->createStage($admin, $vacancyId, 'First Stage', 0);
        [$candidate] = $this->candidate('ms-null-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);
        self::assertNull(DB::table('applications')->where('id', $applicationId)->value('current_stage_id'));

        $this->actingAs($admin)->postJson("/applications/{$applicationId}/move-stage", [
            'to_stage_id' => $stage, 'candidate_visibility' => 'INTERNAL',
        ])->assertOk()->assertJsonPath('data.current_stage_id', $stage);

        $event = DB::table('application_status_histories')->where('application_id', $applicationId)
            ->where('event_type', 'STAGE_CHANGED')->first();
        self::assertNull($event->from_stage_id);
        self::assertSame($stage, (int) $event->to_stage_id);
    }

    public function test_backward_and_skipped_movement_are_both_allowed(): void
    {
        [$admin, , $vacancyId] = $this->openVacancy('ms-adjacency@example.test');
        $stageA = $this->createStage($admin, $vacancyId, 'A', 0);
        $stageB = $this->createStage($admin, $vacancyId, 'B', 1);
        $stageC = $this->createStage($admin, $vacancyId, 'C', 2);
        [$candidate] = $this->candidate('ms-adjacency-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);
        DB::table('applications')->where('id', $applicationId)->update(['current_stage_id' => $stageA]);

        // Skip: A -> C.
        $this->actingAs($admin)->postJson("/applications/{$applicationId}/move-stage", [
            'to_stage_id' => $stageC, 'candidate_visibility' => 'INTERNAL',
        ])->assertOk()->assertJsonPath('data.current_stage_id', $stageC);

        // Backward: C -> B.
        $this->actingAs($admin)->postJson("/applications/{$applicationId}/move-stage", [
            'to_stage_id' => $stageB, 'candidate_visibility' => 'INTERNAL',
        ])->assertOk()->assertJsonPath('data.current_stage_id', $stageB);

        self::assertSame(
            [$stageC, $stageB],
            DB::table('application_status_histories')->where('application_id', $applicationId)
                ->where('event_type', 'STAGE_CHANGED')->orderBy('id')
                ->pluck('to_stage_id')->map(static fn ($v) => $v === null ? null : (int) $v)->all(),
        );
    }

    // ---------------------------------------------------------------
    // Validation
    // ---------------------------------------------------------------

    public function test_inactive_target_is_rejected(): void
    {
        [$admin, , $vacancyId] = $this->openVacancy('ms-inactive@example.test');
        $stageA = $this->createStage($admin, $vacancyId, 'Active', 0);
        $stageB = $this->createStage($admin, $vacancyId, 'Disabled', 1, false);
        [$candidate] = $this->candidate('ms-inactive-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);
        DB::table('applications')->where('id', $applicationId)->update(['current_stage_id' => $stageA]);

        $this->actingAs($admin)->postJson("/applications/{$applicationId}/move-stage", [
            'to_stage_id' => $stageB, 'candidate_visibility' => 'INTERNAL',
        ])->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_FAILED');

        self::assertSame($stageA, DB::table('applications')->where('id', $applicationId)->value('current_stage_id'));
    }

    public function test_moving_away_from_a_disabled_current_stage_is_allowed(): void
    {
        [$admin, , $vacancyId] = $this->openVacancy('ms-away-disabled@example.test');
        $stageA = $this->createStage($admin, $vacancyId, 'A', 0);
        $stageB = $this->createStage($admin, $vacancyId, 'B', 1);
        [$candidate] = $this->candidate('ms-away-disabled-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);
        DB::table('applications')->where('id', $applicationId)->update(['current_stage_id' => $stageA]);
        $this->actingAs($admin)->patchJson("/vacancies/{$vacancyId}/stages/{$stageA}", ['active' => false])->assertOk();

        $this->actingAs($admin)->postJson("/applications/{$applicationId}/move-stage", [
            'to_stage_id' => $stageB, 'candidate_visibility' => 'INTERNAL',
        ])->assertOk()->assertJsonPath('data.current_stage_id', $stageB);
    }

    public function test_different_vacancy_target_is_rejected_enumeration_safe(): void
    {
        [$admin, $company, $vacancyId] = $this->openVacancy('ms-wrong-vacancy@example.test');
        $ownStage = $this->createStage($admin, $vacancyId, 'Own', 0);
        [$otherAdmin, $otherCompany, $otherVacancyId] = $this->openVacancy('ms-wrong-vacancy-other@example.test');
        $foreignStage = $this->createStage($otherAdmin, $otherVacancyId, 'Foreign', 0);

        [$candidate] = $this->candidate('ms-wrong-vacancy-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);
        DB::table('applications')->where('id', $applicationId)->update(['current_stage_id' => $ownStage]);

        $this->actingAs($admin)->postJson("/applications/{$applicationId}/move-stage", [
            'to_stage_id' => $foreignStage, 'candidate_visibility' => 'INTERNAL',
        ])->assertStatus(422)->assertJsonPath('error.code', 'STAGE_NOT_IN_VACANCY');

        // A nonexistent stage id gets the same, enumeration-safe outcome.
        $this->actingAs($admin)->postJson("/applications/{$applicationId}/move-stage", [
            'to_stage_id' => 999999999, 'candidate_visibility' => 'INTERNAL',
        ])->assertStatus(422)->assertJsonPath('error.code', 'STAGE_NOT_IN_VACANCY');
    }

    public function test_same_stage_target_rejected_with_zero_side_effects(): void
    {
        [$admin, , $vacancyId] = $this->openVacancy('ms-same-stage@example.test');
        $stage = $this->createStage($admin, $vacancyId, 'A', 0);
        [$candidate] = $this->candidate('ms-same-stage-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);
        DB::table('applications')->where('id', $applicationId)->update(['current_stage_id' => $stage]);

        $historyBefore = DB::table('application_status_histories')->where('application_id', $applicationId)->count();
        $auditBefore = DB::table('audit_logs')->where('action', 'application_stage_changed')->where('object_id', $applicationId)->count();
        $notifBefore = DB::table('notifications')->count();

        $this->actingAs($admin)->postJson("/applications/{$applicationId}/move-stage", [
            'to_stage_id' => $stage, 'candidate_visibility' => 'VISIBLE',
        ])->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_FAILED');

        self::assertSame($stage, DB::table('applications')->where('id', $applicationId)->value('current_stage_id'));
        self::assertSame($historyBefore, DB::table('application_status_histories')->where('application_id', $applicationId)->count());
        self::assertSame($auditBefore, DB::table('audit_logs')->where('action', 'application_stage_changed')->where('object_id', $applicationId)->count());
        self::assertSame($notifBefore, DB::table('notifications')->count());
    }

    public function test_missing_to_stage_id_is_rejected(): void
    {
        [$admin, , $vacancyId] = $this->openVacancy('ms-missing-field@example.test');
        [$candidate] = $this->candidate('ms-missing-field-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);

        $this->actingAs($admin)->postJson("/applications/{$applicationId}/move-stage", [
            'candidate_visibility' => 'INTERNAL',
        ])->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    public function test_invalid_candidate_visibility_is_rejected(): void
    {
        [$admin, , $vacancyId] = $this->openVacancy('ms-invalid-visibility@example.test');
        $stage = $this->createStage($admin, $vacancyId, 'A', 0);
        [$candidate] = $this->candidate('ms-invalid-visibility-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);

        $this->actingAs($admin)->postJson("/applications/{$applicationId}/move-stage", [
            'to_stage_id' => $stage, 'candidate_visibility' => 'PUBLIC',
        ])->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    // ---------------------------------------------------------------
    // Eligibility — terminal applications
    // ---------------------------------------------------------------

    public function test_each_terminal_status_blocks_movement(): void
    {
        [$admin, , $vacancyId] = $this->openVacancy('ms-terminal@example.test');
        $stageA = $this->createStage($admin, $vacancyId, 'A', 0);
        $stageB = $this->createStage($admin, $vacancyId, 'B', 1);

        foreach (['WITHDRAWN', 'HIRED', 'REJECTED', 'NO_SHOW'] as $status) {
            [$candidate] = $this->candidate('ms-terminal-'.mb_strtolower($status).'@example.test');
            $applicationId = $this->submitApplication($candidate, $vacancyId);
            $update = ['current_stage_id' => $stageA, 'current_status' => $status];
            if ($status === 'WITHDRAWN') {
                $update['withdrawn_at'] = now();
            }
            DB::table('applications')->where('id', $applicationId)->update($update);

            $historyBefore = DB::table('application_status_histories')->where('application_id', $applicationId)->count();

            $this->actingAs($admin)->postJson("/applications/{$applicationId}/move-stage", [
                'to_stage_id' => $stageB, 'candidate_visibility' => 'INTERNAL',
            ])->assertStatus(409)->assertJsonPath('error.code', 'APPLICATION_TERMINAL');

            self::assertSame($stageA, DB::table('applications')->where('id', $applicationId)->value('current_stage_id'));
            self::assertSame($historyBefore, DB::table('application_status_histories')->where('application_id', $applicationId)->count());
        }
    }

    // ---------------------------------------------------------------
    // Authorization
    // ---------------------------------------------------------------

    public function test_career_center_selector_auditor_and_candidate_are_denied(): void
    {
        [$admin, , $vacancyId] = $this->openVacancy('ms-deny@example.test');
        $stage = $this->createStage($admin, $vacancyId, 'A', 0);
        [$candidate] = $this->candidate('ms-deny-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);

        $careerCenter = $this->moderator('ms-deny-cc@example.test', RoleCode::CareerCenterStaff);
        $selector = $this->moderator('ms-deny-selector@example.test', RoleCode::Selector);
        $auditor = $this->moderator('ms-deny-auditor@example.test', RoleCode::Auditor);

        foreach ([$careerCenter, $selector, $auditor, $candidate] as $actor) {
            $this->actingAs($actor)->postJson("/applications/{$applicationId}/move-stage", [
                'to_stage_id' => $stage, 'candidate_visibility' => 'INTERNAL',
            ])->assertStatus(403)->assertJsonPath('error.code', 'AUTH_FORBIDDEN');
        }

        self::assertNull(DB::table('applications')->where('id', $applicationId)->value('current_stage_id'));
    }

    public function test_cross_company_recruiter_receives_enumeration_safe_not_found(): void
    {
        [$admin, , $vacancyId] = $this->openVacancy('ms-cross-a@example.test');
        $stage = $this->createStage($admin, $vacancyId, 'A', 0);
        [, $companyB] = $this->verifiedCompanyWithRecruiter('ms-cross-b@example.test');
        [$candidate] = $this->candidate('ms-cross-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);

        $recruiterB = $this->recruiterMember($companyB->id, 'ms-cross-recruiter-b@example.test');

        $this->actingAs($recruiterB)->postJson("/applications/{$applicationId}/move-stage", [
            'to_stage_id' => $stage, 'candidate_visibility' => 'INTERNAL',
        ])->assertNotFound()->assertJsonPath('error.code', 'NOT_FOUND');
    }

    // ---------------------------------------------------------------
    // RA-2
    // ---------------------------------------------------------------

    public function test_unverified_company_blocks_movement(): void
    {
        [$admin, $company, $vacancyId] = $this->openVacancy('ms-ra2-company@example.test');
        $stage = $this->createStage($admin, $vacancyId, 'A', 0);
        [$candidate] = $this->candidate('ms-ra2-company-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);

        DB::table('companies')->where('id', $company->id)->update(['verification_status' => 'SUSPENDED']);

        $this->actingAs($admin)->postJson("/applications/{$applicationId}/move-stage", [
            'to_stage_id' => $stage, 'candidate_visibility' => 'INTERNAL',
        ])->assertStatus(403)->assertJsonPath('error.code', 'VACANCY_COMPANY_NOT_VERIFIED');

        self::assertNull(DB::table('applications')->where('id', $applicationId)->value('current_stage_id'));
    }

    public function test_suspended_vacancy_blocks_movement(): void
    {
        [$admin, , $vacancyId] = $this->openVacancy('ms-ra2-vacancy@example.test');
        $stage = $this->createStage($admin, $vacancyId, 'A', 0);
        [$candidate] = $this->candidate('ms-ra2-vacancy-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);

        DB::table('vacancies')->where('id', $vacancyId)->update(['current_status' => 'SUSPENDED']);

        $this->actingAs($admin)->postJson("/applications/{$applicationId}/move-stage", [
            'to_stage_id' => $stage, 'candidate_visibility' => 'INTERNAL',
        ])->assertStatus(409)->assertJsonPath('error.code', 'APPLICATION_VACANCY_NOT_PROCESSABLE');
    }

    public function test_super_admin_does_not_bypass_ra2(): void
    {
        [$admin, , $vacancyId] = $this->openVacancy('ms-ra2-super@example.test');
        $stage = $this->createStage($admin, $vacancyId, 'A', 0);
        [$candidate] = $this->candidate('ms-ra2-super-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);
        DB::table('vacancies')->where('id', $vacancyId)->update(['current_status' => 'SUSPENDED']);

        $superAdmin = $this->moderator('ms-ra2-super-admin@example.test', RoleCode::SuperAdmin);
        $this->actingAs($superAdmin)->postJson("/applications/{$applicationId}/move-stage", [
            'to_stage_id' => $stage, 'candidate_visibility' => 'INTERNAL',
        ])->assertStatus(409)->assertJsonPath('error.code', 'APPLICATION_VACANCY_NOT_PROCESSABLE');
    }

    // ---------------------------------------------------------------
    // Status independence
    // ---------------------------------------------------------------

    public function test_move_changes_stage_but_never_status(): void
    {
        [$admin, , $vacancyId] = $this->openVacancy('ms-status-independent@example.test');
        $stage = $this->createStage($admin, $vacancyId, 'A', 0);
        [$candidate] = $this->candidate('ms-status-independent-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);

        $this->actingAs($admin)->postJson("/applications/{$applicationId}/move-stage", [
            'to_stage_id' => $stage, 'candidate_visibility' => 'INTERNAL',
        ])->assertOk();

        self::assertSame($stage, DB::table('applications')->where('id', $applicationId)->value('current_stage_id'));
        self::assertSame('APPLIED', DB::table('applications')->where('id', $applicationId)->value('current_status'));
    }

    // ---------------------------------------------------------------
    // History
    // ---------------------------------------------------------------

    public function test_history_row_carries_the_expected_fields(): void
    {
        [$admin, , $vacancyId] = $this->openVacancy('ms-history@example.test');
        $stageA = $this->createStage($admin, $vacancyId, 'A', 0);
        $stageB = $this->createStage($admin, $vacancyId, 'B', 1);
        [$candidate] = $this->candidate('ms-history-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);
        DB::table('applications')->where('id', $applicationId)->update(['current_stage_id' => $stageA]);

        $this->actingAs($admin)->postJson("/applications/{$applicationId}/move-stage", [
            'to_stage_id' => $stageB, 'candidate_visibility' => 'VISIBLE', 'reason' => 'Lulus wawancara awal.',
        ])->assertOk();

        $event = DB::table('application_status_histories')->where('application_id', $applicationId)
            ->where('event_type', 'STAGE_CHANGED')->first();

        self::assertSame($stageA, (int) $event->from_stage_id);
        self::assertSame($stageB, (int) $event->to_stage_id);
        self::assertNull($event->from_status);
        self::assertNull($event->to_status);
        self::assertNull($event->candidate_visible_note);
        self::assertSame($admin->id, (int) $event->actor_user_id);
        self::assertSame('Lulus wawancara awal.', $event->reason);
        self::assertSame('VISIBLE', $event->candidate_visibility);

        self::assertSame(1, DB::table('audit_logs')->where('action', 'application_stage_changed')->where('object_id', $applicationId)->count());
    }

    // ---------------------------------------------------------------
    // Candidate history presentation (candidate-visible STAGE_CHANGED)
    // ---------------------------------------------------------------

    public function test_candidate_history_visible_stage_change_carries_candidate_visible_label_never_internal_name(): void
    {
        [$admin, , $vacancyId] = $this->openVacancy('ms-cand-hist@example.test');
        $stageA = $this->createStage($admin, $vacancyId, 'A', 0);
        $stageB = $this->createStage($admin, $vacancyId, 'MS-INTERNAL-STAGE-NAME-ZZ', 1, true, 'Wawancara');
        [$candidate] = $this->candidate('ms-cand-hist-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);
        DB::table('applications')->where('id', $applicationId)->update(['current_stage_id' => $stageA]);

        $this->actingAs($admin)->postJson("/applications/{$applicationId}/move-stage", [
            'to_stage_id' => $stageB, 'candidate_visibility' => 'VISIBLE', 'reason' => 'Catatan internal rahasia.',
        ])->assertOk();

        $detail = $this->actingAs($candidate)->getJson("/applications/{$applicationId}?include=history")->assertOk();

        $stageEvents = collect($detail->json('data.history'))->where('event_type', 'STAGE_CHANGED')->values();
        self::assertCount(1, $stageEvents);
        self::assertSame('Wawancara', $stageEvents[0]['stage_label']);
        self::assertNull($stageEvents[0]['to_status']);
        self::assertNull($stageEvents[0]['from_status']);

        $body = $detail->getContent();
        self::assertStringNotContainsString('MS-INTERNAL-STAGE-NAME-ZZ', $body);
        self::assertStringNotContainsString('Catatan internal rahasia.', $body);
        self::assertStringNotContainsString('actor_user_id', $body);
    }

    public function test_candidate_history_internal_stage_change_is_absent(): void
    {
        [$admin, , $vacancyId] = $this->openVacancy('ms-cand-hist-int@example.test');
        $stageA = $this->createStage($admin, $vacancyId, 'A', 0);
        $stageB = $this->createStage($admin, $vacancyId, 'B', 1, true, 'Wawancara');
        [$candidate] = $this->candidate('ms-cand-hist-int-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);
        DB::table('applications')->where('id', $applicationId)->update(['current_stage_id' => $stageA]);

        $this->actingAs($admin)->postJson("/applications/{$applicationId}/move-stage", [
            'to_stage_id' => $stageB, 'candidate_visibility' => 'INTERNAL',
        ])->assertOk();

        $detail = $this->actingAs($candidate)->getJson("/applications/{$applicationId}?include=history")->assertOk();

        self::assertEmpty(collect($detail->json('data.history'))->where('event_type', 'STAGE_CHANGED')->all());
    }

    public function test_candidate_history_visible_stage_change_without_label_has_null_stage_label(): void
    {
        [$admin, , $vacancyId] = $this->openVacancy('ms-cand-hist-nolabel@example.test');
        $stageA = $this->createStage($admin, $vacancyId, 'A', 0);
        $stageB = $this->createStage($admin, $vacancyId, 'MS-INTERNAL-NOLABEL-ZZ', 1);
        [$candidate] = $this->candidate('ms-cand-hist-nolabel-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);
        DB::table('applications')->where('id', $applicationId)->update(['current_stage_id' => $stageA]);

        $this->actingAs($admin)->postJson("/applications/{$applicationId}/move-stage", [
            'to_stage_id' => $stageB, 'candidate_visibility' => 'VISIBLE',
        ])->assertOk();

        $detail = $this->actingAs($candidate)->getJson("/applications/{$applicationId}?include=history")->assertOk();

        $stageEvents = collect($detail->json('data.history'))->where('event_type', 'STAGE_CHANGED')->values();
        self::assertCount(1, $stageEvents);
        self::assertNull($stageEvents[0]['stage_label']);
        self::assertStringNotContainsString('MS-INTERNAL-NOLABEL-ZZ', $detail->getContent());
    }

    // ---------------------------------------------------------------
    // Notification
    // ---------------------------------------------------------------

    public function test_visible_notifies_candidate_with_stage_label_internal_does_not(): void
    {
        [$admin, , $vacancyId] = $this->openVacancy('ms-notif@example.test');
        $stage = $this->createStage($admin, $vacancyId, 'Wawancara Dekan', 0, true, 'Interview Tahap 2');

        [$candidateVisible] = $this->candidate('ms-notif-visible@example.test');
        $visibleId = $this->submitApplication($candidateVisible, $vacancyId);
        $this->actingAs($admin)->postJson("/applications/{$visibleId}/move-stage", [
            'to_stage_id' => $stage, 'candidate_visibility' => 'VISIBLE',
        ])->assertOk();

        self::assertSame(1, DB::table('notifications')->where('related_object_type', 'application')->where('related_object_id', $visibleId)
            ->where('user_id', $candidateVisible->id)->where('body_reference', 'application.stage_moved.candidate')->count());
        $outboxRow = DB::table('email_outbox')->where('related_object_type', 'application')->where('related_object_id', $visibleId)
            ->where('template_reference', 'application.stage_moved.candidate')->first();
        self::assertNotNull($outboxRow);
        $payload = json_decode((string) $outboxRow->payload_reference, true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('Interview Tahap 2', $payload['stage_label']);

        [$candidateInternal] = $this->candidate('ms-notif-internal@example.test');
        $internalId = $this->submitApplication($candidateInternal, $vacancyId);
        $notifBefore = DB::table('notifications')->count();
        $outboxBefore = DB::table('email_outbox')->count();

        $this->actingAs($admin)->postJson("/applications/{$internalId}/move-stage", [
            'to_stage_id' => $stage, 'candidate_visibility' => 'INTERNAL',
        ])->assertOk();

        self::assertSame($notifBefore, DB::table('notifications')->count());
        self::assertSame($outboxBefore, DB::table('email_outbox')->count());
    }

    // ---------------------------------------------------------------
    // Idempotency
    // ---------------------------------------------------------------

    public function test_idempotency_key_replay_does_not_duplicate_side_effects(): void
    {
        [$admin, , $vacancyId] = $this->openVacancy('ms-idem@example.test');
        $stage = $this->createStage($admin, $vacancyId, 'A', 0);
        [$candidate] = $this->candidate('ms-idem-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);
        $key = 'ms-move-'.uniqid('', true);

        $first = $this->actingAs($admin)->withHeaders(['Idempotency-Key' => $key])->postJson("/applications/{$applicationId}/move-stage", [
            'to_stage_id' => $stage, 'candidate_visibility' => 'VISIBLE',
        ])->assertOk();

        $replay = $this->actingAs($admin)->withHeaders(['Idempotency-Key' => $key])->postJson("/applications/{$applicationId}/move-stage", [
            'to_stage_id' => $stage, 'candidate_visibility' => 'VISIBLE',
        ])->assertOk();

        self::assertEquals($first->json('data'), $replay->json('data'));
        self::assertSame(1, DB::table('application_status_histories')->where('application_id', $applicationId)->where('event_type', 'STAGE_CHANGED')->count());
        self::assertSame(1, DB::table('audit_logs')->where('action', 'application_stage_changed')->where('object_id', $applicationId)->count());
        self::assertSame(1, DB::table('notifications')->where('related_object_type', 'application')->where('related_object_id', $applicationId)->where('body_reference', 'application.stage_moved.candidate')->count());
    }

    // ---------------------------------------------------------------
    // Stage Authoring boundary
    // ---------------------------------------------------------------

    public function test_stage_rename_and_reorder_never_affect_current_stage_id(): void
    {
        [$admin, , $vacancyId] = $this->openVacancy('ms-authoring-boundary@example.test');
        $stageA = $this->createStage($admin, $vacancyId, 'A', 0);
        $stageB = $this->createStage($admin, $vacancyId, 'B', 1);
        [$candidate] = $this->candidate('ms-authoring-boundary-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);
        DB::table('applications')->where('id', $applicationId)->update(['current_stage_id' => $stageA]);

        $this->actingAs($admin)->patchJson("/vacancies/{$vacancyId}/stages/{$stageA}", ['name' => 'Renamed A'])->assertOk();
        $this->actingAs($admin)->postJson("/vacancies/{$vacancyId}/stages/reorder", ['stage_ids' => [$stageB, $stageA]])->assertOk();

        self::assertSame($stageA, DB::table('applications')->where('id', $applicationId)->value('current_stage_id'));
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
        $approver = $this->moderator('approver-'.$vacancyId.'@example.test');
        $this->actingAs($approver)->postJson("/vacancies/{$vacancyId}/approve")->assertOk();
        DB::table('vacancies')->where('id', $vacancyId)->update([
            'current_status' => 'PUBLISHED', 'published_at' => $open,
        ]);
        Carbon::setTestNow($close->copy()->subDay());

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

    private function createStage(User $admin, int $vacancyId, string $name, int $sortOrder, bool $active = true, ?string $candidateVisibleLabel = null): int
    {
        return (int) $this->actingAs($admin)->postJson("/vacancies/{$vacancyId}/stages", [
            'name' => $name, 'stage_type' => 'GENERAL', 'sort_order' => $sortOrder, 'active' => $active,
            'candidate_visible_label' => $candidateVisibleLabel,
        ])->assertCreated()->json('data.id');
    }
}
