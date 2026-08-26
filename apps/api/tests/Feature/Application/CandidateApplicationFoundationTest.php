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
 * Candidate Application Foundation v1 — submit, list, detail, withdraw.
 * AD-1 (company VERIFIED gate) and AD-4 (no guessed profile-completeness
 * gate) are approved and CLOSED; AD-2 (reopen) and AD-3 (INTERNAL audience)
 * remain OPEN and deliberately unimplemented in this milestone.
 */
final class CandidateApplicationFoundationTest extends VacancyTestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ---------------------------------------------------------------
    // Routes
    // ---------------------------------------------------------------

    public function test_routes_exist_and_require_authentication(): void
    {
        // Auth middleware runs before route-model binding or any business
        // logic, so no real vacancy/application fixture is needed here —
        // building one via actingAs()-driven helpers would taint this test's
        // "unauthenticated" state for the assertions below.
        $this->postJson('/vacancies/999999/applications', [])
            ->assertUnauthorized()->assertJsonPath('error.code', 'UNAUTHENTICATED');
        $this->getJson('/applications')->assertUnauthorized();
        $this->getJson('/applications/1')->assertUnauthorized();
        $this->postJson('/applications/1/withdraw')->assertUnauthorized();
    }

    public function test_no_reopen_route_exists(): void
    {
        $uris = collect(Route::getRoutes())->map(static fn ($route): string => strtoupper(implode('|', $route->methods())).' '.$route->uri());

        self::assertFalse($uris->contains(fn (string $u): bool => str_contains($u, 'reopen')));
        self::assertNull(Route::getRoutes()->getByName('applications.reopen'));
    }

    // ---------------------------------------------------------------
    // Core success + one lifecycle
    // ---------------------------------------------------------------

    public function test_successful_application_creates_exactly_one_lifecycle(): void
    {
        [$recruiter, $company, $id, $slug] = $this->openVacancy('success@example.test');
        [$candidate, $profileId] = $this->candidate('candidate-success@example.test');

        $response = $this->submit($candidate, $id)->assertCreated();
        $applicationId = (int) $response->json('data.id');

        self::assertSame('APPLIED', $response->json('data.current_status'));
        self::assertNotEmpty($response->json('data.application_code'));

        $row = DB::table('applications')->where('id', $applicationId)->first();
        self::assertSame($profileId, (int) $row->candidate_profile_id);
        self::assertSame($id, (int) $row->vacancy_id);
        self::assertSame('APPLIED', $row->current_status);
        self::assertSame(0, (int) $row->reopen_count);
        self::assertNull($row->last_reopened_at);
        self::assertNotNull($row->first_applied_at);

        self::assertSame(1, DB::table('application_status_histories')->where('application_id', $applicationId)->where('event_type', 'APPLICATION_CREATED')->count());
        self::assertSame(1, DB::table('consents')->where('application_id', $applicationId)->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'application_created', 'object_id' => $applicationId]);

        $consent = DB::table('consents')->where('application_id', $applicationId)->first();
        self::assertSame($company->id, (int) $consent->receiving_company_id);
        self::assertNull($consent->receiving_organizational_unit_id);

        self::assertSame(1, DB::table('notifications')->where('related_object_type', 'application')->where('related_object_id', $applicationId)->count());
        self::assertGreaterThanOrEqual(1, DB::table('email_outbox')->where('related_object_type', 'application')->where('related_object_id', $applicationId)->count());
    }

    // ---------------------------------------------------------------
    // AD-1 — company VERIFIED gate
    // ---------------------------------------------------------------

    public function test_ad1_company_suspended_blocks_apply_with_no_side_effects_and_restore_allows_retry(): void
    {
        [$recruiter, $company, $id] = $this->openVacancy('ad1@example.test');
        [$candidate] = $this->candidate('ad1-candidate@example.test');

        $appsBefore = DB::table('applications')->count();
        $historyBefore = DB::table('application_status_histories')->count();
        $consentsBefore = DB::table('consents')->count();
        $docsBefore = DB::table('application_documents')->count();
        $answersBefore = DB::table('application_screening_answers')->count();
        $auditBefore = DB::table('audit_logs')->where('action', 'application_created')->count();
        $notifBefore = DB::table('notifications')->count();
        $outboxBefore = DB::table('email_outbox')->count();

        DB::table('companies')->where('id', $company->id)->update(['verification_status' => 'SUSPENDED']);

        $this->submit($candidate, $id)->assertStatus(403)->assertJsonPath('error.code', 'VACANCY_COMPANY_NOT_VERIFIED');

        self::assertSame($appsBefore, DB::table('applications')->count());
        self::assertSame($historyBefore, DB::table('application_status_histories')->count());
        self::assertSame($consentsBefore, DB::table('consents')->count());
        self::assertSame($docsBefore, DB::table('application_documents')->count());
        self::assertSame($answersBefore, DB::table('application_screening_answers')->count());
        self::assertSame($auditBefore, DB::table('audit_logs')->where('action', 'application_created')->count());
        self::assertSame($notifBefore, DB::table('notifications')->count());
        self::assertSame($outboxBefore, DB::table('email_outbox')->count());
        self::assertSame('PUBLISHED', DB::table('vacancies')->where('id', $id)->value('current_status'));

        // Partnership state alone has no effect on the gate.
        DB::table('partnerships')->insert([
            'company_id' => $company->id, 'partnership_type' => 'RECRUITMENT', 'agreement_number' => 'AGR-AD1',
            'start_date' => now()->subYear()->toDateString(), 'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->submit($candidate, $id)->assertStatus(403)->assertJsonPath('error.code', 'VACANCY_COMPANY_NOT_VERIFIED');

        // Restore -> fresh submit succeeds if every other condition still holds.
        DB::table('companies')->where('id', $company->id)->update(['verification_status' => 'VERIFIED']);
        $this->submit($candidate, $id)->assertCreated();
    }

    // ---------------------------------------------------------------
    // AD-4 — no guessed profile-completeness gate
    // ---------------------------------------------------------------

    public function test_ad4_incomplete_profile_is_never_rejected_for_completeness(): void
    {
        [$recruiter, $company, $id] = $this->openVacancy('ad4@example.test');
        // A profile with every optional field left null/absent — no headline,
        // no phone, no summary, nothing beyond the bare row.
        [$candidate] = $this->candidate('ad4-candidate@example.test');

        $response = $this->submit($candidate, $id);
        self::assertNotSame('CANDIDATE_PROFILE_INCOMPLETE', $response->json('error.code'));
        $response->assertCreated();
    }

    public function test_missing_candidate_profile_is_still_rejected(): void
    {
        [$recruiter, $company, $id] = $this->openVacancy('no-profile@example.test');
        $user = $this->makeUser('no-profile-candidate@example.test', UserStatus::Active);
        $this->assignRole($user, RoleCode::CandidateExternal);

        $this->actingAs($user)->postJson("/vacancies/{$id}/applications", $this->applicationPayload())
            ->assertStatus(422)->assertJsonPath('error.code', 'CANDIDATE_PROFILE_REQUIRED');
    }

    // ---------------------------------------------------------------
    // Audience matrix
    // ---------------------------------------------------------------

    public function test_public_audience_any_eligible_candidate_succeeds(): void
    {
        [, , $id] = $this->openVacancy('aud-public@example.test', ['target_audience' => 'PUBLIC']);
        [$candidate] = $this->candidate('aud-public-c@example.test');

        $this->submit($candidate, $id)->assertCreated();
    }

    public function test_alumni_only_requires_verified_alumni(): void
    {
        [, , $id] = $this->openVacancy('aud-alumni@example.test', ['target_audience' => 'ALUMNI_ONLY']);

        [$noVerification] = $this->candidate('aud-alumni-none@example.test');
        $this->submit($noVerification, $id)->assertStatus(403)->assertJsonPath('error.code', 'CANDIDATE_NOT_ELIGIBLE');

        [$pending, $pendingProfileId] = $this->candidate('aud-alumni-pending@example.test');
        $this->verification($pendingProfileId, 'ALUMNI', 'PENDING');
        $this->submit($pending, $id)->assertStatus(403)->assertJsonPath('error.code', 'CANDIDATE_NOT_ELIGIBLE');

        [$rejected, $rejectedProfileId] = $this->candidate('aud-alumni-rejected@example.test');
        $this->verification($rejectedProfileId, 'ALUMNI', 'MISMATCH_MANUAL_REVIEW');
        $this->submit($rejected, $id)->assertStatus(403)->assertJsonPath('error.code', 'CANDIDATE_NOT_ELIGIBLE');

        [$verified, $verifiedProfileId] = $this->candidate('aud-alumni-verified@example.test');
        $this->verification($verifiedProfileId, 'ALUMNI', 'VERIFIED');
        $this->submit($verified, $id)->assertCreated();
    }

    public function test_final_year_and_alumni_accepts_either_verified_type(): void
    {
        [, , $idAlumni] = $this->openVacancy('aud-fy-alumni@example.test', ['target_audience' => 'FINAL_YEAR_AND_ALUMNI']);
        [$alumniCandidate, $alumniProfileId] = $this->candidate('aud-fy-alumni-c@example.test');
        $this->verification($alumniProfileId, 'ALUMNI', 'VERIFIED');
        $this->submit($alumniCandidate, $idAlumni)->assertCreated();

        [, , $idFinalYear] = $this->openVacancy('aud-fy-final@example.test', ['target_audience' => 'FINAL_YEAR_AND_ALUMNI']);
        [$finalYearCandidate, $finalYearProfileId] = $this->candidate('aud-fy-final-c@example.test');
        $this->verification($finalYearProfileId, 'FINAL_YEAR_STUDENT', 'VERIFIED');
        $this->submit($finalYearCandidate, $idFinalYear)->assertCreated();

        [, , $idNone] = $this->openVacancy('aud-fy-none@example.test', ['target_audience' => 'FINAL_YEAR_AND_ALUMNI']);
        [$noneCandidate] = $this->candidate('aud-fy-none-c@example.test');
        $this->submit($noneCandidate, $idNone)->assertStatus(403)->assertJsonPath('error.code', 'CANDIDATE_NOT_ELIGIBLE');
    }

    public function test_internal_audience_never_succeeds_in_foundation_v1(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('aud-internal@example.test');
        $close = Carbon::parse('2026-12-01T09:00:00+00:00');
        $id = $this->vacancyAt($recruiter, $company, 'PENDING_REVIEW', [
            'open_at' => $close->copy()->subDays(20)->toIso8601String(), 'close_at' => $close->toIso8601String(),
            'target_audience' => 'INTERNAL',
        ]);
        $approver = $this->moderator('aud-internal-cc@example.test');
        $this->actingAs($approver)->postJson("/vacancies/{$id}/approve")->assertOk();
        DB::table('vacancies')->where('id', $id)->update(['current_status' => 'PUBLISHED', 'published_at' => $close->copy()->subDays(20)]);

        Carbon::setTestNow($close->copy()->subDay());
        [$candidate] = $this->candidate('aud-internal-c@example.test');
        $this->submit($candidate, $id)->assertStatus(403)->assertJsonPath('error.code', 'CANDIDATE_NOT_ELIGIBLE');
        self::assertSame(0, DB::table('applications')->where('vacancy_id', $id)->count());
    }

    // ---------------------------------------------------------------
    // Date boundary
    // ---------------------------------------------------------------

    public function test_date_boundary_is_exact(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('dates@example.test');
        $open = Carbon::parse('2026-12-10T09:00:00+00:00');
        $close = Carbon::parse('2026-12-20T09:00:00+00:00');
        $id = $this->vacancyAt($recruiter, $company, 'PENDING_REVIEW', [
            'open_at' => $open->toIso8601String(), 'close_at' => $close->toIso8601String(),
        ]);
        $approver = $this->moderator('dates-cc@example.test');
        Carbon::setTestNow($open->copy()->subDays(2));
        $this->actingAs($approver)->postJson("/vacancies/{$id}/approve")
            ->assertOk()->assertJsonPath('data.current_status', 'SCHEDULED');

        [$c1] = $this->candidate('dates-c1@example.test');
        Carbon::setTestNow($open->copy()->subSecond());
        $this->submit($c1, $id)->assertStatus(409)->assertJsonPath('error.code', 'VACANCY_NOT_OPEN');

        // Scheduler has not run yet; the vacancy itself is still SCHEDULED at
        // now == open_at. This proves the application gate never trusts the
        // O-7-adjacent scheduler and instead evaluates the frozen window
        // directly against a genuinely PUBLISHED row.
        DB::table('vacancies')->where('id', $id)->update(['current_status' => 'PUBLISHED', 'published_at' => $open]);

        [$c2] = $this->candidate('dates-c2@example.test');
        Carbon::setTestNow($open);
        $this->submit($c2, $id)->assertCreated();

        [$c3] = $this->candidate('dates-c3@example.test');
        Carbon::setTestNow($close->copy()->subSecond());
        $this->submit($c3, $id)->assertCreated();

        [$c4] = $this->candidate('dates-c4@example.test');
        Carbon::setTestNow($close);
        $this->submit($c4, $id)->assertStatus(409)->assertJsonPath('error.code', 'VACANCY_NOT_OPEN');

        [$c5] = $this->candidate('dates-c5@example.test');
        Carbon::setTestNow($close->copy()->addSecond());
        $this->submit($c5, $id)->assertStatus(409)->assertJsonPath('error.code', 'VACANCY_NOT_OPEN');
    }

    public function test_recheck_races_reject_stale_client_state(): void
    {
        [$recruiter, $company, $id] = $this->openVacancy('recheck@example.test');
        [$candidate] = $this->candidate('recheck-c@example.test');

        foreach (['CLOSED', 'EXPIRED', 'SUSPENDED'] as $status) {
            DB::table('vacancies')->where('id', $id)->update(['current_status' => $status]);
            $this->submit($candidate, $id)->assertStatus(409)->assertJsonPath('error.code', 'VACANCY_NOT_OPEN');
        }
        DB::table('vacancies')->where('id', $id)->update(['current_status' => 'PUBLISHED']);

        DB::table('vacancies')->where('id', $id)->update(['application_method' => 'EXTERNAL_ATS', 'external_ats_url' => 'https://ats.example.test/apply']);
        $this->submit($candidate, $id)->assertStatus(422)->assertJsonPath('error.code', 'APPLICATION_NOT_IN_PORTAL_VACANCY');
        DB::table('vacancies')->where('id', $id)->update(['application_method' => 'IN_PORTAL', 'external_ats_url' => null]);

        self::assertSame(0, DB::table('applications')->where('vacancy_id', $id)->count());
        $this->submit($candidate, $id)->assertCreated();
    }

    // ---------------------------------------------------------------
    // EXTERNAL_ATS
    // ---------------------------------------------------------------

    public function test_external_ats_vacancy_cannot_receive_an_internal_application(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('external@example.test');
        $close = Carbon::parse('2026-12-01T09:00:00+00:00');
        $id = $this->vacancyAt($recruiter, $company, 'PENDING_REVIEW', [
            'open_at' => $close->copy()->subDays(20)->toIso8601String(), 'close_at' => $close->toIso8601String(),
            'application_method' => 'EXTERNAL_ATS', 'external_ats_url' => 'https://careers.example.test/job/1',
        ]);
        $approver = $this->moderator('external-cc@example.test');
        $this->actingAs($approver)->postJson("/vacancies/{$id}/approve")->assertOk();
        DB::table('vacancies')->where('id', $id)->update(['current_status' => 'PUBLISHED', 'published_at' => $close->copy()->subDays(20)]);

        Carbon::setTestNow($close->copy()->subDay());
        [$candidate] = $this->candidate('external-c@example.test');
        $eventsBefore = DB::table('external_apply_events')->count();

        $this->submit($candidate, $id)->assertStatus(422)->assertJsonPath('error.code', 'APPLICATION_NOT_IN_PORTAL_VACANCY');

        self::assertSame(0, DB::table('applications')->where('vacancy_id', $id)->count());
        self::assertSame($eventsBefore, DB::table('external_apply_events')->count());
    }

    // ---------------------------------------------------------------
    // Duplicate submit
    // ---------------------------------------------------------------

    public function test_duplicate_submit_returns_already_exists_without_a_second_lifecycle(): void
    {
        [$recruiter, $company, $id] = $this->openVacancy('dup@example.test');
        [$candidate] = $this->candidate('dup-c@example.test');

        $first = $this->submit($candidate, $id)->assertCreated();
        $firstId = (int) $first->json('data.id');

        $second = $this->submit($candidate, $id, ['Idempotency-Key' => null])
            ->assertStatus(409)->assertJsonPath('error.code', 'APPLICATION_ALREADY_EXISTS');
        self::assertSame($firstId, (int) $second->json('error.details.application_id'));

        self::assertSame(1, DB::table('applications')->where('vacancy_id', $id)->count());
        self::assertSame(1, DB::table('application_status_histories')->where('application_id', $firstId)->where('event_type', 'APPLICATION_CREATED')->count());
        self::assertSame(1, DB::table('consents')->where('application_id', $firstId)->count());
    }

    // ---------------------------------------------------------------
    // Idempotency-Key replay
    // ---------------------------------------------------------------

    public function test_idempotency_key_replay_does_not_duplicate_side_effects(): void
    {
        [$recruiter, $company, $id] = $this->openVacancy('idem@example.test');
        [$candidate] = $this->candidate('idem-c@example.test');
        $key = 'idem-key-'.uniqid('', true);

        $first = $this->submit($candidate, $id, ['Idempotency-Key' => $key])->assertCreated();
        $applicationId = (int) $first->json('data.id');

        $replay = $this->submit($candidate, $id, ['Idempotency-Key' => $key])->assertCreated();
        self::assertSame($applicationId, (int) $replay->json('data.id'));

        self::assertSame(1, DB::table('applications')->where('vacancy_id', $id)->count());
        self::assertSame(1, DB::table('application_status_histories')->where('application_id', $applicationId)->count());
        self::assertSame(1, DB::table('consents')->where('application_id', $applicationId)->count());
        self::assertSame(1, DB::table('notifications')->where('related_object_type', 'application')->where('related_object_id', $applicationId)->count());
    }

    public function test_idempotency_key_reused_with_different_payload_is_rejected(): void
    {
        [$recruiter, $company, $id] = $this->openVacancy('idem-reuse@example.test');
        [$candidate] = $this->candidate('idem-reuse-c@example.test');
        $key = 'idem-reuse-key-'.uniqid('', true);

        $this->submit($candidate, $id, ['Idempotency-Key' => $key])->assertCreated();

        [, , $id2] = $this->openVacancy('idem-reuse2@example.test');
        $this->submit($candidate, $id2, ['Idempotency-Key' => $key])
            ->assertStatus(409)->assertJsonPath('error.code', 'IDEMPOTENCY_KEY_REUSED');
    }

    // ---------------------------------------------------------------
    // Screening
    // ---------------------------------------------------------------

    public function test_required_screening_question_must_be_answered(): void
    {
        [$recruiter, $company, $id] = $this->draftVacancyForScreening('screen-required@example.test');
        $this->addScreeningQuestion($recruiter, $id, 'SHORT_TEXT', true);
        $vacancyId = $this->publishDraft($recruiter, $company, $id);

        [$candidate] = $this->candidate('screen-required-c@example.test');
        $this->submit($candidate, $vacancyId, [], ['screening_answers' => []])
            ->assertStatus(422)->assertJsonPath('error.code', 'APPLICATION_SCREENING_INCOMPLETE');
    }

    public function test_invalid_answer_type_and_choice_are_rejected(): void
    {
        [$recruiter, $company, $id] = $this->draftVacancyForScreening('screen-invalid@example.test');
        $numberQuestionId = $this->addScreeningQuestion($recruiter, $id, 'NUMBER', true);
        $choiceQuestionId = $this->addScreeningQuestion($recruiter, $id, 'SINGLE_CHOICE', true, ['A', 'B']);
        $vacancyId = $this->publishDraft($recruiter, $company, $id);

        [$candidate] = $this->candidate('screen-invalid-c@example.test');
        $this->submit($candidate, $vacancyId, [], ['screening_answers' => [
            ['screening_question_id' => $numberQuestionId, 'answer_value' => 'not-a-number'],
            ['screening_question_id' => $choiceQuestionId, 'answer_value' => 'A'],
        ]])->assertStatus(422)->assertJsonPath('error.code', 'APPLICATION_SCREENING_INVALID');

        $this->submit($candidate, $vacancyId, [], ['screening_answers' => [
            ['screening_question_id' => $numberQuestionId, 'answer_value' => 5],
            ['screening_question_id' => $choiceQuestionId, 'answer_value' => 'not-an-option'],
        ]])->assertStatus(422)->assertJsonPath('error.code', 'APPLICATION_SCREENING_INVALID');
    }

    public function test_foreign_vacancy_question_is_rejected(): void
    {
        [$recruiterA, , $idA] = $this->draftVacancyForScreening('screen-foreign-a@example.test');
        $foreignQuestionId = $this->addScreeningQuestion($recruiterA, $idA, 'SHORT_TEXT', false);

        [$recruiterB, $companyB, $idB] = $this->draftVacancyForScreening('screen-foreign-b@example.test');
        $vacancyIdB = $this->publishDraft($recruiterB, $companyB, $idB);

        [$candidate] = $this->candidate('screen-foreign-c@example.test');
        $this->submit($candidate, $vacancyIdB, [], ['screening_answers' => [
            ['screening_question_id' => $foreignQuestionId, 'answer_value' => 'hello'],
        ]])->assertStatus(422)->assertJsonPath('error.code', 'APPLICATION_SCREENING_INVALID');
    }

    public function test_inactive_question_is_not_required_and_answer_to_it_is_rejected(): void
    {
        [$recruiter, $company, $id] = $this->draftVacancyForScreening('screen-inactive@example.test');
        $inactiveId = $this->addScreeningQuestion($recruiter, $id, 'SHORT_TEXT', true);
        $this->actingAs($recruiter)->patchJson("/vacancies/{$id}/screening-questions/{$inactiveId}", ['active' => false])->assertOk();
        $vacancyId = $this->publishDraft($recruiter, $company, $id);

        [$candidate] = $this->candidate('screen-inactive-c@example.test');
        // Not answering the now-inactive (formerly required) question succeeds.
        $this->submit($candidate, $vacancyId)->assertCreated();

        [$candidate2] = $this->candidate('screen-inactive-c2@example.test');
        // Answering an inactive question id is rejected, not silently accepted.
        $this->submit($candidate2, $vacancyId, [], ['screening_answers' => [
            ['screening_question_id' => $inactiveId, 'answer_value' => 'hello'],
        ]])->assertStatus(422)->assertJsonPath('error.code', 'APPLICATION_SCREENING_INVALID');
    }

    public function test_optional_question_may_be_left_blank_and_valid_answers_persist_exactly_once(): void
    {
        [$recruiter, $company, $id] = $this->draftVacancyForScreening('screen-valid@example.test');
        $shortId = $this->addScreeningQuestion($recruiter, $id, 'SHORT_TEXT', true);
        $optionalId = $this->addScreeningQuestion($recruiter, $id, 'YES_NO', false);
        $vacancyId = $this->publishDraft($recruiter, $company, $id);

        [$candidate] = $this->candidate('screen-valid-c@example.test');
        $response = $this->submit($candidate, $vacancyId, [], ['screening_answers' => [
            ['screening_question_id' => $shortId, 'answer_value' => 'Jawaban saya'],
        ]])->assertCreated();

        $applicationId = (int) $response->json('data.id');
        self::assertSame(1, DB::table('application_screening_answers')->where('application_id', $applicationId)->count());
        $answer = DB::table('application_screening_answers')->where('application_id', $applicationId)->first();
        self::assertSame($shortId, (int) $answer->screening_question_id);
        self::assertSame('Jawaban saya', $answer->answer_text);
    }

    // ---------------------------------------------------------------
    // Documents
    // ---------------------------------------------------------------

    public function test_own_active_document_shares_successfully_with_a_snapshot(): void
    {
        [$recruiter, $company, $id] = $this->openVacancy('doc-own@example.test');
        [$candidate, $profileId] = $this->candidate('doc-own-c@example.test');
        $docId = $this->candidateDocument($profileId, 'resume.pdf');

        $response = $this->submit($candidate, $id, [], ['document_ids' => [$docId]])->assertCreated();
        $applicationId = (int) $response->json('data.id');

        $share = DB::table('application_documents')->where('application_id', $applicationId)->first();
        self::assertNotNull($share);
        self::assertSame($docId, (int) $share->candidate_document_id);
        self::assertSame('resume.pdf', $share->snapshot_name);
        self::assertNotEmpty($share->snapshot_storage_reference);

        // Later candidate-document changes never rewrite the historical snapshot.
        DB::table('candidate_documents')->where('id', $docId)->update(['display_name' => 'renamed-later.pdf']);
        $shareAfter = DB::table('application_documents')->where('application_id', $applicationId)->first();
        self::assertSame('resume.pdf', $shareAfter->snapshot_name);
    }

    public function test_foreign_candidate_document_is_rejected(): void
    {
        [$recruiter, $company, $id] = $this->openVacancy('doc-foreign@example.test');
        [$candidate] = $this->candidate('doc-foreign-c@example.test');
        [, $otherProfileId] = $this->candidate('doc-foreign-owner@example.test');
        $foreignDocId = $this->candidateDocument($otherProfileId, 'other.pdf');

        $this->submit($candidate, $id, [], ['document_ids' => [$foreignDocId]])
            ->assertStatus(403)->assertJsonPath('error.code', 'DOCUMENT_NOT_OWNED');
        self::assertSame(0, DB::table('applications')->where('vacancy_id', $id)->count());
    }

    public function test_archived_document_is_rejected(): void
    {
        [$recruiter, $company, $id] = $this->openVacancy('doc-archived@example.test');
        [$candidate, $profileId] = $this->candidate('doc-archived-c@example.test');
        $docId = $this->candidateDocument($profileId, 'archived.pdf', archived: true);

        $this->submit($candidate, $id, [], ['document_ids' => [$docId]])
            ->assertStatus(422)->assertJsonPath('error.code', 'DOCUMENT_ARCHIVED');
    }

    public function test_application_may_submit_with_zero_documents(): void
    {
        [$recruiter, $company, $id] = $this->openVacancy('doc-none@example.test');
        [$candidate] = $this->candidate('doc-none-c@example.test');

        $this->submit($candidate, $id)->assertCreated();
    }

    // ---------------------------------------------------------------
    // Consent
    // ---------------------------------------------------------------

    public function test_missing_consent_acceptance_is_rejected(): void
    {
        [$recruiter, $company, $id] = $this->openVacancy('consent-missing@example.test');
        [$candidate] = $this->candidate('consent-missing-c@example.test');

        $this->submit($candidate, $id, [], ['consent' => ['consent_version' => 'x', 'consent_text_hash_reference' => 'y', 'accepted' => false]])
            ->assertStatus(422)->assertJsonPath('error.code', 'APPLICATION_CONSENT_REQUIRED');
    }

    public function test_unknown_consent_version_is_rejected(): void
    {
        [$recruiter, $company, $id] = $this->openVacancy('consent-version@example.test');
        [$candidate] = $this->candidate('consent-version-c@example.test');

        $this->submit($candidate, $id, [], ['consent' => ['consent_version' => 'NOT_A_REAL_VERSION', 'consent_text_hash_reference' => 'hash', 'accepted' => true]])
            ->assertStatus(422)->assertJsonPath('error.code', 'CONSENT_VERSION_UNKNOWN');
    }

    public function test_client_supplied_receiver_is_rejected(): void
    {
        [$recruiter, $company, $id] = $this->openVacancy('consent-receiver@example.test');
        [$candidate] = $this->candidate('consent-receiver-c@example.test');

        $this->submit($candidate, $id, [], ['receiving_company_id' => 999999])
            ->assertStatus(422)->assertJsonPath('error.code', 'CONSENT_RECEIVER_MISMATCH');
    }

    // ---------------------------------------------------------------
    // List
    // ---------------------------------------------------------------

    public function test_candidate_sees_only_own_applications(): void
    {
        [$recruiter, $company, $id] = $this->openVacancy('list-own@example.test');
        [$candidateA] = $this->candidate('list-own-a@example.test');
        [$candidateB] = $this->candidate('list-own-b@example.test');

        $this->submit($candidateA, $id)->assertCreated();
        $this->submit($candidateB, $id)->assertCreated();

        $response = $this->actingAs($candidateA)->getJson('/applications')->assertOk();
        self::assertCount(1, $response->json('data.items'));
    }

    public function test_unsupported_filter_is_rejected(): void
    {
        [$candidate] = $this->candidate('list-filter-c@example.test');
        $this->actingAs($candidate)->getJson('/applications?not_a_real_filter=1')
            ->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    // ---------------------------------------------------------------
    // Detail
    // ---------------------------------------------------------------

    public function test_detail_owner_sees_own_history_documents_and_answers_never_recruiter_notes(): void
    {
        [$recruiter, $company, $id] = $this->draftVacancyForScreening('detail@example.test');
        $questionId = $this->addScreeningQuestion($recruiter, $id, 'SHORT_TEXT', true);
        $vacancyId = $this->publishDraft($recruiter, $company, $id);

        [$candidate, $profileId] = $this->candidate('detail-c@example.test');
        $docId = $this->candidateDocument($profileId, 'cv.pdf');

        $response = $this->submit($candidate, $vacancyId, [], [
            'document_ids' => [$docId],
            'screening_answers' => [['screening_question_id' => $questionId, 'answer_value' => 'Jawaban detail']],
        ])->assertCreated();
        $applicationId = (int) $response->json('data.id');

        $detail = $this->actingAs($candidate)
            ->getJson("/applications/{$applicationId}?include=history,documents,screening_answers")
            ->assertOk();

        self::assertNotEmpty($detail->json('data.history'));
        self::assertSame('APPLICATION_CREATED', $detail->json('data.history.0.event_type'));
        self::assertSame($docId, $detail->json('data.documents.0.candidate_document_id'));
        self::assertSame('Jawaban detail', $detail->json('data.screening_answers.0.answer_text'));

        $body = $detail->getContent();
        foreach (['internal_note', 'reviewer_user_id', 'recruiter_visible_note', 'legal_identifier'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $body);
        }
    }

    public function test_other_candidate_gets_404_never_403(): void
    {
        [$recruiter, $company, $id] = $this->openVacancy('detail-other@example.test');
        [$owner] = $this->candidate('detail-other-owner@example.test');
        [$intruder] = $this->candidate('detail-other-intruder@example.test');

        $response = $this->submit($owner, $id)->assertCreated();
        $applicationId = (int) $response->json('data.id');

        $this->actingAs($intruder)->getJson("/applications/{$applicationId}")
            ->assertNotFound()->assertJsonPath('error.code', 'NOT_FOUND');
    }

    // ---------------------------------------------------------------
    // Withdraw
    // ---------------------------------------------------------------

    public function test_own_active_application_withdraws_and_preserves_everything(): void
    {
        [$recruiter, $company, $id] = $this->openVacancy('withdraw@example.test');
        [$candidate, $profileId] = $this->candidate('withdraw-c@example.test');
        $docId = $this->candidateDocument($profileId, 'wd.pdf');

        $response = $this->submit($candidate, $id, [], ['document_ids' => [$docId]])->assertCreated();
        $applicationId = (int) $response->json('data.id');

        $this->actingAs($candidate)->postJson("/applications/{$applicationId}/withdraw", ['reason' => 'Berubah pikiran'])
            ->assertOk()->assertJsonPath('data.current_status', 'WITHDRAWN');

        $row = DB::table('applications')->where('id', $applicationId)->first();
        self::assertSame('WITHDRAWN', $row->current_status);
        self::assertNotNull($row->withdrawn_at);
        self::assertSame('Berubah pikiran', $row->withdrawal_reason);

        self::assertSame(1, DB::table('application_status_histories')->where('application_id', $applicationId)->where('event_type', 'WITHDRAWN')->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'application_withdrawn', 'object_id' => $applicationId]);
        self::assertSame(1, DB::table('application_documents')->where('application_id', $applicationId)->count());
        self::assertSame(1, DB::table('consents')->where('application_id', $applicationId)->count());

        $this->actingAs($candidate)->postJson("/applications/{$applicationId}/withdraw")
            ->assertStatus(409)->assertJsonPath('error.code', 'APPLICATION_ALREADY_WITHDRAWN');
    }

    public function test_other_candidate_cannot_withdraw(): void
    {
        [$recruiter, $company, $id] = $this->openVacancy('withdraw-other@example.test');
        [$owner] = $this->candidate('withdraw-other-owner@example.test');
        [$intruder] = $this->candidate('withdraw-other-intruder@example.test');

        $response = $this->submit($owner, $id)->assertCreated();
        $applicationId = (int) $response->json('data.id');

        $this->actingAs($intruder)->postJson("/applications/{$applicationId}/withdraw")
            ->assertNotFound()->assertJsonPath('error.code', 'NOT_FOUND');
    }

    // ---------------------------------------------------------------
    // Public discovery regression
    // ---------------------------------------------------------------

    public function test_public_discovery_routes_and_visibility_are_unaffected(): void
    {
        [$recruiter, $company, $id, $slug] = $this->openVacancy('regression@example.test');

        $this->getJson('/api/v1/public/vacancies')->assertOk();
        $this->getJson("/api/v1/public/vacancies/{$slug}")->assertOk();
        $this->get('/lowongan')->assertOk();
        $this->get("/lowongan/{$slug}")->assertOk();

        $uris = collect(Route::getRoutes())->map(static fn ($route): string => $route->uri());
        self::assertTrue($uris->contains('api/v1/public/vacancies'));
        self::assertTrue($uris->contains('lowongan'));
    }

    // ---------------------------------------------------------------
    // Fixtures
    // ---------------------------------------------------------------

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

    private function verification(int $profileId, string $type, string $status): void
    {
        DB::table('candidate_verifications')->insert([
            'candidate_profile_id' => $profileId, 'verification_type' => $type, 'status' => $status,
            'rejection_reason' => $status === 'MISMATCH_MANUAL_REVIEW' ? 'Data tidak cocok.' : null,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function candidateDocument(int $profileId, string $displayName, bool $archived = false): int
    {
        return (int) DB::table('candidate_documents')->insertGetId([
            'candidate_profile_id' => $profileId, 'document_type' => 'CV', 'display_name' => $displayName,
            'storage_reference' => 'private/candidates/'.uniqid('', true).'.pdf', 'mime_type' => 'application/pdf',
            'size' => 12345, 'uploaded_at' => now(), 'archived_at' => $archived ? now() : null,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** A PUBLISHED, active, VERIFIED-company, IN_PORTAL vacancy ready to receive applications. */
    private function openVacancy(string $email, array $overrides = []): array
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter($email);
        $close = Carbon::parse('2026-12-01T09:00:00+00:00');
        $open = $close->copy()->subDays(20);
        $id = $this->vacancyAt($recruiter, $company, 'PUBLISHED', array_merge([
            'open_at' => $open->toIso8601String(), 'close_at' => $close->toIso8601String(),
        ], $overrides));
        DB::table('vacancies')->where('id', $id)->update(['published_at' => $open]);
        $slug = DB::table('vacancies')->where('id', $id)->value('slug');

        Carbon::setTestNow($close->copy()->subDay());

        return [$recruiter, $company, $id, $slug];
    }

    /** A DRAFT vacancy left editable so screening questions can be attached before publication. */
    private function draftVacancyForScreening(string $email): array
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter($email);
        $id = $this->createVacancy($recruiter, $company, $this->submittablePayload());

        return [$recruiter, $company, $id];
    }

    private function addScreeningQuestion(User $recruiter, int $vacancyId, string $type, bool $required, ?array $options = null): int
    {
        $payload = [
            'question_text' => 'Pertanyaan '.$type, 'question_type' => $type,
            'required' => $required, 'sort_order' => 0, 'active' => true,
        ];
        if ($options !== null) {
            $payload['options_definition'] = $options;
        }

        $response = $this->actingAs($recruiter)->postJson("/vacancies/{$vacancyId}/screening-questions", $payload)->assertCreated();

        return (int) $response->json('data.id');
    }

    private function publishDraft(User $recruiter, $company, int $vacancyId): int
    {
        $this->actingAs($recruiter)->postJson("/vacancies/{$vacancyId}/submit-review")->assertOk();
        $close = Carbon::parse('2026-12-05T09:00:00+00:00');
        $approver = $this->moderator('approver-'.$vacancyId.'@example.test');
        $this->actingAs($approver)->postJson("/vacancies/{$vacancyId}/approve")->assertOk();
        DB::table('vacancies')->where('id', $vacancyId)->update([
            'current_status' => 'PUBLISHED',
            'published_at' => $close->copy()->subDays(20), 'open_at' => $close->copy()->subDays(20), 'close_at' => $close,
        ]);
        Carbon::setTestNow($close->copy()->subDay());

        return $vacancyId;
    }

    /** @param array<string, mixed> $headers @param array<string, mixed> $overrides */
    private function submit(User $candidate, int $vacancyId, array $headers = [], array $overrides = [])
    {
        $headers = array_filter($headers, fn ($v) => $v !== null);

        return $this->actingAs($candidate)->withHeaders($headers)
            ->postJson("/vacancies/{$vacancyId}/applications", $this->applicationPayload($overrides));
    }

    /** @param array<string, mixed> $overrides */
    private function applicationPayload(array $overrides = []): array
    {
        return array_merge([
            'consent' => [
                'consent_version' => \App\Domains\Application\Support\ApplicationConsentVersion::CURRENT,
                'consent_text_hash_reference' => 'sha256:'.str_repeat('a', 64),
                'accepted' => true,
            ],
        ], $overrides);
    }
}
