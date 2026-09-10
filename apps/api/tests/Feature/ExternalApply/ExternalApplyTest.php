<?php

declare(strict_types=1);

namespace Tests\Feature\ExternalApply;

use App\Domains\Application\Support\ApplicationConsentVersion;
use App\Domains\Identity\Enums\RoleCode;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Vacancy\VacancyTestCase;

/**
 * GAP-009 — External Apply runtime (API_CONTRACT.md Part VII · FR-EXT-001..004
 * · INV-012, INV-024). Proves: start records an event and no application;
 * the destination URL comes only from the stored vacancy; confirm accepts a
 * legitimate source and is single-shot; history is candidate-scoped.
 */
final class ExternalApplyTest extends VacancyTestCase
{
    private const ATS_URL = 'https://ats.partner.example.test/jobs/42';

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** @return array{User, \App\Domains\Company\Models\Company, int} */
    private function externalVacancy(string $email, array $overrides = []): array
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter($email);
        $close = Carbon::parse('2026-12-01T09:00:00+00:00');
        $open = $close->copy()->subDays(20);
        $vacancyId = $this->vacancyAt($recruiter, $company, 'PUBLISHED', array_merge([
            'application_method' => 'EXTERNAL_ATS',
            'external_ats_url' => self::ATS_URL,
            'open_at' => $open->toIso8601String(),
            'close_at' => $close->toIso8601String(),
        ], $overrides));
        DB::table('vacancies')->where('id', $vacancyId)->update(['published_at' => $open]);
        Carbon::setTestNow($close->copy()->subDay());

