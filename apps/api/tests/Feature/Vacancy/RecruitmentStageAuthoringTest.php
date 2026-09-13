<?php

declare(strict_types=1);

namespace Tests\Feature\Vacancy;

use App\Domains\Identity\Enums\RoleCode;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/**
 * Recruitment Stage Authoring Foundation v1 (RS-2, RS-6 — approved and
 * CLOSED). COMPANY vacancies only. move-stage and selector assignment remain
 * entirely out of scope.
 */
final class RecruitmentStageAuthoringTest extends VacancyTestCase
{
    // ---------------------------------------------------------------
    // Routes
    // ---------------------------------------------------------------

    public function test_stage_routes_exist_no_delete_no_selector(): void
    {
        $registered = collect(Route::getRoutes())->map(
            static fn ($route): string => strtoupper(implode('|', $route->methods())).' '.$route->uri(),
        );

        self::assertTrue($registered->contains('GET|HEAD vacancies/{vacancy}/stages'));
        self::assertTrue($registered->contains('POST vacancies/{vacancy}/stages'));
        self::assertTrue($registered->contains('PATCH vacancies/{vacancy}/stages/{stage}'));
        self::assertTrue($registered->contains('POST vacancies/{vacancy}/stages/reorder'));

        self::assertFalse($registered->contains(fn (string $r): bool => str_contains($r, 'DELETE') && str_contains($r, 'stages')));
        // Selector assignment is now its own frozen family (API_CONTRACT.md
        // Part VIII, shipped by GAP-010 / SelectorAssignmentTest). It is a
        // stage-scoped selection route, not part of the stage-authoring
        // surface asserted here, so it is no longer required to be absent.
        self::assertTrue($registered->contains('POST stages/{stage}/selector-assignments'));
        // move-stage is now routed — Application Stage Movement Foundation v1
        // (MS-3, MS-4) — see ApplicationStageMovementTest for its own
        // route-existence assertion. It is an Application-domain route, not
        // a stage-authoring route, so it is deliberately excluded here.
        self::assertFalse($registered->contains(fn (string $r): bool => str_contains($r, 'api/v1') && str_contains($r, 'stage')));
    }

    // ---------------------------------------------------------------
    // CRUD success
    // ---------------------------------------------------------------

    public function test_recruiter_and_admin_can_list_create_update_and_deactivate_stages(): void
    {
        [$admin, $company] = $this->verifiedCompanyWithRecruiter('stage-crud@example.test');
        $recruiter = $this->companyRecruiter($company->id, 'stage-crud-r@example.test');
        $vacancyId = $this->createVacancy($admin, $company);

        foreach ([$admin, $recruiter] as $actor) {
            $this->actingAs($actor)->getJson("/vacancies/{$vacancyId}/stages")->assertOk()->assertJsonPath('data.items', []);
        }

        $created = $this->actingAs($admin)->postJson("/vacancies/{$vacancyId}/stages", [
            'name' => 'Wawancara HR', 'stage_type' => 'INTERVIEW', 'sort_order' => 0, 'active' => true,
            'candidate_visible_label' => 'Wawancara',
        ])->assertCreated();
        $stageId = (int) $created->json('data.id');
        self::assertSame('Wawancara HR', $created->json('data.name'));
        self::assertSame('Wawancara', $created->json('data.candidate_visible_label'));

        $updated = $this->actingAs($recruiter)->patchJson("/vacancies/{$vacancyId}/stages/{$stageId}", [
            'name' => 'Wawancara HR (revised)',
        ])->assertOk();
        self::assertSame('Wawancara HR (revised)', $updated->json('data.name'));

        $deactivated = $this->actingAs($admin)->patchJson("/vacancies/{$vacancyId}/stages/{$stageId}", ['active' => false])->assertOk();
        self::assertFalse($deactivated->json('data.active'));

        self::assertSame(3, DB::table('audit_logs')->where('action', 'vacancy_stage_changed')->where('object_id', $vacancyId)->count());
    }

