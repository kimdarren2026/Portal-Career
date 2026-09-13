<?php

declare(strict_types=1);

namespace Tests\Feature\Application;

use App\Domains\Application\Support\ApplicationConsentVersion;
use App\Domains\Identity\Enums\RoleCode;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Vacancy\VacancyTestCase;

/**
 * GAP-012 — consent-integrity hardening. The client no longer defines the
 * authoritative `consent_text_hash_reference`; the server derives and
 * persists it from the known version. Version-identifier semantics
 * (CONSENT_VERSION_UNKNOWN, APPLICATION_CONSENT_REQUIRED) are unchanged.
 *
 * Classification for this milestone: HARDENED_PENDING_APPROVED_CONSENT_TEXT
 * — no ratified consent document exists, so the derived reference is a
 * function of the version id, not a digest of approved wording.
 */
final class ConsentIntegrityTest extends VacancyTestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** @return array{User, int} candidate + vacancy id */
    private function openVacancyAndCandidate(string $email): array
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter($email);
        $close = Carbon::parse('2026-12-01T09:00:00+00:00');
        $open = $close->copy()->subDays(20);
        $vacancyId = $this->vacancyAt($recruiter, $company, 'PUBLISHED', [
            'open_at' => $open->toIso8601String(), 'close_at' => $close->toIso8601String(),
        ]);
        DB::table('vacancies')->where('id', $vacancyId)->update(['published_at' => $open]);
        Carbon::setTestNow($close->copy()->subDay());

        $candidate = $this->makeUser('cand-'.$email, UserStatus::Active);
        $this->assignRole($candidate, RoleCode::CandidateExternal);
        DB::table('candidate_profiles')->insert([
            'user_id' => $candidate->id, 'current_candidate_type' => 'EXTERNAL',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return [$candidate, $vacancyId];
    }

    private function submit(User $candidate, int $vacancyId, string $clientHash): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($candidate)->postJson("/vacancies/{$vacancyId}/applications", [
            'consent' => [
                'consent_version' => ApplicationConsentVersion::CURRENT,
                'consent_text_hash_reference' => $clientHash,
                'accepted' => true,
            ],
        ]);
    }

    public function test_arbitrary_client_hash_never_becomes_the_persisted_trusted_hash(): void
    {
        [$candidate, $vacancyId] = $this->openVacancyAndCandidate('consent-tamper@example.test');

        $applicationId = (int) $this->submit($candidate, $vacancyId, 'totally-bogus-attacker-controlled-value')
            ->assertCreated()->json('data.id');

        $persisted = DB::table('consents')->where('application_id', $applicationId)->value('consent_text_hash_reference');

        self::assertNotSame('totally-bogus-attacker-controlled-value', $persisted);
        self::assertSame(ApplicationConsentVersion::serverDerivedHash(ApplicationConsentVersion::CURRENT), $persisted);
    }

    public function test_two_different_client_hashes_yield_the_same_server_authoritative_hash(): void
    {
        [$candidateA, $vacancyA] = $this->openVacancyAndCandidate('consent-a@example.test');
        [$candidateB, $vacancyB] = $this->openVacancyAndCandidate('consent-b@example.test');

        $idA = (int) $this->submit($candidateA, $vacancyA, 'client-hash-one')->assertCreated()->json('data.id');
        $idB = (int) $this->submit($candidateB, $vacancyB, 'client-hash-two-completely-different')->assertCreated()->json('data.id');

        $hashA = DB::table('consents')->where('application_id', $idA)->value('consent_text_hash_reference');
        $hashB = DB::table('consents')->where('application_id', $idB)->value('consent_text_hash_reference');

        self::assertSame($hashA, $hashB);
        self::assertSame(ApplicationConsentVersion::serverDerivedHash(ApplicationConsentVersion::CURRENT), $hashA);
    }

    public function test_unknown_consent_version_is_still_rejected(): void
    {
        [$candidate, $vacancyId] = $this->openVacancyAndCandidate('consent-badver@example.test');

        $this->actingAs($candidate)->postJson("/vacancies/{$vacancyId}/applications", [
            'consent' => [
                'consent_version' => 'NOT_A_REAL_VERSION',
                'consent_text_hash_reference' => 'x',
                'accepted' => true,
            ],
        ])->assertStatus(422)->assertJsonPath('error.code', 'CONSENT_VERSION_UNKNOWN');
    }

    public function test_unaccepted_consent_is_still_rejected(): void
    {
        [$candidate, $vacancyId] = $this->openVacancyAndCandidate('consent-noaccept@example.test');

        $this->actingAs($candidate)->postJson("/vacancies/{$vacancyId}/applications", [
            'consent' => [
                'consent_version' => ApplicationConsentVersion::CURRENT,
                'consent_text_hash_reference' => 'x',
                'accepted' => false,
            ],
        ])->assertStatus(422)->assertJsonPath('error.code', 'APPLICATION_CONSENT_REQUIRED');
    }
}
