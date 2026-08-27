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
 * Frontend Vertical Slice v1 correction — styled Inertia error pages for
 * browser/Inertia GET page navigation only. The distinguishing mechanism is
 * `$request->expectsJson()`, the same predicate `shouldRenderJsonWhen`
 * already uses in `bootstrap/app.php` — never a route-guessing heuristic.
 * Every mutation/action JSON endpoint is proven unaffected (test 5 below is
 * the critical regression guard).
 */
final class RecruitmentFrontendErrorPagesTest extends VacancyTestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_candidate_foreign_application_page_is_styled_404(): void
    {
        [, , $vacancyId] = $this->openVacancy('errpage-app-a@example.test');
        [$owner] = $this->candidate('errpage-app-owner@example.test');
        [$other] = $this->candidate('errpage-app-other@example.test');
        $applicationId = $this->submitApplication($owner, $vacancyId);

        $response = $this->actingAs($other)->get("/lamaran-saya/{$applicationId}");
        $response->assertNotFound();
        $response->assertInertia(fn (Assert $page) => $page->component('Error')->where('status', 404));
        self::assertStringNotContainsString('application/json', (string) $response->headers->get('Content-Type'), 'A styled error page must not carry a raw ContractResponse JSON body.');
    }

    public function test_candidate_missing_application_page_is_styled_404(): void
    {
        [$candidate] = $this->candidate('errpage-app-missing@example.test');

        $this->actingAs($candidate)->get('/lamaran-saya/999999999')
            ->assertNotFound()
            ->assertInertia(fn (Assert $page) => $page->component('Error')->where('status', 404));
    }

    public function test_recruiter_foreign_applicant_page_is_enumeration_safe_404(): void
    {
        [$adminA, , $vacancyIdA] = $this->openVacancy('errpage-pelamar-a@example.test');
        [$adminB] = $this->openVacancy('errpage-pelamar-b@example.test');
        [$candidate] = $this->candidate('errpage-pelamar-candidate@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyIdA);

        $response = $this->actingAs($adminB)->get("/pelamar/{$applicationId}");
        $response->assertNotFound();
        $response->assertInertia(fn (Assert $page) => $page->component('Error')->where('status', 404));
    }

    public function test_schedule_missing_and_foreign_page_returns_styled_404(): void
    {
        [$admin] = $this->openVacancy('errpage-schedule-a@example.test');
        [$candidate] = $this->candidate('errpage-schedule-candidate@example.test');

        $this->actingAs($admin)->get('/jadwal-seleksi/999999999')
            ->assertNotFound()
            ->assertInertia(fn (Assert $page) => $page->component('Error')->where('status', 404));

        $this->actingAs($candidate)->get('/jadwal-seleksi/999999999')
            ->assertNotFound()
            ->assertInertia(fn (Assert $page) => $page->component('Error')->where('status', 404));
    }

    public function test_apply_page_for_unknown_slug_is_styled_404(): void
    {
        [$candidate] = $this->candidate('errpage-apply-missing@example.test');

        $this->actingAs($candidate)->get('/lowongan/does-not-exist-slug/lamar')
            ->assertNotFound()
            ->assertInertia(fn (Assert $page) => $page->component('Error')->where('status', 404));
    }

    public function test_candidate_gets_styled_403_on_pelamar_page(): void
    {
        [$candidate] = $this->candidate('errpage-403-candidate@example.test');

        $response = $this->actingAs($candidate)->get('/pelamar');
        $response->assertStatus(403);
        $response->assertInertia(fn (Assert $page) => $page->component('Error')->where('status', 403));
        self::assertStringNotContainsString('application/json', (string) $response->headers->get('Content-Type'), 'A styled error page must not carry a raw ContractResponse JSON body.');
    }

    /**
     * Critical regression guard: the same underlying not-found/forbidden
     * conditions on the frozen JSON action/contract endpoints must keep
     * returning their exact original `ContractResponse` envelope — never
     * the new styled Inertia error page.
     */
    public function test_existing_mutation_and_json_endpoints_retain_contract_response_envelope(): void
    {
        [$adminA, , $vacancyIdA] = $this->openVacancy('errpage-regress-a@example.test');
        [$adminB] = $this->openVacancy('errpage-regress-b@example.test');
        [$candidate] = $this->candidate('errpage-regress-candidate@example.test');
        $applicationId = $this->submitApplication($candidate, $vacancyIdA);

        // JSON GET /applications/{foreign} (existing JSON controller) — untouched.
        $this->actingAs($adminB)->getJson("/applications/{$applicationId}")
            ->assertStatus(404)->assertJsonPath('error.code', 'NOT_FOUND');

        // JSON mutation POST /applications/{foreign}/transition — untouched.
        $this->actingAs($adminB)->postJson("/applications/{$applicationId}/transition", [
            'to_status' => 'UNDER_REVIEW', 'candidate_visibility' => 'VISIBLE',
        ])->assertStatus(404)->assertJsonPath('error.code', 'NOT_FOUND');

        // JSON mutation POST /applications/{foreign}/schedules — untouched.
        $this->actingAs($adminB)->postJson("/applications/{$applicationId}/schedules", [
            'recruitment_stage_id' => 1, 'selection_type' => 'Wawancara',
            'starts_at' => now()->addDays(3)->toIso8601String(), 'timezone' => 'Asia/Jakarta',
            'method' => 'ONLINE', 'meeting_url' => 'https://meet.example.test/x',
        ])->assertStatus(404)->assertJsonPath('error.code', 'NOT_FOUND');

        // JSON GET /schedules/{missing} — untouched.
        $this->actingAs($adminA)->getJson('/schedules/999999999')
            ->assertStatus(404)->assertJsonPath('error.code', 'NOT_FOUND');

        // JSON recruitment-outcomes create with unauthorized actor — untouched AUTH_FORBIDDEN envelope.
        $this->actingAs($candidate)->postJson('/recruitment-outcomes', [
            'source_type' => 'INTERNAL_APPLICATION', 'application_id' => $applicationId,
            'outcome' => 'HIRED', 'reported_by_source' => 'COMPANY',
        ])->assertStatus(403)->assertJsonPath('error.code', 'AUTH_FORBIDDEN');
    }

    // ---------------------------------------------------------------
    // Fixtures (same shape as RecruitmentFrontendPagesTest)
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