    /**
     * `sort_order` is create-only. `POST .../stages/reorder` is the sole
     * post-creation ordering path (API_SIZE_REVIEW.md Q-4) -- a PATCH
     * carrying `sort_order` must be rejected outright, never silently
     * dropped, so it can never open a second, non-atomic ordering path.
     */
    public function test_patch_rejects_sort_order_with_zero_mutation_and_zero_audit(): void
    {
        [$admin, $company] = $this->verifiedCompanyWithRecruiter('stage-patch-order@example.test');
        $vacancyId = $this->createVacancy($admin, $company);

        $stageA = (int) $this->actingAs($admin)->postJson("/vacancies/{$vacancyId}/stages", [
            'name' => 'Stage A', 'stage_type' => 'GENERAL', 'sort_order' => 1, 'active' => true,
        ])->assertCreated()->json('data.id');
        $stageB = (int) $this->actingAs($admin)->postJson("/vacancies/{$vacancyId}/stages", [
            'name' => 'Stage B', 'stage_type' => 'GENERAL', 'sort_order' => 2, 'active' => true,
        ])->assertCreated()->json('data.id');

        $auditBefore = DB::table('audit_logs')->where('action', 'vacancy_stage_changed')->where('object_id', $vacancyId)->count();
        $notifBefore = DB::table('notifications')->count();
        $outboxBefore = DB::table('email_outbox')->count();

        $this->actingAs($admin)->patchJson("/vacancies/{$vacancyId}/stages/{$stageB}", ['sort_order' => 1])
            ->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_FAILED');

        self::assertSame(1, (int) DB::table('recruitment_stages')->where('id', $stageA)->value('sort_order'));
        self::assertSame(2, (int) DB::table('recruitment_stages')->where('id', $stageB)->value('sort_order'));
        self::assertSame($auditBefore, DB::table('audit_logs')->where('action', 'vacancy_stage_changed')->where('object_id', $vacancyId)->count());
        self::assertSame($notifBefore, DB::table('notifications')->count());
        self::assertSame($outboxBefore, DB::table('email_outbox')->count());
        self::assertSame(0, DB::table('applications')->count());
    }

    public function test_create_still_accepts_explicit_sort_order(): void
    {
        [$admin, $company] = $this->verifiedCompanyWithRecruiter('stage-create-order@example.test');
        $vacancyId = $this->createVacancy($admin, $company);

        $created = $this->actingAs($admin)->postJson("/vacancies/{$vacancyId}/stages", [
            'name' => 'Stage', 'stage_type' => 'GENERAL', 'sort_order' => 5, 'active' => true,
        ])->assertCreated();

        self::assertSame(5, $created->json('data.sort_order'));
        self::assertSame(5, (int) DB::table('recruitment_stages')->where('id', $created->json('data.id'))->value('sort_order'));
    }

    // ---------------------------------------------------------------
    // Cross-company / authorization
    // ---------------------------------------------------------------

    public function test_cross_company_stage_access_is_not_found(): void
    {
        [$adminA, $companyA] = $this->verifiedCompanyWithRecruiter('stage-cross-a@example.test');
        [$adminB, $companyB] = $this->verifiedCompanyWithRecruiter('stage-cross-b@example.test');
        $vacancyA = $this->createVacancy($adminA, $companyA);
        $stageId = (int) $this->actingAs($adminA)->postJson("/vacancies/{$vacancyA}/stages", [
            'name' => 'Screening', 'stage_type' => 'SCREENING', 'sort_order' => 0, 'active' => true,
        ])->assertCreated()->json('data.id');

        $this->actingAs($adminB)->getJson("/vacancies/{$vacancyA}/stages")->assertNotFound()->assertJsonPath('error.code', 'NOT_FOUND');
        $this->actingAs($adminB)->postJson("/vacancies/{$vacancyA}/stages", [
            'name' => 'Foreign', 'stage_type' => 'SCREENING', 'sort_order' => 1, 'active' => true,
        ])->assertNotFound();
        $this->actingAs($adminB)->patchJson("/vacancies/{$vacancyA}/stages/{$stageId}", ['name' => 'Hijacked'])->assertNotFound();
        $this->actingAs($adminB)->postJson("/vacancies/{$vacancyA}/stages/reorder", ['stage_ids' => [$stageId]])->assertNotFound();

        self::assertSame('Screening', DB::table('recruitment_stages')->where('id', $stageId)->value('name'));
    }

