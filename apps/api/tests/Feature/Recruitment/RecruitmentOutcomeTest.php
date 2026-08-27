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
 * Recruitment Outcome Foundation v1 — create, list, correction (`PATCH`).
 * OC-1, RC-2 both approved and CLOSED. `INTERNAL_APPLICATION` only.
 * `EXTERNAL_APPLY` runtime, Career Center alumni write, Campus, automatic
 * outcome creation/correction, delete, history table, approval workflow,
 * bulk mutation, and the reminder scheduler all remain deliberately
 * unimplemented. `GET /recruitment-outcomes/incomplete` is unrouted (H-5).
 */
final class RecruitmentOutcomeTest extends VacancyTestCase
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

        self::assertTrue($registered->contains('GET|HEAD recruitment-outcomes'));
        self::assertTrue($registered->contains('POST recruitment-outcomes'));
        self::assertTrue($registered->contains('PATCH recruitment-outcomes/{outcome}'));

        self::assertFalse($registered->contains(fn (string $r): bool => str_contains($r, 'incomplete')));
        self::assertFalse($registered->contains(fn (string $r): bool => str_contains($r, 'DELETE') && str_contains($r, 'recruitment-outcome')));
        self::assertFalse($registered->contains(fn (string $r): bool => str_contains($r, 'api/v1') && str_contains($r, 'recruitment-outcome')));
    }

    // ---------------------------------------------------------------
    // Create — authorization
    // ---------------------------------------------------------------

    public function test_recruiter_admin_and_super_admin_can_create(): void
    {
        [$admin, $company, $vacancyId] = $this->openVacancy('outcome-create@example.test');
        [$candidate] = $this->candidate('outcome-create-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);

        $response = $this->actingAs($admin)->postJson('/recruitment-outcomes', $this->outcomePayload($applicationId))->assertCreated();
        self::assertSame('INTERNAL_APPLICATION', $response->json('data.source_type'));
        self::assertSame($applicationId, $response->json('data.application_id'));
        self::assertNull($response->json('data.external_apply_event_id'));
        self::assertSame('HIRED', $response->json('data.outcome'));
        self::assertSame((int) $admin->getKey(), $response->json('data.confirmed_by'));
        self::assertNotNull($response->json('data.confirmed_at'));

        self::assertSame(1, DB::table('recruitment_outcomes')->where('application_id', $applicationId)->count());
        self::assertSame(1, DB::table('audit_logs')->where('action', 'recruitment_outcome_recorded')
            ->where('object_id', $response->json('data.id'))->count());

        $recruiter = $this->recruiterMember($company->id, 'outcome-create-recruiter@example.test');
        $applicationId2 = $this->submitApplication($this->candidate('outcome-create-c2@example.test')[0], $vacancyId);
        $this->actingAs($recruiter)->postJson('/recruitment-outcomes', $this->outcomePayload($applicationId2, ['outcome' => 'REJECTED']))->assertCreated();

        $superAdmin = $this->makeUser('outcome-create-super@example.test', UserStatus::Active);
        $this->assignRole($superAdmin, RoleCode::SuperAdmin);
        $applicationId3 = $this->submitApplication($this->candidate('outcome-create-c3@example.test')[0], $vacancyId);
        $this->actingAs($superAdmin)->postJson('/recruitment-outcomes', $this->outcomePayload($applicationId3, ['outcome' => 'WITHDRAWN']))->assertCreated();
    }

    public function test_revoked_membership_cannot_create(): void
    {
        [, $company, $vacancyId] = $this->openVacancy('outcome-revoked@example.test');
        [$candidate] = $this->candidate('outcome-revoked-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);

        $revoked = $this->recruiterMember($company->id, 'outcome-revoked-recruiter@example.test');
        DB::table('company_members')->where('company_id', $company->id)->where('user_id', $revoked->id)
            ->update(['status' => 'REVOKED', 'revoked_at' => now()]);

        $this->actingAs($revoked)->postJson('/recruitment-outcomes', $this->outcomePayload($applicationId))
            ->assertStatus(404);
        self::assertSame(0, DB::table('recruitment_outcomes')->where('application_id', $applicationId)->count());
    }

    public function test_cross_company_recruiter_gets_enumeration_safe_404(): void
    {
        [, , $vacancyId] = $this->openVacancy('outcome-cross-a@example.test');
        [$candidate] = $this->candidate('outcome-cross-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);

        [$otherRecruiter] = $this->verifiedCompanyWithRecruiter('outcome-cross-b@example.test');

        $response = $this->actingAs($otherRecruiter)->postJson('/recruitment-outcomes', $this->outcomePayload($applicationId));
        $response->assertStatus(404);
        self::assertSame('NOT_FOUND', $response->json('error.code'));
        self::assertSame(0, DB::table('recruitment_outcomes')->where('application_id', $applicationId)->count());
    }

    public function test_career_center_selector_auditor_and_candidate_denied_write(): void
    {
        [$admin, , $vacancyId] = $this->openVacancy('outcome-deny@example.test');
        [$candidate] = $this->candidate('outcome-deny-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);

        $careerCenter = $this->moderator('outcome-deny-cc@example.test', RoleCode::CareerCenterStaff);
        $auditor = $this->moderator('outcome-deny-auditor@example.test', RoleCode::Auditor);
        $selector = $this->moderator('outcome-deny-selector@example.test', RoleCode::Selector);

        foreach ([$careerCenter, $auditor, $selector, $candidate] as $actor) {
            $this->actingAs($actor)->postJson('/recruitment-outcomes', $this->outcomePayload($applicationId))
                ->assertStatus(403)->assertJsonPath('error.code', 'AUTH_FORBIDDEN');
        }
        self::assertSame(0, DB::table('recruitment_outcomes')->where('application_id', $applicationId)->count());

        // PATCH DENY too, once a legitimate outcome exists.
        $outcomeId = $this->actingAs($admin)->postJson('/recruitment-outcomes', $this->outcomePayload($applicationId))
            ->assertCreated()->json('data.id');
        foreach ([$careerCenter, $auditor, $selector, $candidate] as $actor) {
            $this->actingAs($actor)->patchJson("/recruitment-outcomes/{$outcomeId}", ['notes' => 'nope'])
                ->assertStatus(403)->assertJsonPath('error.code', 'AUTH_FORBIDDEN');
        }
    }

    // ---------------------------------------------------------------
    // OC-1 vocabulary
    // ---------------------------------------------------------------

    public function test_oc1_vocabulary_allows_exact_four_values(): void
    {
        [$admin, , $vacancyId] = $this->openVacancy('outcome-vocab-ok@example.test');

        foreach (['HIRED', 'REJECTED', 'WITHDRAWN', 'NO_SHOW'] as $index => $value) {
            [$candidate] = $this->candidate("outcome-vocab-ok-c{$index}@example.test");
            $applicationId = $this->submitApplication($candidate, $vacancyId);

            $this->actingAs($admin)->postJson('/recruitment-outcomes', $this->outcomePayload($applicationId, ['outcome' => $value]))
                ->assertCreated()->assertJsonPath('data.outcome', $value);
        }
    }

    public function test_oc1_vocabulary_denies_arbitrary_values(): void
    {
        [$admin, , $vacancyId] = $this->openVacancy('outcome-vocab-bad@example.test');

        foreach (['ACCEPTED', 'DECLINED', 'FAILED', 'SUCCESS', 'POSITION_FILLED', 'OTHER', 'EXPIRED', 'random-string'] as $index => $value) {
            [$candidate] = $this->candidate("outcome-vocab-bad-c{$index}@example.test");
            $applicationId = $this->submitApplication($candidate, $vacancyId);

            $this->actingAs($admin)->postJson('/recruitment-outcomes', $this->outcomePayload($applicationId, ['outcome' => $value]))
                ->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_FAILED');
            self::assertSame(0, DB::table('recruitment_outcomes')->where('application_id', $applicationId)->count());
        }
    }

    // ---------------------------------------------------------------
    // Source type / XOR
    // ---------------------------------------------------------------

    public function test_source_type_external_apply_denied_and_event_id_prohibited(): void
    {
        [$admin, , $vacancyId] = $this->openVacancy('outcome-source@example.test');
        [$candidate] = $this->candidate('outcome-source-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);

        $this->actingAs($admin)->postJson('/recruitment-outcomes', $this->outcomePayload($applicationId, [
            'source_type' => 'EXTERNAL_APPLY',
        ]))->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_FAILED');

        $this->actingAs($admin)->postJson('/recruitment-outcomes', $this->outcomePayload($applicationId, [
            'external_apply_event_id' => 999,
        ]))->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_FAILED');

        self::assertSame(0, DB::table('recruitment_outcomes')->where('application_id', $applicationId)->count());
    }

    // ---------------------------------------------------------------
    // Duplicate
    // ---------------------------------------------------------------

    public function test_duplicate_outcome_rejected(): void
    {
        [$admin, , $vacancyId] = $this->openVacancy('outcome-dup@example.test');
        [$candidate] = $this->candidate('outcome-dup-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);

        $this->actingAs($admin)->postJson('/recruitment-outcomes', $this->outcomePayload($applicationId))->assertCreated();
        $this->actingAs($admin)->postJson('/recruitment-outcomes', $this->outcomePayload($applicationId, ['outcome' => 'REJECTED']))
            ->assertStatus(409)->assertJsonPath('error.code', 'OUTCOME_ALREADY_RECORDED');

        self::assertSame(1, DB::table('recruitment_outcomes')->where('application_id', $applicationId)->count());
        self::assertSame(1, DB::table('audit_logs')->where('action', 'recruitment_outcome_recorded')->count());
    }

    // ---------------------------------------------------------------
    // Idempotency
    // ---------------------------------------------------------------

    public function test_create_idempotency_replay(): void
    {
        [$admin, , $vacancyId] = $this->openVacancy('outcome-idem@example.test');
        [$candidate] = $this->candidate('outcome-idem-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);
        $key = 'outcome-idem-'.uniqid('', true);

        $first = $this->actingAs($admin)->postJson('/recruitment-outcomes', $this->outcomePayload($applicationId), ['Idempotency-Key' => $key])->assertCreated();
        $replay = $this->actingAs($admin)->postJson('/recruitment-outcomes', $this->outcomePayload($applicationId), ['Idempotency-Key' => $key])->assertCreated();

        self::assertEquals($first->json('data'), $replay->json('data'));
        self::assertSame(1, DB::table('recruitment_outcomes')->where('application_id', $applicationId)->count());
        self::assertSame(1, DB::table('audit_logs')->where('action', 'recruitment_outcome_recorded')->count());
    }

    // ---------------------------------------------------------------
    // List
    // ---------------------------------------------------------------

    public function test_list_company_scope(): void
    {
        [$adminA, , $vacancyIdA] = $this->openVacancy('outcome-list-a@example.test');
        [$adminB, , $vacancyIdB] = $this->openVacancy('outcome-list-b@example.test');

        $applicationA = $this->submitApplication($this->candidate('outcome-list-ca@example.test')[0], $vacancyIdA);
        $applicationB = $this->submitApplication($this->candidate('outcome-list-cb@example.test')[0], $vacancyIdB);

        $this->actingAs($adminA)->postJson('/recruitment-outcomes', $this->outcomePayload($applicationA))->assertCreated();
        $this->actingAs($adminB)->postJson('/recruitment-outcomes', $this->outcomePayload($applicationB))->assertCreated();

        $listA = $this->actingAs($adminA)->getJson('/recruitment-outcomes')->assertOk();
        $ids = collect($listA->json('data.items'))->pluck('application_id')->all();
        self::assertContains($applicationA, $ids);
        self::assertNotContains($applicationB, $ids);
    }

    public function test_list_super_admin_and_auditor_allowed_candidate_and_selector_denied(): void
    {
        [$admin, , $vacancyId] = $this->openVacancy('outcome-list-roles@example.test');
        [$candidate] = $this->candidate('outcome-list-roles-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);
        $this->actingAs($admin)->postJson('/recruitment-outcomes', $this->outcomePayload($applicationId))->assertCreated();

        $superAdmin = $this->makeUser('outcome-list-roles-super@example.test', UserStatus::Active);
        $this->assignRole($superAdmin, RoleCode::SuperAdmin);
        $this->actingAs($superAdmin)->getJson('/recruitment-outcomes')->assertOk();

        $auditor = $this->moderator('outcome-list-roles-auditor@example.test', RoleCode::Auditor);
        $this->actingAs($auditor)->getJson('/recruitment-outcomes')->assertOk();

        $selector = $this->moderator('outcome-list-roles-selector@example.test', RoleCode::Selector);
        $this->actingAs($selector)->getJson('/recruitment-outcomes')->assertStatus(403);

        $this->actingAs($candidate)->getJson('/recruitment-outcomes')->assertStatus(403);
    }

    // ---------------------------------------------------------------
    // Patch
    // ---------------------------------------------------------------

    public function test_patch_mutable_fields_and_source_immutable(): void
    {
        [$admin, , $vacancyId] = $this->openVacancy('outcome-patch@example.test');
        [$candidate] = $this->candidate('outcome-patch-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);
        $outcomeId = $this->actingAs($admin)->postJson('/recruitment-outcomes', $this->outcomePayload($applicationId))
            ->assertCreated()->json('data.id');

        $response = $this->actingAs($admin)->patchJson("/recruitment-outcomes/{$outcomeId}", [
            'outcome' => 'REJECTED',
            'reported_by_source' => 'INTEGRATION',
            'notes' => 'Corrected after review.',
        ])->assertOk();

        self::assertSame('REJECTED', $response->json('data.outcome'));
        self::assertSame('INTEGRATION', $response->json('data.reported_by_source'));
        self::assertSame('Corrected after review.', $response->json('data.notes'));
        self::assertSame($applicationId, $response->json('data.application_id'));
        self::assertSame('INTERNAL_APPLICATION', $response->json('data.source_type'));

        self::assertSame(1, DB::table('audit_logs')->where('action', 'recruitment_outcome_updated')
            ->where('object_id', $outcomeId)->count());
        self::assertSame('APPLIED', DB::table('applications')->where('id', $applicationId)->value('current_status'));

        foreach (['source_type' => 'EXTERNAL_APPLY', 'application_id' => 999999, 'external_apply_event_id' => 1] as $field => $value) {
            $this->actingAs($admin)->patchJson("/recruitment-outcomes/{$outcomeId}", [$field => $value])
                ->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_FAILED');
        }
        self::assertSame($applicationId, DB::table('recruitment_outcomes')->where('id', $outcomeId)->value('application_id'));
    }

    public function test_patch_invalid_outcome_value_rejected(): void
    {
        [$admin, , $vacancyId] = $this->openVacancy('outcome-patch-bad@example.test');
        [$candidate] = $this->candidate('outcome-patch-bad-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);
        $outcomeId = $this->actingAs($admin)->postJson('/recruitment-outcomes', $this->outcomePayload($applicationId))
            ->assertCreated()->json('data.id');

        $this->actingAs($admin)->patchJson("/recruitment-outcomes/{$outcomeId}", ['outcome' => 'ACCEPTED'])
            ->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_FAILED');
        self::assertSame('HIRED', DB::table('recruitment_outcomes')->where('id', $outcomeId)->value('outcome'));
    }

    // ---------------------------------------------------------------
    // Independence
    // ---------------------------------------------------------------

    public function test_no_automatic_outcome_on_offer_accept_then_explicit_create(): void
    {
        [$admin, , $vacancyId] = $this->openVacancy('outcome-independence@example.test');
        [$candidate] = $this->candidate('outcome-independence-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);

        $offerId = (int) $this->actingAs($admin)->postJson("/applications/{$applicationId}/offers", ['note' => 'Selamat.'])
            ->assertCreated()->json('data.id');
        $this->actingAs($admin)->withHeaders(['Idempotency-Key' => 'outcome-independence-send-'.uniqid('', true)])
            ->postJson("/offers/{$offerId}/send", [])->assertOk();
        $this->actingAs($candidate)->postJson("/offers/{$offerId}/accept", [])->assertOk();

        self::assertSame('HIRED', DB::table('applications')->where('id', $applicationId)->value('current_status'));
        self::assertSame(0, DB::table('recruitment_outcomes')->where('application_id', $applicationId)->count());

        $this->actingAs($admin)->postJson('/recruitment-outcomes', $this->outcomePayload($applicationId))->assertCreated();
        self::assertSame(1, DB::table('recruitment_outcomes')->where('application_id', $applicationId)->count());
        // Recording the outcome never mutates application status further.
        self::assertSame('HIRED', DB::table('applications')->where('id', $applicationId)->value('current_status'));
    }

    public function test_outcome_recordable_for_terminal_application(): void
    {
        [$admin, , $vacancyId] = $this->openVacancy('outcome-terminal@example.test');
        [$candidate] = $this->candidate('outcome-terminal-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);
        DB::table('applications')->where('id', $applicationId)->update(['current_status' => 'REJECTED']);

        $this->actingAs($admin)->postJson('/recruitment-outcomes', $this->outcomePayload($applicationId, ['outcome' => 'REJECTED']))
            ->assertCreated();
        self::assertSame(1, DB::table('recruitment_outcomes')->where('application_id', $applicationId)->count());
    }

    public function test_no_notifications_or_outbox_on_create_or_update(): void
    {
        [$admin, , $vacancyId] = $this->openVacancy('outcome-notify@example.test');
        [$candidate] = $this->candidate('outcome-notify-c@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyId);

        $notificationsBefore = DB::table('notifications')->count();
        $outboxBefore = DB::table('email_outbox')->count();

        $outcomeId = $this->actingAs($admin)->postJson('/recruitment-outcomes', $this->outcomePayload($applicationId))
            ->assertCreated()->json('data.id');
        $this->actingAs($admin)->patchJson("/recruitment-outcomes/{$outcomeId}", ['notes' => 'x'])->assertOk();

        self::assertSame($notificationsBefore, DB::table('notifications')->count());
        self::assertSame($outboxBefore, DB::table('email_outbox')->count());
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
        $approver = $this->moderator('approver-of-'.$vacancyId.'@example.test');
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

    /** @return array<string, mixed> */
    private function outcomePayload(int $applicationId, array $overrides = []): array
    {
        return array_merge([
            'source_type' => 'INTERNAL_APPLICATION',
            'application_id' => $applicationId,
            'outcome' => 'HIRED',
            'reported_by_source' => 'COMPANY',
            'notes' => null,
        ], $overrides);
    }
}