        return [$recruiter, $company, $vacancyId];
    }

    private function candidate(string $email): User
    {
        $user = $this->makeUser($email, UserStatus::Active);
        $this->assignRole($user, RoleCode::CandidateExternal);
        DB::table('candidate_profiles')->insert([
            'user_id' => $user->id, 'current_candidate_type' => 'EXTERNAL',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $user;
    }

    /** @return array<string, mixed> */
    private function consent(): array
    {
        return [
            'consent_version' => ApplicationConsentVersion::CURRENT,
            'consent_text_hash_reference' => 'sha256:'.str_repeat('a', 64),
            'accepted' => true,
        ];
    }

    public function test_start_records_an_event_and_never_an_application(): void
    {
        [, , $vacancyId] = $this->externalVacancy('ext-start@example.test');
        $candidate = $this->candidate('ext-start-c@example.test');

        $response = $this->actingAs($candidate)->postJson("/vacancies/{$vacancyId}/external-apply/start", [
            'consent' => $this->consent(),
            // A client-supplied URL must be ignored.
            'destination_url' => 'https://evil.example.test/phish',
            'external_ats_url' => 'https://evil.example.test/phish',
        ])->assertCreated();

        $response->assertJsonPath('data.event_type', 'EXTERNAL_APPLY_STARTED')
            ->assertJsonPath('data.confirmation_status', 'PENDING')
            ->assertJsonPath('data.destination_url', self::ATS_URL);

        $eventId = (int) $response->json('data.external_apply_event_id');
        $this->assertDatabaseHas('external_apply_events', [
            'id' => $eventId, 'vacancy_id' => $vacancyId,
            'event_type' => 'EXTERNAL_APPLY_STARTED', 'confirmation_status' => 'PENDING',
            'destination_url_reference' => self::ATS_URL, 'consent_id' => null,
        ]);
        self::assertSame(0, DB::table('applications')->where('vacancy_id', $vacancyId)->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'external_apply_started', 'object_id' => $eventId]);
    }

    public function test_start_is_rejected_for_an_in_portal_vacancy(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('ext-inportal@example.test');
        $close = Carbon::parse('2026-12-01T09:00:00+00:00');
        $open = $close->copy()->subDays(20);
        $vacancyId = $this->vacancyAt($recruiter, $company, 'PUBLISHED', [
            'application_method' => 'IN_PORTAL',
            'open_at' => $open->toIso8601String(), 'close_at' => $close->toIso8601String(),
        ]);
        Carbon::setTestNow($close->copy()->subDay());
        $candidate = $this->candidate('ext-inportal-c@example.test');

        $this->actingAs($candidate)->postJson("/vacancies/{$vacancyId}/external-apply/start", ['consent' => $this->consent()])
            ->assertStatus(422)->assertJsonPath('error.code', 'EXTERNAL_APPLY_INVALID_METHOD');
    }

    public function test_start_requires_an_open_vacancy(): void
    {
        [, , $vacancyId] = $this->externalVacancy('ext-closed@example.test');
        Carbon::setTestNow(Carbon::parse('2026-12-02T09:00:00+00:00')); // after close_at
        $candidate = $this->candidate('ext-closed-c@example.test');

        $this->actingAs($candidate)->postJson("/vacancies/{$vacancyId}/external-apply/start", ['consent' => $this->consent()])
            ->assertStatus(409)->assertJsonPath('error.code', 'VACANCY_NOT_OPEN');
    }

    public function test_start_requires_accepted_consent(): void
    {
        [, , $vacancyId] = $this->externalVacancy('ext-noconsent@example.test');
        $candidate = $this->candidate('ext-noconsent-c@example.test');

        $this->actingAs($candidate)->postJson("/vacancies/{$vacancyId}/external-apply/start", [
            'consent' => ['consent_version' => ApplicationConsentVersion::CURRENT, 'consent_text_hash_reference' => 'x', 'accepted' => false],
        ])->assertStatus(422)->assertJsonPath('error.code', 'APPLICATION_CONSENT_REQUIRED');
    }

    public function test_start_requires_authentication(): void
    {
        // No fixture built here — an actingAs()-driven helper would taint the
        // "unauthenticated" state. Auth middleware runs before route-model
        // binding, so a placeholder id is fine.
        $this->postJson('/vacancies/999999/external-apply/start', ['consent' => $this->consent()])
            ->assertUnauthorized()->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }

    public function test_a_non_candidate_persona_cannot_start_external_apply(): void
    {
        [$recruiter, , $vacancyId] = $this->externalVacancy('ext-persona@example.test');
        // $recruiter is COMPANY_RECRUITER with no candidate_profiles row.

        $this->actingAs($recruiter)->postJson("/vacancies/{$vacancyId}/external-apply/start", ['consent' => $this->consent()])
            ->assertStatus(422)->assertJsonPath('error.code', 'CANDIDATE_PROFILE_REQUIRED');

        self::assertSame(0, DB::table('external_apply_events')->where('vacancy_id', $vacancyId)->count());
        self::assertSame(0, DB::table('applications')->where('vacancy_id', $vacancyId)->count());
    }

    public function test_start_enforces_target_audience_eligibility(): void
    {
        [, , $vacancyId] = $this->externalVacancy('ext-elig@example.test', ['target_audience' => 'ALUMNI_ONLY']);
        $candidate = $this->candidate('ext-elig-c@example.test'); // no VERIFIED alumni record

        $this->actingAs($candidate)->postJson("/vacancies/{$vacancyId}/external-apply/start", ['consent' => $this->consent()])
            ->assertStatus(403)->assertJsonPath('error.code', 'CANDIDATE_NOT_ELIGIBLE');
    }

    public function test_confirm_by_the_starting_candidate_then_rejects_a_second_confirm(): void
    {
        [, , $vacancyId] = $this->externalVacancy('ext-confirm@example.test');
        $candidate = $this->candidate('ext-confirm-c@example.test');

        $eventId = (int) $this->actingAs($candidate)->postJson("/vacancies/{$vacancyId}/external-apply/start", ['consent' => $this->consent()])
            ->assertCreated()->json('data.external_apply_event_id');

        $this->actingAs($candidate)->postJson("/external-apply-events/{$eventId}/confirm", [
            'confirmation_status' => 'SUBMITTED_EXTERNALLY', 'confirmation_source' => 'CANDIDATE', 'notes' => 'done',
        ])->assertOk()->assertJsonPath('data.confirmation_status', 'SUBMITTED_EXTERNALLY');

        $row = DB::table('external_apply_events')->where('id', $eventId)->first();
        self::assertNotNull($row->confirmed_at);
        self::assertSame($candidate->id, (int) $row->confirmed_by);
        self::assertSame(0, DB::table('applications')->where('vacancy_id', $vacancyId)->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'external_apply_confirmed', 'object_id' => $eventId]);

        $this->actingAs($candidate)->postJson("/external-apply-events/{$eventId}/confirm", [
            'confirmation_status' => 'SUBMITTED_EXTERNALLY', 'confirmation_source' => 'CANDIDATE',
        ])->assertStatus(409)->assertJsonPath('error.code', 'EXTERNAL_APPLY_EVENT_ALREADY_CONFIRMED');
    }

    public function test_confirm_by_owning_company_member_is_allowed_and_by_a_stranger_is_forbidden(): void
    {
        [$recruiter, $company, $vacancyId] = $this->externalVacancy('ext-src@example.test');
        $candidate = $this->candidate('ext-src-c@example.test');
        $stranger = $this->candidate('ext-src-stranger@example.test');

        $eventId = (int) $this->actingAs($candidate)->postJson("/vacancies/{$vacancyId}/external-apply/start", ['consent' => $this->consent()])
            ->assertCreated()->json('data.external_apply_event_id');

        $this->actingAs($stranger)->postJson("/external-apply-events/{$eventId}/confirm", [
            'confirmation_status' => 'REACHED_OUT', 'confirmation_source' => 'CANDIDATE',
        ])->assertStatus(403)->assertJsonPath('error.code', 'EXTERNAL_APPLY_CONFIRMATION_FORBIDDEN');

        $this->actingAs($recruiter)->postJson("/external-apply-events/{$eventId}/confirm", [
            'confirmation_status' => 'RECEIVED', 'confirmation_source' => 'COMPANY',
        ])->assertOk()->assertJsonPath('data.confirmation_status', 'RECEIVED');
    }

    public function test_history_is_scoped_to_the_owning_candidate(): void
    {
        [, , $vacancyId] = $this->externalVacancy('ext-hist@example.test');
        $candidateA = $this->candidate('ext-hist-a@example.test');
        $candidateB = $this->candidate('ext-hist-b@example.test');

        $this->actingAs($candidateA)->postJson("/vacancies/{$vacancyId}/external-apply/start", ['consent' => $this->consent()])->assertCreated();

        $this->actingAs($candidateA)->getJson('/candidate/external-apply-events')->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.items.0.destination_url', self::ATS_URL);

        $this->actingAs($candidateB)->getJson('/candidate/external-apply-events')->assertOk()
            ->assertJsonPath('data.pagination.total', 0);
    }
}