    public function test_a_stage_from_another_vacancy_is_not_found_via_update(): void
    {
        [$admin, $company] = $this->verifiedCompanyWithRecruiter('stage-foreign-vacancy@example.test');
        $vacancyA = $this->createVacancy($admin, $company);
        $vacancyB = $this->createVacancy($admin, $company);
        $stageOfA = (int) $this->actingAs($admin)->postJson("/vacancies/{$vacancyA}/stages", [
            'name' => 'A Stage', 'stage_type' => 'SCREENING', 'sort_order' => 0, 'active' => true,
        ])->assertCreated()->json('data.id');

        $this->actingAs($admin)->patchJson("/vacancies/{$vacancyB}/stages/{$stageOfA}", ['name' => 'Hijack'])
            ->assertNotFound()->assertJsonPath('error.code', 'NOT_FOUND');
    }

    public function test_career_center_and_selector_are_denied_stage_access(): void
    {
        [$admin, $company] = $this->verifiedCompanyWithRecruiter('stage-deny@example.test');
        $vacancyId = $this->createVacancy($admin, $company);

        // Career Center holds general vacancy read visibility (moderation
        // context) so it reaches the manageStages gate and is denied there —
        // 403. Selector holds no vacancy read visibility in VacancyScope at
        // all, so it never even locates the vacancy — 404. Both are correct,
        // enumeration-safe denials; neither reaches stage data.
        $careerCenter = $this->moderator('stage-deny-cc@example.test', RoleCode::CareerCenterStaff);
        $careerCenterManager = $this->moderator('stage-deny-ccm@example.test', RoleCode::CareerCenterManager);

        foreach ([$careerCenter, $careerCenterManager] as $actor) {
            $this->actingAs($actor)->getJson("/vacancies/{$vacancyId}/stages")
                ->assertStatus(403)->assertJsonPath('error.code', 'AUTH_FORBIDDEN');
            $this->actingAs($actor)->postJson("/vacancies/{$vacancyId}/stages", [
                'name' => 'Denied', 'stage_type' => 'SCREENING', 'sort_order' => 0, 'active' => true,
            ])->assertStatus(403)->assertJsonPath('error.code', 'AUTH_FORBIDDEN');
        }

        $selector = $this->moderator('stage-deny-selector@example.test', RoleCode::Selector);
        $this->actingAs($selector)->getJson("/vacancies/{$vacancyId}/stages")->assertNotFound();
        $this->actingAs($selector)->postJson("/vacancies/{$vacancyId}/stages", [
            'name' => 'Denied', 'stage_type' => 'SCREENING', 'sort_order' => 0, 'active' => true,
        ])->assertNotFound();

        self::assertSame(0, DB::table('recruitment_stages')->where('vacancy_id', $vacancyId)->count());
    }

    public function test_candidate_has_no_stage_capability(): void
    {
        [$admin, $company] = $this->verifiedCompanyWithRecruiter('stage-candidate@example.test');
        $vacancyId = $this->createVacancy($admin, $company);

        $candidate = $this->makeUser('stage-candidate-c@example.test', UserStatus::Active);
        $this->assignRole($candidate, RoleCode::CandidateExternal);

        $this->actingAs($candidate)->getJson("/vacancies/{$vacancyId}/stages")->assertNotFound();
    }

    // ---------------------------------------------------------------
    // RS-6 — Super Admin unconditional ALLOW
    // ---------------------------------------------------------------

    public function test_super_admin_manages_stages_without_any_company_membership(): void
    {
        [$admin, $company] = $this->verifiedCompanyWithRecruiter('stage-super@example.test');
        $vacancyId = $this->createVacancy($admin, $company);
        $superAdmin = $this->superAdmin('stage-super-admin@example.test');

        $created = $this->actingAs($superAdmin)->postJson("/vacancies/{$vacancyId}/stages", [
            'name' => 'Super Admin Stage', 'stage_type' => 'SCREENING', 'sort_order' => 0, 'active' => true,
        ])->assertCreated();
        $stageId = (int) $created->json('data.id');

        $this->actingAs($superAdmin)->patchJson("/vacancies/{$vacancyId}/stages/{$stageId}", ['active' => false])->assertOk();
        $this->actingAs($superAdmin)->postJson("/vacancies/{$vacancyId}/stages/reorder", ['stage_ids' => [$stageId]])->assertOk();

        self::assertSame(0, DB::table('company_members')->where('user_id', $superAdmin->id)->count());
    }

    // ---------------------------------------------------------------
    // RS-2 — no vacancy-status / company-verification gate
    // ---------------------------------------------------------------

