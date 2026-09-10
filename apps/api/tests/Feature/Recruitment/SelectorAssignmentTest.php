<?php

declare(strict_types=1);

namespace Tests\Feature\Recruitment;

use App\Domains\Application\Support\ApplicationConsentVersion;
use App\Domains\Identity\Enums\RoleCode;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Vacancy\VacancyTestCase;

/**
 * GAP-010 — selector stage assignment/revocation (FR-HR-006, INV-037,
 * AUTHORIZATION_MATRIX.md §4.8) and the `ASSIGNED_STAGE` read scope wired
 * into `GET /applications(/{id})` (§4.6).
 */
final class SelectorAssignmentTest extends VacancyTestCase
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

    private function selectorUser(string $email): User
    {
        $user = $this->makeUser($email, UserStatus::Active);
        $this->assignRole($user, RoleCode::Selector);

        return $user;
    }

    /** @return array{User, int} */
    private function candidate(string $email): array
    {
        $user = $this->makeUser($email, UserStatus::Active);
        $this->assignRole($user, RoleCode::CandidateExternal);
        $profileId = (int) DB::table('candidate_profiles')->insertGetId([
            'user_id' => $user->id, 'current_candidate_type' => 'EXTERNAL',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return [$user, $profileId];
    }

    private function unit(string $code): int
    {
        return (int) DB::table('organizational_units')->insertGetId([
            'code' => $code, 'name' => 'Unit '.$code, 'active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** Publishes a campus vacancy and returns its id. */
    private function campusVacancy(User $hr, int $unitId): int
    {
        $payload = array_merge($this->submittablePayload([
            'open_at' => now()->subDay()->toIso8601String(),
            'close_at' => now()->addDays(30)->toIso8601String(),
        ]), [
            'vacancy_type' => 'CAMPUS_EMPLOYMENT',
            'organizational_unit_id' => $unitId,
        ]);

        $vacancyId = (int) $this->actingAs($hr)->postJson('/hr/vacancies', $payload)->assertCreated()->json('data.id');
        $this->actingAs($hr)->postJson("/hr/vacancies/{$vacancyId}/publish")->assertOk();

        return $vacancyId;
    }

    private function stage(User $hr, int $vacancyId, string $name, int $order): int
    {
        return (int) $this->actingAs($hr)->postJson("/vacancies/{$vacancyId}/stages", [
            'name' => $name, 'stage_type' => 'GENERAL', 'sort_order' => $order, 'active' => true,
        ])->assertCreated()->json('data.id');
    }

    private function applyAt(User $candidate, int $vacancyId): int
    {
        return (int) $this->actingAs($candidate)->postJson("/vacancies/{$vacancyId}/applications", [
            'consent' => [
                'consent_version' => ApplicationConsentVersion::CURRENT,
                'consent_text_hash_reference' => 'sha256:'.str_repeat('a', 64),
                'accepted' => true,
            ],
        ])->assertCreated()->json('data.id');
    }

    private function moveToStage(User $hr, int $applicationId, int $stageId): void
    {
        $this->actingAs($hr)->postJson("/applications/{$applicationId}/move-stage", [
            'to_stage_id' => $stageId, 'candidate_visibility' => 'INTERNAL',
        ])->assertOk();
    }

    // -----------------------------------------------------------------
    // Assignment lifecycle + authorization
    // -----------------------------------------------------------------

    public function test_hr_admin_assigns_and_revokes_a_selector_on_an_exact_campus_stage(): void
    {
        $hr = $this->hrAdmin('sa-hr@example.test');
        $selector = $this->selectorUser('sa-selector@example.test');
        $vacancyId = $this->campusVacancy($hr, $this->unit('SA-FT'));
        $stageA = $this->stage($hr, $vacancyId, 'Wawancara', 0);

        $assignmentId = (int) $this->actingAs($hr)->postJson("/stages/{$stageA}/selector-assignments", [
            'selector_user_id' => $selector->id,
        ])->assertCreated()->json('data.assignment.id');

        $this->assertDatabaseHas('selection_stage_assignments', [
            'id' => $assignmentId, 'recruitment_stage_id' => $stageA,
            'selector_user_id' => $selector->id, 'assigned_by_user_id' => $hr->id, 'revoked_at' => null,
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'selector_assigned', 'object_id' => $assignmentId]);
        self::assertSame(1, DB::table('notifications')->where('user_id', $selector->id)
            ->where('type', 'SELECTOR_ASSIGNMENT_CHANGED')->count());
        self::assertSame(1, DB::table('email_outbox')->where('recipient', $selector->email)
            ->where('related_object_type', 'selection_stage_assignment')->count());

        $this->actingAs($hr)->postJson("/selector-assignments/{$assignmentId}/revoke")->assertOk();
        $row = DB::table('selection_stage_assignments')->where('id', $assignmentId)->first();
        self::assertNotNull($row->revoked_at);
        self::assertSame($hr->id, (int) $row->revoked_by_user_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'selector_assignment_revoked', 'object_id' => $assignmentId]);
    }

    public function test_role_alone_grants_no_access_and_assigned_stage_scope_is_exact(): void
    {
        $hr = $this->hrAdmin('sa-scope-hr@example.test');
        $selector = $this->selectorUser('sa-scope-selector@example.test');
        [$candidate] = $this->candidate('sa-scope-cand@example.test');

        $vacancy1 = $this->campusVacancy($hr, $this->unit('SA-V1'));
        $stageA = $this->stage($hr, $vacancy1, 'Tahap A', 0);
        $stageB = $this->stage($hr, $vacancy1, 'Tahap B', 1);

        $vacancy2 = $this->campusVacancy($hr, $this->unit('SA-V2'));
        $stageC = $this->stage($hr, $vacancy2, 'Tahap C', 0);

        $applicationId = $this->applyAt($candidate, $vacancy1);
        $this->moveToStage($hr, $applicationId, $stageA);

        // SELECTOR role, no assignment yet → sees nothing, 404 on direct read.
        $this->actingAs($selector)->getJson('/applications')->assertOk()->assertJsonPath('data.items', []);
        $this->actingAs($selector)->getJson("/applications/{$applicationId}")
            ->assertStatus(404)->assertJsonPath('error.code', 'NOT_FOUND');

        // Assigned to stage A → application currently on A is visible.
        $assignA = (int) $this->actingAs($hr)->postJson("/stages/{$stageA}/selector-assignments", [
            'selector_user_id' => $selector->id,
        ])->assertCreated()->json('data.assignment.id');

        $this->actingAs($selector)->getJson('/applications')->assertOk()
            ->assertJsonPath('data.items', fn (array $items): bool => count($items) === 1 && $items[0]['id'] === $applicationId);
        $this->actingAs($selector)->getJson("/applications/{$applicationId}")->assertOk()
            ->assertJsonPath('data.id', $applicationId);

        // Move the application to stage B (same vacancy, NOT assigned) → gone.
        $this->moveToStage($hr, $applicationId, $stageB);
        $this->actingAs($selector)->getJson('/applications')->assertOk()->assertJsonPath('data.items', []);
        $this->actingAs($selector)->getJson("/applications/{$applicationId}")->assertStatus(404);

        // Move it back to stage A → visible again (assignment A still active).
        $this->moveToStage($hr, $applicationId, $stageA);
        $this->actingAs($selector)->getJson("/applications/{$applicationId}")->assertOk();

        // Revoke the stage-A assignment → immediately gone even with app on A.
        $this->actingAs($hr)->postJson("/selector-assignments/{$assignA}/revoke")->assertOk();
        $this->actingAs($selector)->getJson('/applications')->assertOk()->assertJsonPath('data.items', []);
        $this->actingAs($selector)->getJson("/applications/{$applicationId}")->assertStatus(404);

        // An assignment on stage C (another vacancy) grants no reach to this
        // application, which sits on vacancy 1 / stage A.
        $this->actingAs($hr)->postJson("/stages/{$stageC}/selector-assignments", [
            'selector_user_id' => $selector->id,
        ])->assertCreated();
        $this->actingAs($selector)->getJson('/applications')->assertOk()->assertJsonPath('data.items', []);
        $this->actingAs($selector)->getJson("/applications/{$applicationId}")->assertStatus(404);
    }

    public function test_assigning_a_user_without_active_selector_role_is_rejected(): void
    {
        $hr = $this->hrAdmin('sa-norole-hr@example.test');
        $notSelector = $this->makeUser('sa-norole-user@example.test', UserStatus::Active);
        $vacancyId = $this->campusVacancy($hr, $this->unit('SA-NR'));
        $stageA = $this->stage($hr, $vacancyId, 'Tahap A', 0);

        $this->actingAs($hr)->postJson("/stages/{$stageA}/selector-assignments", [
            'selector_user_id' => $notSelector->id,
        ])->assertStatus(422)->assertJsonPath('error.code', 'SELECTOR_ROLE_REQUIRED');
    }

    public function test_duplicate_active_assignment_is_rejected(): void
    {
        $hr = $this->hrAdmin('sa-dup-hr@example.test');
        $selector = $this->selectorUser('sa-dup-selector@example.test');
        $vacancyId = $this->campusVacancy($hr, $this->unit('SA-DUP'));
        $stageA = $this->stage($hr, $vacancyId, 'Tahap A', 0);

        $this->actingAs($hr)->postJson("/stages/{$stageA}/selector-assignments", ['selector_user_id' => $selector->id])->assertCreated();
        $this->actingAs($hr)->postJson("/stages/{$stageA}/selector-assignments", ['selector_user_id' => $selector->id])
            ->assertStatus(409)->assertJsonPath('error.code', 'SELECTOR_ASSIGNMENT_ALREADY_ACTIVE');
    }

    public function test_company_stage_cannot_receive_a_selector_assignment(): void
    {
        $hr = $this->hrAdmin('sa-company-hr@example.test');
        $selector = $this->selectorUser('sa-company-selector@example.test');
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('sa-company-recruiter@example.test');
        $companyVacancy = $this->vacancyAt($recruiter, $company, 'DRAFT');
        $companyStage = $this->stage($recruiter, $companyVacancy, 'Tahap Perusahaan', 0);

        $this->actingAs($hr)->postJson("/stages/{$companyStage}/selector-assignments", [
            'selector_user_id' => $selector->id,
        ])->assertStatus(404)->assertJsonPath('error.code', 'NOT_FOUND');
    }

    public function test_other_personas_cannot_assign_or_self_assign(): void
    {
        $hr = $this->hrAdmin('sa-deny-hr@example.test');
        $selector = $this->selectorUser('sa-deny-selector@example.test');
        $careerCenter = $this->makeUser('sa-deny-cc@example.test', UserStatus::Active);
        $this->assignRole($careerCenter, RoleCode::CareerCenterStaff);
        [$recruiter] = $this->verifiedCompanyWithRecruiter('sa-deny-recruiter@example.test');
        [$candidate] = $this->candidate('sa-deny-cand@example.test');

        $vacancyId = $this->campusVacancy($hr, $this->unit('SA-DENY'));
        $stageA = $this->stage($hr, $vacancyId, 'Tahap A', 0);

        foreach ([
            'career center' => $careerCenter,
            'recruiter' => $recruiter,
            'candidate' => $candidate,
            'selector self-assign' => $selector,
        ] as $actor) {
            $this->actingAs($actor)->postJson("/stages/{$stageA}/selector-assignments", [
                'selector_user_id' => $selector->id,
            ])->assertStatus(403)->assertJsonPath('error.code', 'AUTH_FORBIDDEN');
        }

        // A selector cannot revoke either — including its own assignment.
        $assignmentId = (int) $this->actingAs($hr)->postJson("/stages/{$stageA}/selector-assignments", [
            'selector_user_id' => $selector->id,
        ])->assertCreated()->json('data.assignment.id');
        $this->actingAs($selector)->postJson("/selector-assignments/{$assignmentId}/revoke")
            ->assertStatus(403)->assertJsonPath('error.code', 'AUTH_FORBIDDEN');
    }

    public function test_career_center_is_never_granted_candidate_selection_via_a_stage(): void
    {
        $hr = $this->hrAdmin('sa-ccsel-hr@example.test');
        [$candidate] = $this->candidate('sa-ccsel-cand@example.test');
        $careerCenter = $this->makeUser('sa-ccsel-cc@example.test', UserStatus::Active);
        $this->assignRole($careerCenter, RoleCode::CareerCenterManager);

        $vacancyId = $this->campusVacancy($hr, $this->unit('SA-CCSEL'));
        $stageA = $this->stage($hr, $vacancyId, 'Tahap A', 0);
        $applicationId = $this->applyAt($candidate, $vacancyId);
        $this->moveToStage($hr, $applicationId, $stageA);

        // Even if a Career Center user somehow reached this endpoint, they are DENY.
        $this->actingAs($careerCenter)->getJson('/applications')
            ->assertStatus(403)->assertJsonPath('error.code', 'AUTH_FORBIDDEN');
        $this->actingAs($careerCenter)->getJson("/applications/{$applicationId}")
            ->assertStatus(403)->assertJsonPath('error.code', 'AUTH_FORBIDDEN');
    }
}
