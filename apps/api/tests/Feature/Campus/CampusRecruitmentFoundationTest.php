<?php

declare(strict_types=1);

namespace Tests\Feature\Campus;

use App\Domains\Identity\Enums\RoleCode;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Vacancy\VacancyTestCase;

/**
 * Campus Recruitment Foundation — CAMPUS_SCOPE / HR_ADMIN runtime activation
 * (approved Product Owner / SPEC-DOC decision; BRD/FSD already require the
 * Karier di Kampus track — FSD §5.5, FR-HR-001..007, §8.4).
 *
 * Proves the full campus journey on one shared candidate/application
 * lifecycle, plus Campus↔Company isolation and cross-role denial.
 */
final class CampusRecruitmentFoundationTest extends VacancyTestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function hrAdmin(string $email): User
    {
        $user = $this->makeUser($email, UserStatus::Active);
        $this->assignRole($user, RoleCode::HrAdmin);

        return $user;
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

    private function unit(string $code = 'FAK-TEKNIK'): int
    {
        return (int) DB::table('organizational_units')->insertGetId([
            'code' => $code, 'name' => 'Unit '.$code, 'active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function campusVacancyPayload(int $unitId, array $overrides = []): array
    {
        return array_merge($this->submittablePayload([
            'open_at' => now()->subDay()->toIso8601String(),
            'close_at' => now()->addDays(30)->toIso8601String(),
        ]), [
            'vacancy_type' => 'CAMPUS_EMPLOYMENT',
            'organizational_unit_id' => $unitId,
        ], $overrides);
    }

    private function applyToVacancy(User $candidate, int $vacancyId): int
    {
        return (int) $this->actingAs($candidate)->postJson("/vacancies/{$vacancyId}/applications", [
            'consent' => [
                'consent_version' => \App\Domains\Application\Support\ApplicationConsentVersion::CURRENT,
                'consent_text_hash_reference' => 'sha256:'.str_repeat('a', 64),
                'accepted' => true,
            ],
        ])->assertCreated()->json('data.id');
    }

    // ---------------------------------------------------------------

    public function test_headline_campus_journey_end_to_end(): void
    {
        $hr = $this->hrAdmin('campus-hr@example.test');
        $unitId = $this->unit();

        // CASE A — create DRAFT campus vacancy.
        $create = $this->actingAs($hr)->postJson('/hr/vacancies', $this->campusVacancyPayload($unitId))
            ->assertCreated();
        $vacancyId = (int) $create->json('data.id');
        $this->assertDatabaseHas('vacancies', [
            'id' => $vacancyId, 'ownership_type' => 'CAMPUS', 'company_id' => null,
            'organizational_unit_id' => $unitId, 'vacancy_type' => 'CAMPUS_EMPLOYMENT',
            'application_method' => 'IN_PORTAL', 'current_status' => 'DRAFT',
        ]);

        // CASE B — publish directly (FSD §8.4), no moderation.
        $this->actingAs($hr)->postJson("/hr/vacancies/{$vacancyId}/publish")
            ->assertOk()->assertJsonPath('data.current_status', 'PUBLISHED');
        $this->assertNotNull(DB::table('vacancies')->where('id', $vacancyId)->value('published_at'));

        // CASE C — candidate discovers it and applies (one application lifecycle).
        $slug = DB::table('vacancies')->where('id', $vacancyId)->value('slug');
        $candidate = $this->candidate('campus-cand@example.test');
        $publicSlugs = $this->getJson('/api/v1/public/vacancies?per_page=50')->assertOk()->json('data.items.*.slug');
        self::assertContains($slug, $publicSlugs);
        $this->getJson("/api/v1/public/vacancies/{$slug}")->assertOk()
            ->assertJsonPath('data.vacancy_type', 'CAMPUS_EMPLOYMENT');
        $applicationId = $this->applyToVacancy($candidate, $vacancyId);
        $this->assertDatabaseHas('applications', ['id' => $applicationId, 'current_status' => 'APPLIED']);

        // CASE D — HR admin sees the applicant (CAMPUS_SCOPE).
        $this->actingAs($hr)->getJson('/applications')->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.items.0.id', $applicationId);
        $this->actingAs($hr)->getJson("/applications/{$applicationId}")->assertOk();

        // CASE E — process status.
        $this->actingAs($hr)->postJson("/applications/{$applicationId}/transition", [
            'to_status' => 'UNDER_REVIEW', 'candidate_visibility' => 'INTERNAL',
        ])->assertOk()->assertJsonPath('data.current_status', 'UNDER_REVIEW');

        // CASE F — stage + schedule.
        $stageId = (int) $this->actingAs($hr)->postJson("/vacancies/{$vacancyId}/stages", [
            'name' => 'Wawancara', 'stage_type' => 'GENERAL', 'sort_order' => 0, 'active' => true,
        ])->assertCreated()->json('data.id');
        $scheduleId = (int) $this->actingAs($hr)->postJson("/applications/{$applicationId}/schedules", [
            'recruitment_stage_id' => $stageId, 'selection_type' => 'INTERVIEW',
            'starts_at' => now()->addDays(3)->toIso8601String(),
            'ends_at' => now()->addDays(3)->addHour()->toIso8601String(),
            'timezone' => 'Asia/Jakarta', 'method' => 'ONLINE',
            'meeting_url' => 'https://meet.example.test/room',
        ])->assertCreated()->json('data.id');
        // Timezone contract: an absolute instant is persisted.
        self::assertNotNull(DB::table('selection_schedules')->where('id', $scheduleId)->value('starts_at'));

        // CASE G — evaluation.
        $evaluationId = (int) $this->actingAs($hr)->postJson("/applications/{$applicationId}/evaluations", [
            'recruitment_stage_id' => $stageId, 'recommendation' => 'HIRE',
            'comments' => 'Kandidat kuat.', 'total_score' => 88.0,
        ])->assertCreated()->json('data.id');
        $this->actingAs($hr)->postJson("/evaluations/{$evaluationId}/submit", [])->assertOk();
        self::assertNotNull(DB::table('evaluations')->where('id', $evaluationId)->value('submitted_at'));

        // CASE H — offer create + send.
        $offerId = (int) $this->actingAs($hr)->postJson("/applications/{$applicationId}/offers", [
            'note' => 'Selamat, Anda diterima.',
        ])->assertCreated()->assertJsonPath('data.status', 'DRAFT')->json('data.id');
        $this->actingAs($hr)->postJson("/offers/{$offerId}/send", [], ['Idempotency-Key' => 'campus-send-1'])
            ->assertOk()->assertJsonPath('data.status', 'SENT');
        // Create/send never mutate application status.
        self::assertSame('UNDER_REVIEW', DB::table('applications')->where('id', $applicationId)->value('current_status'));

        // CASE I — candidate accepts: offer ACCEPTED, application HIRED, NO auto outcome.
        $this->actingAs($candidate)->postJson("/offers/{$offerId}/accept", [])
            ->assertOk()->assertJsonPath('data.status', 'ACCEPTED');
        self::assertSame('HIRED', DB::table('applications')->where('id', $applicationId)->value('current_status'));
        self::assertSame(0, DB::table('recruitment_outcomes')->where('application_id', $applicationId)->count());

        // The application now shows as incomplete in the H-5 campus report.
        $this->actingAs($hr)->getJson('/recruitment-outcomes/incomplete')->assertOk()
            ->assertJsonPath('data.pagination.total', 1);

        // CASE J — HR admin records the explicit outcome.
        $this->actingAs($hr)->postJson('/recruitment-outcomes', [
            'source_type' => 'INTERNAL_APPLICATION',
            'application_id' => $applicationId,
            'outcome' => 'HIRED',
            'reported_by_source' => 'CAMPUS_STAFF',
        ], ['Idempotency-Key' => 'campus-outcome-1'])->assertCreated();
        $this->assertDatabaseHas('recruitment_outcomes', [
            'application_id' => $applicationId, 'source_type' => 'INTERNAL_APPLICATION', 'outcome' => 'HIRED',
        ]);
    }

    public function test_candidate_reject_leaves_application_unchanged_and_creates_no_outcome(): void
    {
        $hr = $this->hrAdmin('campus-hr-rej@example.test');
        $unitId = $this->unit('FAK-EKONOMI');
        $vacancyId = (int) $this->actingAs($hr)->postJson('/hr/vacancies', $this->campusVacancyPayload($unitId))
            ->assertCreated()->json('data.id');
        $this->actingAs($hr)->postJson("/hr/vacancies/{$vacancyId}/publish")->assertOk();

        $candidate = $this->candidate('campus-cand-rej@example.test');
        $applicationId = $this->applyToVacancy($candidate, $vacancyId);
        $this->actingAs($hr)->postJson("/applications/{$applicationId}/transition", [
            'to_status' => 'UNDER_REVIEW', 'candidate_visibility' => 'INTERNAL',
        ])->assertOk();

        $offerId = (int) $this->actingAs($hr)->postJson("/applications/{$applicationId}/offers", ['note' => 'Tawaran.'])
            ->assertCreated()->json('data.id');
        $this->actingAs($hr)->postJson("/offers/{$offerId}/send", [], ['Idempotency-Key' => 'campus-send-r'])->assertOk();

        $this->actingAs($candidate)->postJson("/offers/{$offerId}/reject", ['rejection_reason' => 'Terima kasih.'])
            ->assertOk()->assertJsonPath('data.status', 'REJECTED');

        self::assertSame('UNDER_REVIEW', DB::table('applications')->where('id', $applicationId)->value('current_status'));
        self::assertSame(0, DB::table('recruitment_outcomes')->where('application_id', $applicationId)->count());
    }

    public function test_recruiter_and_career_center_cannot_touch_the_campus_track(): void
    {
        [$recruiter] = $this->verifiedCompanyWithRecruiter('campus-x-recruiter@example.test');
        $careerCenter = $this->moderator('campus-x-cc@example.test');
        $unitId = $this->unit('FAK-HUKUM');

        // Recruiter cannot create a campus vacancy.
        $this->actingAs($recruiter)->postJson('/hr/vacancies', $this->campusVacancyPayload($unitId))
            ->assertForbidden()->assertJsonPath('error.code', 'AUTH_FORBIDDEN');

        // Set one up as HR and publish.
        $hr = $this->hrAdmin('campus-x-hr@example.test');
        $vacancyId = (int) $this->actingAs($hr)->postJson('/hr/vacancies', $this->campusVacancyPayload($unitId))
            ->assertCreated()->json('data.id');

        // Recruiter cannot publish it, cannot read it (VacancyScope), cannot edit it.
        $this->actingAs($recruiter)->postJson("/hr/vacancies/{$vacancyId}/publish")->assertForbidden();
        $this->actingAs($recruiter)->getJson("/vacancies/{$vacancyId}")->assertNotFound();
        $this->actingAs($recruiter)->patchJson("/vacancies/{$vacancyId}", ['title' => 'Hijack'])->assertNotFound();

        // Career Center cannot even see a campus vacancy — VacancyScope is
        // company-only for it, so the moderation route is an enumeration-safe 404.
        $this->actingAs($careerCenter)->postJson("/vacancies/{$vacancyId}/suspend", [
            'reason_category' => 'LAINNYA', 'recruiter_visible_note' => 'x',
        ])->assertNotFound();

        // Career Center cannot process a campus applicant.
        $this->actingAs($hr)->postJson("/hr/vacancies/{$vacancyId}/publish")->assertOk();
        $candidate = $this->candidate('campus-x-cand@example.test');
        $applicationId = $this->applyToVacancy($candidate, $vacancyId);
        $this->actingAs($careerCenter)->postJson("/applications/{$applicationId}/transition", [
            'to_status' => 'UNDER_REVIEW', 'candidate_visibility' => 'INTERNAL',
        ])->assertForbidden();
        $this->actingAs($recruiter)->getJson("/applications/{$applicationId}")->assertNotFound();
    }

    public function test_campus_admin_cannot_reach_the_company_track(): void
    {
        $hr = $this->hrAdmin('campus-iso-hr@example.test');
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('campus-iso-recruiter@example.test');
        $companyVacancyId = $this->vacancyAt($recruiter, $company, 'PUBLISHED', [
            'open_at' => now()->subDay()->toIso8601String(),
            'close_at' => now()->addDays(20)->toIso8601String(),
        ]);
        $candidate = $this->candidate('campus-iso-cand@example.test');
        $companyApplicationId = $this->applyToVacancy($candidate, $companyVacancyId);

        // HR admin's applicant scope is campus-only.
        $this->actingAs($hr)->getJson('/applications')->assertOk()->assertJsonPath('data.pagination.total', 0);
        $this->actingAs($hr)->getJson("/applications/{$companyApplicationId}")->assertNotFound();
        $this->actingAs($hr)->postJson("/applications/{$companyApplicationId}/transition", [
            'to_status' => 'UNDER_REVIEW', 'candidate_visibility' => 'INTERNAL',
        ])->assertNotFound();
        $this->actingAs($hr)->getJson("/vacancies/{$companyVacancyId}")->assertNotFound();
    }

    public function test_campus_vacancy_rejects_external_ats(): void
    {
        $hr = $this->hrAdmin('campus-ats@example.test');
        $unitId = $this->unit('FAK-KEDOKTERAN');

        $this->actingAs($hr)->postJson('/hr/vacancies', $this->campusVacancyPayload($unitId, [
            'application_method' => 'EXTERNAL_ATS',
            'external_ats_url' => 'https://ats.example.test/apply',
        ]))->assertStatus(422)->assertJsonPath('error.code', 'VACANCY_EXTERNAL_ATS_NOT_ALLOWED_FOR_CAMPUS');
    }

    public function test_campus_publish_requires_dates_and_rejects_illegal_transitions(): void
    {
        $hr = $this->hrAdmin('campus-life@example.test');
        $unitId = $this->unit('FAK-FISIP');

        $noDatesId = (int) $this->actingAs($hr)->postJson('/hr/vacancies', array_merge(
            $this->campusVacancyPayload($unitId),
            ['open_at' => null, 'close_at' => null],
        ))->assertCreated()->json('data.id');
        $this->actingAs($hr)->postJson("/hr/vacancies/{$noDatesId}/publish")
            ->assertStatus(422)->assertJsonPath('error.code', 'VACANCY_DATES_REQUIRED');
        // Cannot close a DRAFT.
        $this->actingAs($hr)->postJson("/hr/vacancies/{$noDatesId}/close")
            ->assertStatus(409)->assertJsonPath('error.code', 'VACANCY_INVALID_TRANSITION');

        $okId = (int) $this->actingAs($hr)->postJson('/hr/vacancies', $this->campusVacancyPayload($unitId))
            ->assertCreated()->json('data.id');
        $this->actingAs($hr)->postJson("/hr/vacancies/{$okId}/schedule")->assertOk()
            ->assertJsonPath('data.current_status', 'SCHEDULED');
        $this->actingAs($hr)->postJson("/hr/vacancies/{$okId}/publish")->assertOk()
            ->assertJsonPath('data.current_status', 'PUBLISHED');
        $this->actingAs($hr)->postJson("/hr/vacancies/{$okId}/suspend")->assertOk()
            ->assertJsonPath('data.current_status', 'SUSPENDED');
        $this->actingAs($hr)->postJson("/hr/vacancies/{$okId}/restore")->assertOk()
            ->assertJsonPath('data.current_status', 'PUBLISHED');
        $this->actingAs($hr)->postJson("/hr/vacancies/{$okId}/close")->assertOk()
            ->assertJsonPath('data.current_status', 'CLOSED');
    }
}