    public function test_stage_administration_ignores_vacancy_status(): void
    {
        [$admin, $company] = $this->verifiedCompanyWithRecruiter('stage-rs2-vacancy@example.test');
        $vacancyId = $this->createVacancy($admin, $company);

        foreach (['DRAFT', 'PENDING_REVIEW', 'REVISION_REQUIRED', 'APPROVED', 'SCHEDULED', 'PUBLISHED', 'CLOSED', 'EXPIRED', 'SUSPENDED', 'REJECTED'] as $status) {
            DB::table('vacancies')->where('id', $vacancyId)->update(['current_status' => $status]);

            $created = $this->actingAs($admin)->postJson("/vacancies/{$vacancyId}/stages", [
                'name' => "Stage for {$status}", 'stage_type' => 'SCREENING', 'sort_order' => 0, 'active' => true,
            ])->assertCreated();
            $stageId = (int) $created->json('data.id');

            $this->actingAs($admin)->patchJson("/vacancies/{$vacancyId}/stages/{$stageId}", ['active' => false])->assertOk();
            $this->actingAs($admin)->getJson("/vacancies/{$vacancyId}/stages")->assertOk();

            // Never VACANCY_NOT_EDITABLE, never a company-verification error.
            self::assertSame($status, DB::table('vacancies')->where('id', $vacancyId)->value('current_status'));
        }
    }

    public function test_stage_administration_ignores_company_verification_status(): void
    {
        [$admin, $company] = $this->verifiedCompanyWithRecruiter('stage-rs2-company@example.test');
        $vacancyId = $this->createVacancy($admin, $company);

        foreach (['SUSPENDED', 'PENDING_VERIFICATION', 'REVISION_REQUIRED', 'REJECTED', 'VERIFIED'] as $status) {
            DB::table('companies')->where('id', $company->id)->update(['verification_status' => $status]);

            $this->actingAs($admin)->postJson("/vacancies/{$vacancyId}/stages", [
                'name' => "Stage for company {$status}", 'stage_type' => 'SCREENING', 'sort_order' => 0, 'active' => true,
            ])->assertCreated();
        }

        self::assertSame(5, DB::table('recruitment_stages')->where('vacancy_id', $vacancyId)->count());
    }

    // ---------------------------------------------------------------
    // Stage mutation never touches vacancy status / current_stage_id / RA-2
    // ---------------------------------------------------------------

    public function test_stage_mutation_never_touches_vacancy_status_or_application_current_stage(): void
    {
        [$admin, $company] = $this->verifiedCompanyWithRecruiter('stage-rs2-isolation@example.test');
        $vacancyId = $this->createVacancy($admin, $company);
        DB::table('vacancies')->where('id', $vacancyId)->update(['current_status' => 'PUBLISHED']);

        $stageId = (int) $this->actingAs($admin)->postJson("/vacancies/{$vacancyId}/stages", [
            'name' => 'Screening', 'stage_type' => 'SCREENING', 'sort_order' => 0, 'active' => true,
        ])->assertCreated()->json('data.id');
        $this->actingAs($admin)->patchJson("/vacancies/{$vacancyId}/stages/{$stageId}", ['name' => 'Screening (revised)'])->assertOk();

        self::assertSame('PUBLISHED', DB::table('vacancies')->where('id', $vacancyId)->value('current_status'));
        self::assertSame(0, DB::table('applications')->where('current_stage_id', $stageId)->count());
        self::assertSame(0, DB::table('selection_stage_assignments')->count());
    }

    public function test_ra2_processing_gate_is_untouched_by_stage_authoring(): void
    {
        [$admin, $company] = $this->verifiedCompanyWithRecruiter('stage-ra2-untouched@example.test');
        $vacancyId = $this->createVacancy($admin, $company);
        DB::table('vacancies')->where('id', $vacancyId)->update(['current_status' => 'SUSPENDED']);

        // Stage authoring succeeds even though the vacancy is SUSPENDED (RS-2).
        $stageId = (int) $this->actingAs($admin)->postJson("/vacancies/{$vacancyId}/stages", [
            'name' => 'Screening', 'stage_type' => 'SCREENING', 'sort_order' => 0, 'active' => true,
        ])->assertCreated()->json('data.id');

        // RA-2 still blocks transitioning an existing applicant while the
        // vacancy is SUSPENDED — stage authoring did not weaken it.
        self::assertNotNull($stageId);
        self::assertSame('SUSPENDED', DB::table('vacancies')->where('id', $vacancyId)->value('current_status'));
    }

