<?php

declare(strict_types=1);

namespace Tests\Feature\Vacancy;

use App\Domains\Identity\Enums\RoleCode;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/**
 * PO decision VA-4 — company authoring never derives from the global
 * SUPER_ADMIN role. It derives from an ACTIVE company membership, and from
 * nothing else. Super Admin READ is unchanged, and no moderation capability is
 * settled or implemented here.
 */
final class VacancyAuthoringAuthorityTest extends VacancyTestCase
{
    public function test_global_super_admin_without_membership_cannot_create_a_company_vacancy(): void
    {
        [, $company] = $this->verifiedCompanyWithRecruiter('va4-create@example.test');
        $superAdmin = $this->superAdmin('va4-sa-create@example.test');

        $this->actingAs($superAdmin)->postJson("/companies/{$company->id}/vacancies", $this->payload())
            ->assertForbidden()->assertJsonPath('error.code', 'AUTH_FORBIDDEN');

        self::assertSame(0, DB::table('vacancies')->count());
    }

    public function test_global_super_admin_without_membership_cannot_edit_a_company_vacancy(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('va4-edit@example.test');
        $id = $this->createVacancy($recruiter, $company, ['title' => 'Recruiter Title']);
        $superAdmin = $this->superAdmin('va4-sa-edit@example.test');

        // Read stays allowed — break-glass read capability is unchanged.
        $this->actingAs($superAdmin)->getJson("/vacancies/{$id}")->assertOk();

        $this->actingAs($superAdmin)->patchJson("/vacancies/{$id}", ['title' => 'Super Admin Title'])
            ->assertForbidden()->assertJsonPath('error.code', 'AUTH_FORBIDDEN');

        self::assertSame('Recruiter Title', DB::table('vacancies')->where('id', $id)->value('title'));
        self::assertSame(1, $this->latestVersion($id));
    }

    public function test_global_super_admin_without_membership_cannot_manage_screening_questions(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('va4-screening@example.test');
        $id = $this->createVacancy($recruiter, $company);
        $questionId = (int) $this->actingAs($recruiter)->postJson("/vacancies/{$id}/screening-questions", [
            'question_text' => 'Owned by the company', 'question_type' => 'SHORT_TEXT',
            'required' => true, 'active' => true, 'sort_order' => 0,
        ])->assertCreated()->json('data.id');

        $superAdmin = $this->superAdmin('va4-sa-screening@example.test');

        $this->actingAs($superAdmin)->postJson("/vacancies/{$id}/screening-questions", [
            'question_text' => 'Super Admin question', 'question_type' => 'SHORT_TEXT',
            'required' => true, 'active' => true, 'sort_order' => 1,
        ])->assertForbidden()->assertJsonPath('error.code', 'AUTH_FORBIDDEN');

        $this->actingAs($superAdmin)->patchJson("/vacancies/{$id}/screening-questions/{$questionId}", ['active' => false])
            ->assertForbidden()->assertJsonPath('error.code', 'AUTH_FORBIDDEN');

        self::assertSame(1, DB::table('vacancy_screening_questions')->where('vacancy_id', $id)->count());
        self::assertTrue((bool) DB::table('vacancy_screening_questions')->where('id', $questionId)->value('active'));
    }

    public function test_super_admin_with_active_company_admin_membership_authors_through_that_membership(): void
    {
        [, $company] = $this->verifiedCompanyWithRecruiter('va4-admin-member@example.test');
        $superAdmin = $this->superAdmin('va4-sa-admin@example.test');
        $this->addMember((int) $company->id, $superAdmin, 'COMPANY_ADMIN');

        $id = (int) $this->actingAs($superAdmin)->postJson("/companies/{$company->id}/vacancies", $this->payload())
            ->assertCreated()->json('data.id');

        $this->actingAs($superAdmin)->patchJson("/vacancies/{$id}", ['title' => 'Edited As Member'])->assertOk();
        $this->actingAs($superAdmin)->postJson("/vacancies/{$id}/screening-questions", [
            'question_text' => 'Member question', 'question_type' => 'SHORT_TEXT',
            'required' => false, 'active' => true, 'sort_order' => 0,
        ])->assertCreated();

        self::assertSame((int) $company->id, (int) DB::table('vacancies')->where('id', $id)->value('company_id'));
        self::assertSame($superAdmin->id, (int) DB::table('vacancies')->where('id', $id)->value('created_by'));
    }

