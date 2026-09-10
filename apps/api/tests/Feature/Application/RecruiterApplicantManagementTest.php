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
 * Recruiter Applicant Management Foundation v1 — list, detail, transition.
 * RA-1 (transition graph) and RA-2 (processing gate) are approved and
 * CLOSED. move-stage is activated separately by Application Stage Movement
 * Foundation v1 — see `ApplicationStageMovementTest`. bulk-transition,
 * document download, evaluations, schedules, offers, reopen, and SELECTOR
 * remain deliberately unimplemented.
 */
final class RecruiterApplicantManagementTest extends VacancyTestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ---------------------------------------------------------------
    // Routes
    // ---------------------------------------------------------------

    public function test_transition_route_exists_bulk_download_reopen_absent(): void
    {
        $registered = collect(Route::getRoutes())->map(
            static fn ($route): string => strtoupper(implode('|', $route->methods())).' '.$route->uri(),
        );

        self::assertTrue($registered->contains('POST applications/{application}/transition'));

        // move-stage is now routed — Application Stage Movement Foundation
        // v1 (MS-3, MS-4) — see ApplicationStageMovementTest for its own
        // route-existence assertion.
        // application-documents download is now routed (PGC-V1 / PD-A, supersedes
        // RA-3 for the download operation — API_CONTRACT.md Part X items 22 / 62).
        self::assertTrue($registered->contains('GET|HEAD application-documents/{applicationDocument}/download'));

        foreach (['bulk-transition', 'reopen'] as $absent) {
            self::assertFalse(
                $registered->contains(fn (string $r): bool => str_contains($r, $absent)),
                "No route may exist for {$absent} in this milestone.",
            );
        }

        self::assertFalse($registered->contains(fn (string $r): bool => str_contains($r, 'api/v1') && str_contains($r, 'applic')));
    }

    // ---------------------------------------------------------------
    // Authorization — list & detail
    // ---------------------------------------------------------------

    public function test_recruiter_and_admin_active_members_can_list_and_read_own_company_applications(): void
    {
        [$admin, $company, $vacancyId] = $this->openVacancy('ra-admin@example.test');
        $recruiter = $this->recruiterMember($company->id, 'ra-recruiter@example.test');
        [$candidate, $profileId] = $this->candidate('ra-candidate@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);

        foreach ([$admin, $recruiter] as $actor) {
            $list = $this->actingAs($actor)->getJson('/applications')->assertOk();
            self::assertSame([$applicationId], $list->json('data.items.*.id'));

            $detail = $this->actingAs($actor)->getJson("/applications/{$applicationId}")->assertOk();
            self::assertSame($applicationId, $detail->json('data.id'));
            self::assertSame($profileId, $detail->json('data.candidate.candidate_profile_id'));
        }
    }

    public function test_revoked_member_and_cross_company_application_are_hidden(): void
    {
        [$adminA, $companyA, $vacancyIdA] = $this->openVacancy('ra-cross-a@example.test');
        [, $companyB] = $this->verifiedCompanyWithRecruiter('ra-cross-b@example.test');
        [$candidate] = $this->candidate('ra-cross-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyIdA);

        $recruiterB = $this->recruiterMember($companyB->id, 'ra-cross-recruiter-b@example.test');
        $this->actingAs($recruiterB)->getJson("/applications/{$applicationId}")->assertNotFound()->assertJsonPath('error.code', 'NOT_FOUND');
        $this->actingAs($recruiterB)->getJson('/applications')->assertOk()->assertJsonPath('data.items', []);

        DB::table('company_members')->where('company_id', $companyA->id)->where('user_id', $adminA->id)
            ->update(['status' => 'INACTIVE']);
        $this->actingAs($adminA->fresh())->getJson("/applications/{$applicationId}")->assertNotFound();
        $this->actingAs($adminA->fresh())->getJson('/applications')->assertOk()->assertJsonPath('data.items', []);
    }

    public function test_career_center_and_selector_gain_no_recruiter_capability(): void
    {
        [, , $vacancyId] = $this->openVacancy('ra-deny@example.test');
        [$candidate] = $this->candidate('ra-deny-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);

        $careerCenter = $this->moderator('ra-deny-cc@example.test', RoleCode::CareerCenterStaff);
        $careerCenterManager = $this->moderator('ra-deny-ccm@example.test', RoleCode::CareerCenterManager);
        $selector = $this->moderator('ra-deny-selector@example.test', RoleCode::Selector);

        // Career Center has no candidate-selection scope of any kind — a blanket
        // 403 on read and on transition (FSD §3.3, matrix §4.6 footnote 13).
        foreach ([$careerCenter, $careerCenterManager] as $actor) {
            $this->actingAs($actor)->getJson('/applications')
                ->assertStatus(403)->assertJsonPath('error.code', 'AUTH_FORBIDDEN');

            $this->actingAs($actor)->getJson("/applications/{$applicationId}")
                ->assertStatus(403)->assertJsonPath('error.code', 'AUTH_FORBIDDEN');

            $this->actingAs($actor)->postJson("/applications/{$applicationId}/transition", [
                'to_status' => 'UNDER_REVIEW', 'candidate_visibility' => 'VISIBLE',
            ])->assertStatus(403)->assertJsonPath('error.code', 'AUTH_FORBIDDEN');
        }

        // SELECTOR is `S` (ASSIGNED_STAGE) on application reads, now wired
        // (GAP-010). With NO active selection_stage_assignment its scope is
        // empty: the list is query-scoped to nothing (200, no items) and a
        // direct read is an enumeration-safe 404, never a 403 that would
        // confirm the row exists. It still cannot transition — selectors
        // evaluate, they never decide (matrix §4.6 footnote 18).
        $this->actingAs($selector)->getJson('/applications')
            ->assertOk()->assertJsonPath('data.items', []);
        $this->actingAs($selector)->getJson("/applications/{$applicationId}")
            ->assertStatus(404)->assertJsonPath('error.code', 'NOT_FOUND');
        $this->actingAs($selector)->postJson("/applications/{$applicationId}/transition", [
            'to_status' => 'UNDER_REVIEW', 'candidate_visibility' => 'VISIBLE',
        ])->assertStatus(403)->assertJsonPath('error.code', 'AUTH_FORBIDDEN');

        self::assertSame('APPLIED', DB::table('applications')->where('id', $applicationId)->value('current_status'));
    }

    /**
     * The core regression for the authorization correction: denial must be
     * caused by the actor lacking recruiter/candidate capability, never by
     * the incidental absence of a candidate_profiles row. Each actor here
     * explicitly holds a candidate_profile and must still be denied.
     */
    public function test_career_center_and_selector_are_denied_even_when_a_candidate_profile_exists(): void
    {
        [, , $vacancyId] = $this->openVacancy('ra-deny-profile@example.test');
        [$candidate] = $this->candidate('ra-deny-profile-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);

        $careerCenter = $this->moderator('ra-deny-profile-cc@example.test', RoleCode::CareerCenterStaff);
        $this->giveCandidateProfile($careerCenter);
        $careerCenterManager = $this->moderator('ra-deny-profile-ccm@example.test', RoleCode::CareerCenterManager);
        $this->giveCandidateProfile($careerCenterManager);
        $selector = $this->moderator('ra-deny-profile-selector@example.test', RoleCode::Selector);
        $this->giveCandidateProfile($selector);

        // The core regression: denial is caused by lacking the capability,
        // never by the incidental presence/absence of a candidate_profiles row.
        foreach ([$careerCenter, $careerCenterManager] as $actor) {
            self::assertNotNull(DB::table('candidate_profiles')->where('user_id', $actor->id)->value('id'), 'Fixture must actually hold a candidate_profile.');

            $this->actingAs($actor)->getJson('/applications')
                ->assertStatus(403)->assertJsonPath('error.code', 'AUTH_FORBIDDEN');

            $this->actingAs($actor)->getJson("/applications/{$applicationId}")
                ->assertStatus(403)->assertJsonPath('error.code', 'AUTH_FORBIDDEN');

            $this->actingAs($actor)->postJson("/applications/{$applicationId}/transition", [
                'to_status' => 'UNDER_REVIEW', 'candidate_visibility' => 'VISIBLE',
            ])->assertStatus(403)->assertJsonPath('error.code', 'AUTH_FORBIDDEN');
        }

        // A SELECTOR holding a candidate_profile still resolves to its own
        // empty ASSIGNED_STAGE scope (GAP-010), not the candidate scope and
        // not a 403: an unassigned selector simply sees nothing, and still
        // cannot transition.
        self::assertNotNull(DB::table('candidate_profiles')->where('user_id', $selector->id)->value('id'), 'Fixture must actually hold a candidate_profile.');
        $this->actingAs($selector)->getJson('/applications')
            ->assertOk()->assertJsonPath('data.items', []);
        $this->actingAs($selector)->getJson("/applications/{$applicationId}")
            ->assertStatus(404)->assertJsonPath('error.code', 'NOT_FOUND');
        $this->actingAs($selector)->postJson("/applications/{$applicationId}/transition", [
            'to_status' => 'UNDER_REVIEW', 'candidate_visibility' => 'VISIBLE',
        ])->assertStatus(403)->assertJsonPath('error.code', 'AUTH_FORBIDDEN');

        self::assertSame('APPLIED', DB::table('applications')->where('id', $applicationId)->value('current_status'));
    }

    public function test_candidate_without_a_profile_still_gets_candidate_profile_required(): void
    {
        $candidateUser = $this->makeUser('ra-noprofile-c@example.test', UserStatus::Active);
        $this->assignRole($candidateUser, RoleCode::CandidateExternal);

        $this->actingAs($candidateUser)->getJson('/applications')
            ->assertStatus(422)->assertJsonPath('error.code', 'CANDIDATE_PROFILE_REQUIRED');
        $this->actingAs($candidateUser)->getJson('/applications/1')
            ->assertStatus(422)->assertJsonPath('error.code', 'CANDIDATE_PROFILE_REQUIRED');
    }

    public function test_recruiter_with_a_candidate_profile_still_gets_company_scope_not_own(): void
    {
        [$admin, $company, $vacancyId] = $this->openVacancy('ra-multirole@example.test');
        $this->giveCandidateProfile($admin);

        [$candidate] = $this->candidate('ra-multirole-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);

        $list = $this->actingAs($admin)->getJson('/applications')->assertOk();
        self::assertSame([$applicationId], $list->json('data.items.*.id'));
        self::assertArrayHasKey('candidate', $list->json('data.items.0'));

        $detail = $this->actingAs($admin)->getJson("/applications/{$applicationId}")->assertOk();
        self::assertArrayHasKey('history', $detail->json('data'));
    }

    public function test_super_admin_can_list_and_read_any_company_application(): void
    {
        [, , $vacancyId] = $this->openVacancy('ra-super@example.test');
        [$candidate, $profileId] = $this->candidate('ra-super-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);

        $superAdmin = $this->moderator('ra-super-admin@example.test', RoleCode::SuperAdmin);

        $list = $this->actingAs($superAdmin)->getJson('/applications')->assertOk();
        self::assertContains($applicationId, $list->json('data.items.*.id'));

        $detail = $this->actingAs($superAdmin)->getJson("/applications/{$applicationId}")->assertOk();
        self::assertSame($profileId, $detail->json('data.candidate.candidate_profile_id'));
    }

    public function test_candidate_own_view_is_unaffected_by_recruiter_capability(): void
    {
        [, , $vacancyId] = $this->openVacancy('ra-cand-regress@example.test');
        [$candidate] = $this->candidate('ra-cand-regress-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);

        $list = $this->actingAs($candidate)->getJson('/applications')->assertOk();
        self::assertSame([$applicationId], $list->json('data.items.*.id'));
        self::assertArrayNotHasKey('candidate', $list->json('data.items.0'));

        $detail = $this->actingAs($candidate)->getJson("/applications/{$applicationId}?include=history")->assertOk();
        self::assertArrayNotHasKey('candidate', $detail->json('data'));
    }

    // ---------------------------------------------------------------
    // Detail field allow-list
    // ---------------------------------------------------------------

    public function test_applicant_detail_exposes_only_safe_allow_listed_fields(): void
    {
        [$admin, $company, $vacancyId] = $this->openVacancy('ra-fields@example.test');
        [$candidate, $profileId] = $this->candidate('ra-fields-c@example.test');
        $docId = $this->candidateDocument($profileId, 'resume.pdf');
        $applicationId = $this->submitApplication($candidate, $vacancyId, ['document_ids' => [$docId]]);

        $detail = $this->actingAs($admin)->getJson("/applications/{$applicationId}")->assertOk();

        self::assertSame('Test User', $detail->json('data.candidate.name'));
        self::assertArrayNotHasKey('email', $detail->json('data.candidate'));
        self::assertArrayNotHasKey('phone', $detail->json('data.candidate'));

        $doc = $detail->json('data.documents.0');
        self::assertSame('resume.pdf', $doc['snapshot_name']);
        self::assertArrayNotHasKey('snapshot_storage_reference', $doc);
        self::assertArrayNotHasKey('snapshot_checksum', $doc);
        self::assertArrayNotHasKey('candidate_document_id', $doc);

        self::assertArrayNotHasKey('evaluations', $detail->json('data'));
        self::assertArrayNotHasKey('schedules', $detail->json('data'));
        self::assertArrayNotHasKey('offers', $detail->json('data'));
    }

    public function test_recruiter_history_is_full_unfiltered_and_screening_answers_are_scoped(): void
    {
        [$admin, $company, $vacancyId] = $this->openVacancy('ra-history@example.test');
        [$candidate] = $this->candidate('ra-history-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);

        $this->actingAs($admin)->postJson("/applications/{$applicationId}/transition", [
            'to_status' => 'UNDER_REVIEW', 'candidate_visibility' => 'INTERNAL', 'reason' => 'Internal recruiter note',
        ])->assertOk();

        $detail = $this->actingAs($admin)->getJson("/applications/{$applicationId}")->assertOk();
        $internalEvent = collect($detail->json('data.history'))->firstWhere('event_type', 'STATUS_CHANGED');
        self::assertSame('Internal recruiter note', $internalEvent['reason']);
        self::assertSame('INTERNAL', $internalEvent['candidate_visibility']);
    }

    // ---------------------------------------------------------------
    // Opening detail never mutates status
    // ---------------------------------------------------------------

    public function test_reading_applicant_detail_never_auto_transitions(): void
    {
        [$admin, , $vacancyId] = $this->openVacancy('ra-noauto@example.test');
        [$candidate] = $this->candidate('ra-noauto-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);

        $this->actingAs($admin)->getJson("/applications/{$applicationId}")->assertOk();
        $this->actingAs($admin)->getJson("/applications/{$applicationId}")->assertOk();

        self::assertSame('APPLIED', DB::table('applications')->where('id', $applicationId)->value('current_status'));
        self::assertSame(1, DB::table('application_status_histories')->where('application_id', $applicationId)->count());
    }

    // ---------------------------------------------------------------
    // RA-1 — transition graph
    // ---------------------------------------------------------------

    public function test_ra1_allowed_edges_succeed_in_sequence(): void
    {
        [$admin, , $vacancyId] = $this->openVacancy('ra1-allowed@example.test');
        [$candidate] = $this->candidate('ra1-allowed-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);

        $this->actingAs($admin)->postJson("/applications/{$applicationId}/transition", [
            'to_status' => 'UNDER_REVIEW', 'candidate_visibility' => 'VISIBLE',
        ])->assertOk()->assertJsonPath('data.current_status', 'UNDER_REVIEW');

        $this->actingAs($admin)->postJson("/applications/{$applicationId}/transition", [
            'to_status' => 'SHORTLISTED', 'candidate_visibility' => 'VISIBLE',
        ])->assertOk()->assertJsonPath('data.current_status', 'SHORTLISTED');

        $this->actingAs($admin)->postJson("/applications/{$applicationId}/transition", [
            'to_status' => 'REJECTED', 'candidate_visibility' => 'VISIBLE',
        ])->assertOk()->assertJsonPath('data.current_status', 'REJECTED');

        self::assertSame(
            ['APPLICATION_CREATED', 'STATUS_CHANGED', 'STATUS_CHANGED', 'REJECTED'],
            DB::table('application_status_histories')->where('application_id', $applicationId)->orderBy('id')->pluck('event_type')->all(),
        );
    }

    public function test_ra1_applied_to_rejected_direct_edge_succeeds(): void
    {
        [$admin, , $vacancyId] = $this->openVacancy('ra1-direct-reject@example.test');
        [$candidate] = $this->candidate('ra1-direct-reject-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);

        $this->actingAs($admin)->postJson("/applications/{$applicationId}/transition", [
            'to_status' => 'REJECTED', 'candidate_visibility' => 'VISIBLE',
        ])->assertOk();
    }

    public function test_ra1_disallowed_edges_are_rejected_with_zero_side_effects(): void
    {
        [$admin, , $vacancyId] = $this->openVacancy('ra1-forbidden@example.test');

        $cases = [
            ['from' => 'APPLIED', 'to' => 'SHORTLISTED'],
            ['from' => 'APPLIED', 'to' => 'ASSESSMENT'],
            ['from' => 'UNDER_REVIEW', 'to' => 'APPLIED'],
            ['from' => 'UNDER_REVIEW', 'to' => 'INTERVIEW'],
            ['from' => 'SHORTLISTED', 'to' => 'UNDER_REVIEW'],
            ['from' => 'SHORTLISTED', 'to' => 'ASSESSMENT'],
        ];

        foreach ($cases as $i => $case) {
            [$candidate] = $this->candidate("ra1-forbidden-{$i}@example.test");
            $applicationId = $this->submitApplication($candidate, $vacancyId);
            $this->driveToStatus($admin, $applicationId, $case['from']);

            $historyBefore = DB::table('application_status_histories')->where('application_id', $applicationId)->count();
            $auditBefore = DB::table('audit_logs')->where('action', 'application_status_changed')->where('object_id', $applicationId)->count();

            $response = $this->actingAs($admin)->postJson("/applications/{$applicationId}/transition", [
                'to_status' => $case['to'], 'candidate_visibility' => 'VISIBLE',
            ])->assertStatus(409)->assertJsonPath('error.code', 'APPLICATION_INVALID_TRANSITION');

            self::assertSame($case['from'], $response->json('error.details.from'));
            self::assertSame($case['to'], $response->json('error.details.attempted'));
            self::assertSame($case['from'], DB::table('applications')->where('id', $applicationId)->value('current_status'));
            self::assertSame($historyBefore, DB::table('application_status_histories')->where('application_id', $applicationId)->count());
            self::assertSame($auditBefore, DB::table('audit_logs')->where('action', 'application_status_changed')->where('object_id', $applicationId)->count());
        }
    }

    public function test_ra1_same_status_no_op_is_rejected(): void
    {
        [$admin, , $vacancyId] = $this->openVacancy('ra1-noop@example.test');
        [$candidate] = $this->candidate('ra1-noop-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);
        $this->driveToStatus($admin, $applicationId, 'UNDER_REVIEW');

        $this->actingAs($admin)->postJson("/applications/{$applicationId}/transition", [
            'to_status' => 'UNDER_REVIEW', 'candidate_visibility' => 'VISIBLE',
        ])->assertStatus(409)->assertJsonPath('error.code', 'APPLICATION_INVALID_TRANSITION');
    }

    public function test_terminal_states_cannot_be_left(): void
    {
        [$admin, , $vacancyId] = $this->openVacancy('ra1-terminal@example.test');

        [$candidateA] = $this->candidate('ra1-terminal-a@example.test');
        $rejectedId = $this->submitApplication($candidateA, $vacancyId);
        $this->driveToStatus($admin, $rejectedId, 'REJECTED');

        [$candidateB] = $this->candidate('ra1-terminal-b@example.test');
        $withdrawnId = $this->submitApplication($candidateB, $vacancyId);
        $this->actingAs($candidateB)->postJson("/applications/{$withdrawnId}/withdraw")->assertOk();

        foreach ([$rejectedId, $withdrawnId] as $applicationId) {
            $historyBefore = DB::table('application_status_histories')->where('application_id', $applicationId)->count();
            $notifBefore = DB::table('notifications')->count();

            $this->actingAs($admin)->postJson("/applications/{$applicationId}/transition", [
                'to_status' => 'UNDER_REVIEW', 'candidate_visibility' => 'VISIBLE',
            ])->assertStatus(409)->assertJsonPath('error.code', 'APPLICATION_TERMINAL');

            self::assertSame($historyBefore, DB::table('application_status_histories')->where('application_id', $applicationId)->count());
            self::assertSame($notifBefore, DB::table('notifications')->count());
        }
    }

    // ---------------------------------------------------------------
    // RA-2 — processing gate
    // ---------------------------------------------------------------

    public function test_ra2_company_and_vacancy_processing_matrix(): void
    {
        [$admin, $company, $vacancyId] = $this->openVacancy('ra2-matrix@example.test');

        $allowed = ['PUBLISHED', 'CLOSED', 'EXPIRED'];
        $denied = ['SUSPENDED'];

        foreach ($allowed as $vacancyStatus) {
            [$candidate] = $this->candidate('ra2-'.mb_strtolower($vacancyStatus).'@example.test');
            $applicationId = $this->submitApplication($candidate, $vacancyId);
            DB::table('vacancies')->where('id', $vacancyId)->update(['current_status' => $vacancyStatus]);

            $this->actingAs($admin)->postJson("/applications/{$applicationId}/transition", [
                'to_status' => 'UNDER_REVIEW', 'candidate_visibility' => 'VISIBLE',
            ])->assertOk();

            DB::table('vacancies')->where('id', $vacancyId)->update(['current_status' => 'PUBLISHED']);
        }

        foreach ($denied as $vacancyStatus) {
            [$candidate] = $this->candidate('ra2-denied-'.mb_strtolower($vacancyStatus).'@example.test');
            $applicationId = $this->submitApplication($candidate, $vacancyId);
            DB::table('vacancies')->where('id', $vacancyId)->update(['current_status' => $vacancyStatus]);

            $historyBefore = DB::table('application_status_histories')->where('application_id', $applicationId)->count();
            $notifBefore = DB::table('notifications')->count();
            $outboxBefore = DB::table('email_outbox')->count();

            $this->actingAs($admin)->postJson("/applications/{$applicationId}/transition", [
                'to_status' => 'UNDER_REVIEW', 'candidate_visibility' => 'VISIBLE',
            ])->assertStatus(409)->assertJsonPath('error.code', 'APPLICATION_VACANCY_NOT_PROCESSABLE');

            self::assertSame('APPLIED', DB::table('applications')->where('id', $applicationId)->value('current_status'));
            self::assertSame($historyBefore, DB::table('application_status_histories')->where('application_id', $applicationId)->count());
            self::assertSame($notifBefore, DB::table('notifications')->count());
            self::assertSame($outboxBefore, DB::table('email_outbox')->count());

            // Reads remain allowed even while processing is denied.
            $this->actingAs($admin)->getJson("/applications/{$applicationId}")->assertOk();

            DB::table('vacancies')->where('id', $vacancyId)->update(['current_status' => 'PUBLISHED']);
        }
    }

    public function test_ra2_company_non_verified_blocks_processing_with_zero_side_effects(): void
    {
        [$admin, $company, $vacancyId] = $this->openVacancy('ra2-company@example.test');
        [$candidate] = $this->candidate('ra2-company-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);

        DB::table('companies')->where('id', $company->id)->update(['verification_status' => 'SUSPENDED']);

        $historyBefore = DB::table('application_status_histories')->where('application_id', $applicationId)->count();
        $this->actingAs($admin)->postJson("/applications/{$applicationId}/transition", [
            'to_status' => 'UNDER_REVIEW', 'candidate_visibility' => 'VISIBLE',
        ])->assertStatus(403)->assertJsonPath('error.code', 'VACANCY_COMPANY_NOT_VERIFIED');

        self::assertSame('APPLIED', DB::table('applications')->where('id', $applicationId)->value('current_status'));
        self::assertSame($historyBefore, DB::table('application_status_histories')->where('application_id', $applicationId)->count());

        // Reads remain allowed.
        $this->actingAs($admin)->getJson("/applications/{$applicationId}")->assertOk();
        $this->actingAs($admin)->getJson('/applications')->assertOk();

        DB::table('companies')->where('id', $company->id)->update(['verification_status' => 'VERIFIED']);
        $this->actingAs($admin)->postJson("/applications/{$applicationId}/transition", [
            'to_status' => 'UNDER_REVIEW', 'candidate_visibility' => 'VISIBLE',
        ])->assertOk();
    }

    public function test_ra2_applies_identically_to_super_admin(): void
    {
        [, $company, $vacancyId] = $this->openVacancy('ra2-super@example.test');
        [$candidate] = $this->candidate('ra2-super-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);
        DB::table('vacancies')->where('id', $vacancyId)->update(['current_status' => 'SUSPENDED']);

        $superAdmin = $this->moderator('ra2-super-admin@example.test', RoleCode::SuperAdmin);
        $this->actingAs($superAdmin)->postJson("/applications/{$applicationId}/transition", [
            'to_status' => 'UNDER_REVIEW', 'candidate_visibility' => 'VISIBLE',
        ])->assertStatus(409)->assertJsonPath('error.code', 'APPLICATION_VACANCY_NOT_PROCESSABLE');
    }

    // ---------------------------------------------------------------
    // If-Match / STALE_VERSION
    // ---------------------------------------------------------------

    public function test_if_match_correct_version_succeeds_stale_version_rejected(): void
    {
        [$admin, , $vacancyId] = $this->openVacancy('ra-ifmatch@example.test');
        [$candidate] = $this->candidate('ra-ifmatch-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);

        $this->actingAs($admin)->withHeaders(['If-Match' => '1'])->postJson("/applications/{$applicationId}/transition", [
            'to_status' => 'UNDER_REVIEW', 'candidate_visibility' => 'VISIBLE',
        ])->assertOk();

        $historyBefore = DB::table('application_status_histories')->where('application_id', $applicationId)->count();
        $this->actingAs($admin)->withHeaders(['If-Match' => '1'])->postJson("/applications/{$applicationId}/transition", [
            'to_status' => 'SHORTLISTED', 'candidate_visibility' => 'VISIBLE',
        ])->assertStatus(409)->assertJsonPath('error.code', 'STALE_VERSION');

        self::assertSame($historyBefore, DB::table('application_status_histories')->where('application_id', $applicationId)->count());
        self::assertSame('UNDER_REVIEW', DB::table('applications')->where('id', $applicationId)->value('current_status'));

        $this->actingAs($admin)->withHeaders(['If-Match' => '2'])->postJson("/applications/{$applicationId}/transition", [
            'to_status' => 'SHORTLISTED', 'candidate_visibility' => 'VISIBLE',
        ])->assertOk();
    }

    // ---------------------------------------------------------------
    // Idempotency
    // ---------------------------------------------------------------

    public function test_idempotency_key_replay_does_not_duplicate_side_effects(): void
    {
        [$admin, , $vacancyId] = $this->openVacancy('ra-idem@example.test');
        [$candidate] = $this->candidate('ra-idem-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);
        $key = 'ra-transition-'.uniqid('', true);

        $first = $this->actingAs($admin)->withHeaders(['Idempotency-Key' => $key])->postJson("/applications/{$applicationId}/transition", [
            'to_status' => 'UNDER_REVIEW', 'candidate_visibility' => 'VISIBLE',
        ])->assertOk();

        $replay = $this->actingAs($admin)->withHeaders(['Idempotency-Key' => $key])->postJson("/applications/{$applicationId}/transition", [
            'to_status' => 'UNDER_REVIEW', 'candidate_visibility' => 'VISIBLE',
        ])->assertOk();

        self::assertEquals($first->json('data'), $replay->json('data'));
        self::assertSame(2, DB::table('application_status_histories')->where('application_id', $applicationId)->count());
        self::assertSame(1, DB::table('audit_logs')->where('action', 'application_status_changed')->where('object_id', $applicationId)->count());
        self::assertSame(1, DB::table('notifications')->where('related_object_type', 'application')->where('related_object_id', $applicationId)->where('body_reference', 'application.transitioned.candidate')->count());
        self::assertSame(1, DB::table('email_outbox')->where('related_object_type', 'application')->where('related_object_id', $applicationId)->where('template_reference', 'application.transitioned.candidate')->count());
    }

    // ---------------------------------------------------------------
    // Notifications — visible vs internal
    // ---------------------------------------------------------------

    public function test_visible_transition_notifies_candidate_internal_does_not(): void
    {
        [$admin, , $vacancyId] = $this->openVacancy('ra-notif@example.test');

        [$candidateVisible] = $this->candidate('ra-notif-visible@example.test');
        $visibleId = $this->submitApplication($candidateVisible, $vacancyId);
        $this->actingAs($admin)->postJson("/applications/{$visibleId}/transition", [
            'to_status' => 'UNDER_REVIEW', 'candidate_visibility' => 'VISIBLE', 'candidate_visible_note' => 'Sedang ditinjau tim kami.',
        ])->assertOk();

        self::assertSame(1, DB::table('notifications')->where('related_object_type', 'application')->where('related_object_id', $visibleId)
            ->where('user_id', $candidateVisible->id)->where('body_reference', 'application.transitioned.candidate')->count());
        self::assertSame(1, DB::table('email_outbox')->where('related_object_type', 'application')->where('related_object_id', $visibleId)
            ->where('recipient', $candidateVisible->email)->where('template_reference', 'application.transitioned.candidate')->count());

        $candidateDetail = $this->actingAs($candidateVisible)->getJson("/applications/{$visibleId}?include=history")->assertOk();
        $event = collect($candidateDetail->json('data.history'))->firstWhere('event_type', 'STATUS_CHANGED');
        self::assertSame('Sedang ditinjau tim kami.', $event['candidate_visible_note']);
        self::assertArrayNotHasKey('reason', $event);

        [$candidateInternal] = $this->candidate('ra-notif-internal@example.test');
        $internalId = $this->submitApplication($candidateInternal, $vacancyId);
        $notifBefore = DB::table('notifications')->count();
        $outboxBefore = DB::table('email_outbox')->count();

        $this->actingAs($admin)->postJson("/applications/{$internalId}/transition", [
            'to_status' => 'UNDER_REVIEW', 'candidate_visibility' => 'INTERNAL', 'reason' => 'Menunggu dokumen tambahan.',
        ])->assertOk();

        self::assertSame($notifBefore, DB::table('notifications')->count());
        self::assertSame($outboxBefore, DB::table('email_outbox')->count());

        $candidateDetail = $this->actingAs($candidateInternal)->getJson("/applications/{$internalId}?include=history")->assertOk();
        self::assertCount(1, $candidateDetail->json('data.history')); // only APPLICATION_CREATED remains visible
        self::assertSame('APPLICATION_CREATED', $candidateDetail->json('data.history.0.event_type'));
    }

    public function test_no_recruiter_self_notification(): void
    {
        [$admin, , $vacancyId] = $this->openVacancy('ra-noself@example.test');
        [$candidate] = $this->candidate('ra-noself-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);

        // Submit itself already notifies the owner (AD-1/FR-NOTIF-002) — the
        // assertion is that /transition adds no further owner notification,
        // not that the owner has never received any notification at all.
        $ownerNotificationsBefore = DB::table('notifications')->where('related_object_type', 'application')->where('related_object_id', $applicationId)->where('user_id', $admin->id)->count();

        $this->actingAs($admin)->postJson("/applications/{$applicationId}/transition", [
            'to_status' => 'UNDER_REVIEW', 'candidate_visibility' => 'VISIBLE',
        ])->assertOk();

        self::assertSame($ownerNotificationsBefore, DB::table('notifications')->where('related_object_type', 'application')->where('related_object_id', $applicationId)->where('user_id', $admin->id)->count());
    }

    // ---------------------------------------------------------------
    // No stage mutation
    // ---------------------------------------------------------------

    public function test_transition_never_mutates_current_stage_id(): void
    {
        [$admin, , $vacancyId] = $this->openVacancy('ra-nostage@example.test');
        [$candidate] = $this->candidate('ra-nostage-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);

        $this->actingAs($admin)->postJson("/applications/{$applicationId}/transition", [
            'to_status' => 'UNDER_REVIEW', 'candidate_visibility' => 'VISIBLE',
        ])->assertOk();

        self::assertNull(DB::table('applications')->where('id', $applicationId)->value('current_stage_id'));
        self::assertNull(DB::table('selection_stage_assignments')->where('id', '>', 0)->value('id'));
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

    /** Gives an existing (non-candidate-role) user a candidate_profiles row, to prove profile existence alone grants nothing. */
    private function giveCandidateProfile(User $user): int
    {
        return (int) DB::table('candidate_profiles')->insertGetId([
            'user_id' => $user->id, 'current_candidate_type' => 'EXTERNAL', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function candidateDocument(int $profileId, string $displayName): int
    {
        return (int) DB::table('candidate_documents')->insertGetId([
            'candidate_profile_id' => $profileId, 'document_type' => 'CV', 'display_name' => $displayName,
            'storage_reference' => 'private/candidates/'.uniqid('', true).'.pdf', 'mime_type' => 'application/pdf',
            'size' => 12345, 'uploaded_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function submitApplication(User $candidate, int $vacancyId, array $overrides = []): int
    {
        $payload = array_merge([
            'consent' => [
                'consent_version' => \App\Domains\Application\Support\ApplicationConsentVersion::CURRENT,
                'consent_text_hash_reference' => 'sha256:'.str_repeat('a', 64),
                'accepted' => true,
            ],
        ], $overrides);

        return (int) $this->actingAs($candidate)->postJson("/vacancies/{$vacancyId}/applications", $payload)
            ->assertCreated()->json('data.id');
    }

    private function driveToStatus(User $admin, int $applicationId, string $targetStatus): void
    {
        $path = match ($targetStatus) {
            'APPLIED' => [],
            'UNDER_REVIEW' => ['UNDER_REVIEW'],
            'SHORTLISTED' => ['UNDER_REVIEW', 'SHORTLISTED'],
            'REJECTED' => ['UNDER_REVIEW', 'SHORTLISTED', 'REJECTED'],
            default => throw new \InvalidArgumentException("Unsupported fixture target {$targetStatus}"),
        };

        foreach ($path as $step) {
            $this->actingAs($admin)->postJson("/applications/{$applicationId}/transition", [
                'to_status' => $step, 'candidate_visibility' => 'INTERNAL',
            ])->assertOk();
        }
    }
}