    // ---------------------------------------------------------------
    // Reorder — whole-set atomic
    // ---------------------------------------------------------------

    public function test_reorder_accepts_a_full_ordered_set_and_writes_sort_order_atomically(): void
    {
        [$admin, $company] = $this->verifiedCompanyWithRecruiter('stage-reorder@example.test');
        $vacancyId = $this->createVacancy($admin, $company);

        $ids = [];
        foreach (['Screening', 'Interview', 'Offer Review'] as $i => $name) {
            $ids[] = (int) $this->actingAs($admin)->postJson("/vacancies/{$vacancyId}/stages", [
                'name' => $name, 'stage_type' => 'GENERAL', 'sort_order' => $i, 'active' => true,
            ])->assertCreated()->json('data.id');
        }

        $reversed = array_reverse($ids);
        $response = $this->actingAs($admin)->postJson("/vacancies/{$vacancyId}/stages/reorder", ['stage_ids' => $reversed])->assertOk();

        self::assertSame($reversed, $response->json('data.items.*.id'));
        foreach ($reversed as $position => $stageId) {
            self::assertSame($position, (int) DB::table('recruitment_stages')->where('id', $stageId)->value('sort_order'));
        }
    }

    public function test_reorder_rejects_a_partial_foreign_or_duplicate_set_with_zero_mutation(): void
    {
        [$admin, $company] = $this->verifiedCompanyWithRecruiter('stage-reorder-invalid@example.test');
        [$otherAdmin, $otherCompany] = $this->verifiedCompanyWithRecruiter('stage-reorder-invalid-other@example.test');
        $vacancyId = $this->createVacancy($admin, $company);
        $otherVacancyId = $this->createVacancy($otherAdmin, $otherCompany);

        $idA = (int) $this->actingAs($admin)->postJson("/vacancies/{$vacancyId}/stages", [
            'name' => 'A', 'stage_type' => 'GENERAL', 'sort_order' => 0, 'active' => true,
        ])->assertCreated()->json('data.id');
        $idB = (int) $this->actingAs($admin)->postJson("/vacancies/{$vacancyId}/stages", [
            'name' => 'B', 'stage_type' => 'GENERAL', 'sort_order' => 1, 'active' => true,
        ])->assertCreated()->json('data.id');
        $foreignId = (int) $this->actingAs($otherAdmin)->postJson("/vacancies/{$otherVacancyId}/stages", [
            'name' => 'Foreign', 'stage_type' => 'GENERAL', 'sort_order' => 0, 'active' => true,
        ])->assertCreated()->json('data.id');

        // Partial (missing B).
        $this->actingAs($admin)->postJson("/vacancies/{$vacancyId}/stages/reorder", ['stage_ids' => [$idA]])
            ->assertStatus(422)->assertJsonPath('error.code', 'STAGE_NOT_IN_VACANCY');

        // Foreign id included.
        $this->actingAs($admin)->postJson("/vacancies/{$vacancyId}/stages/reorder", ['stage_ids' => [$idA, $idB, $foreignId]])
            ->assertStatus(422)->assertJsonPath('error.code', 'STAGE_NOT_IN_VACANCY');

        // Duplicate id.
        $this->actingAs($admin)->postJson("/vacancies/{$vacancyId}/stages/reorder", ['stage_ids' => [$idA, $idA]])
            ->assertStatus(422)->assertJsonPath('error.code', 'STAGE_NOT_IN_VACANCY');

        self::assertSame(0, (int) DB::table('recruitment_stages')->where('id', $idA)->value('sort_order'));
        self::assertSame(1, (int) DB::table('recruitment_stages')->where('id', $idB)->value('sort_order'));
        self::assertSame(0, DB::table('audit_logs')->where('action', 'vacancy_stage_changed')->where('change_summary', 'like', '%reordered%')->count());
    }

    // ---------------------------------------------------------------
    // Fixtures
    // ---------------------------------------------------------------

    private function companyRecruiter(int $companyId, string $email): User
    {
        $user = $this->makeUser($email, UserStatus::Active);
        $this->assignRole($user, RoleCode::CompanyRecruiter);
        $this->addMember($companyId, $user, 'COMPANY_RECRUITER');

        return $user->fresh();
    }

    private function superAdmin(string $email): User
    {
        $user = $this->makeUser($email, UserStatus::Active);
        $this->assignRole($user, RoleCode::SuperAdmin);

        return $user;
    }
}