    public function test_super_admin_with_active_recruiter_membership_gets_exactly_that_membership_capability(): void
    {
        [, $company] = $this->verifiedCompanyWithRecruiter('va4-recruiter-member@example.test');
        $superAdmin = $this->superAdmin('va4-sa-recruiter@example.test');
        $this->addMember((int) $company->id, $superAdmin, 'COMPANY_RECRUITER');

        $id = (int) $this->actingAs($superAdmin)->postJson("/companies/{$company->id}/vacancies", $this->payload())
            ->assertCreated()->json('data.id');
        $this->actingAs($superAdmin)->patchJson("/vacancies/{$id}", ['title' => 'Recruiter Member Edit'])->assertOk();

        // The frozen authoring capability is identical for both membership
        // roles. Moderation is a separate capability neither of them gains:
        // the moderation routes exist now, but a company member is barred from
        // them by the B-3 conflict-of-interest rule.
        self::assertSame('Recruiter Member Edit', DB::table('vacancies')->where('id', $id)->value('title'));
        $this->actingAs($superAdmin)->postJson("/vacancies/{$id}/approve")
            ->assertForbidden()->assertJsonPath('error.code', 'AUTH_FORBIDDEN');
    }

    public function test_membership_in_one_company_grants_nothing_over_another_company(): void
    {
        [, $companyA] = $this->verifiedCompanyWithRecruiter('va4-company-a@example.test');
        [$recruiterB, $companyB] = $this->verifiedCompanyWithRecruiter('va4-company-b@example.test');
        $vacancyB = $this->createVacancy($recruiterB, $companyB, ['title' => 'Company B Title']);

        $superAdmin = $this->superAdmin('va4-sa-cross@example.test');
        $this->addMember((int) $companyA->id, $superAdmin, 'COMPANY_ADMIN');

        $this->actingAs($superAdmin)->postJson("/companies/{$companyB->id}/vacancies", $this->payload())
            ->assertForbidden()->assertJsonPath('error.code', 'AUTH_FORBIDDEN');
        $this->actingAs($superAdmin)->patchJson("/vacancies/{$vacancyB}", ['title' => 'Cross Company Edit'])
            ->assertForbidden()->assertJsonPath('error.code', 'AUTH_FORBIDDEN');

        self::assertSame('Company B Title', DB::table('vacancies')->where('id', $vacancyB)->value('title'));
    }

    public function test_a_revoked_membership_stays_worthless_even_for_a_super_admin(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('va4-revoked@example.test');
        $id = $this->createVacancy($recruiter, $company, ['title' => 'Untouched By Revoked']);

        $superAdmin = $this->superAdmin('va4-sa-revoked@example.test');
        $this->addMember((int) $company->id, $superAdmin, 'COMPANY_ADMIN', 'REVOKED');

        $this->actingAs($superAdmin)->postJson("/companies/{$company->id}/vacancies", $this->payload())
            ->assertForbidden()->assertJsonPath('error.code', 'AUTH_FORBIDDEN');
        $this->actingAs($superAdmin)->patchJson("/vacancies/{$id}", ['title' => 'Revoked Edit'])
            ->assertForbidden()->assertJsonPath('error.code', 'AUTH_FORBIDDEN');

        self::assertSame('Untouched By Revoked', DB::table('vacancies')->where('id', $id)->value('title'));
        self::assertSame(1, DB::table('vacancies')->count());
    }

    private function superAdmin(string $email): User
    {
        $user = $this->makeUser($email, UserStatus::Active);
        $this->assignRole($user, RoleCode::SuperAdmin);

        return $user;
    }
}
