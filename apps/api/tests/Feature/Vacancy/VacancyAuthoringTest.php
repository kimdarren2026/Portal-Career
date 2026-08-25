<?php

declare(strict_types=1);

namespace Tests\Feature\Vacancy;

use App\Domains\Company\Enums\CompanyStatus;
use App\Domains\Identity\Enums\RoleCode;
use App\Domains\Identity\Enums\UserStatus;
use Illuminate\Support\Facades\DB;

/** Create, validation and the company VERIFIED gate. */
final class VacancyAuthoringTest extends VacancyTestCase
{
    public function test_verified_company_creates_a_draft_company_employment_vacancy(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('author@example.test');

        $response = $this->actingAs($recruiter)
            ->postJson("/companies/{$company->id}/vacancies", $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.current_status', 'DRAFT')
            ->assertJsonPath('data.vacancy_type', 'COMPANY_EMPLOYMENT')
            ->assertJsonPath('data.ownership_type', 'COMPANY')
            ->assertJsonPath('data.company_id', (int) $company->id);

        $id = (int) $response->json('data.id');
        $this->assertDatabaseHas('vacancies', ['id' => $id, 'created_by' => $recruiter->id, 'organizational_unit_id' => null]);
        $this->assertNotEmpty($response->json('data.vacancy_code'));
        $this->assertNotEmpty($response->json('data.slug'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'vacancy_created', 'object_id' => $id]);
    }

    public function test_verified_non_partner_company_may_create(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('nonpartner@example.test');
        $this->assertDatabaseCount('partnerships', 0);

        $this->createVacancy($recruiter, $company);

        // INV-003: verification is never partnership, and none is created.
        $this->assertDatabaseCount('partnerships', 0);
    }

    public function test_company_that_is_not_verified_cannot_create(): void
    {
        foreach ([CompanyStatus::Draft, CompanyStatus::PendingVerification, CompanyStatus::RevisionRequired,
            CompanyStatus::Rejected, CompanyStatus::Suspended] as $index => $status) {
            [$recruiter, $company] = $this->companyWithRecruiter("unverified{$index}@example.test", $status);

            $this->actingAs($recruiter)
                ->postJson("/companies/{$company->id}/vacancies", $this->payload())
                ->assertForbidden()
                ->assertJsonPath('error.code', 'VACANCY_COMPANY_NOT_VERIFIED');
        }

        $this->assertDatabaseCount('vacancies', 0);
    }

    public function test_client_cannot_bypass_the_gate_or_inject_server_owned_fields(): void
    {
        [$recruiter, $verified] = $this->verifiedCompanyWithRecruiter('inject@example.test');
        [, $unverified] = $this->companyWithRecruiter('other-unverified@example.test', CompanyStatus::Draft);

        $response = $this->actingAs($recruiter)->postJson("/companies/{$verified->id}/vacancies", $this->payload([
            'company_id' => $unverified->id,
            'ownership_type' => 'CAMPUS',
            'current_status' => 'PUBLISHED',
            'published_at' => now()->toIso8601String(),
            'closed_at' => now()->toIso8601String(),
            'created_by' => 999999,
            'vacancy_code' => 'ATTACKER',
            'slug' => 'attacker',
        ]))->assertCreated();

        $id = (int) $response->json('data.id');
        $row = DB::table('vacancies')->where('id', $id)->first();
        $this->assertSame((int) $verified->id, (int) $row->company_id);
        $this->assertSame('COMPANY', $row->ownership_type);
        $this->assertSame('DRAFT', $row->current_status);
        $this->assertNull($row->published_at);
        $this->assertNull($row->closed_at);
        $this->assertSame((int) $recruiter->id, (int) $row->created_by);
        $this->assertNotSame('ATTACKER', $row->vacancy_code);
        $this->assertNotSame('attacker', $row->slug);
    }

    public function test_campus_employment_is_rejected_and_internship_is_accepted(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('types@example.test');

        $this->actingAs($recruiter)
            ->postJson("/companies/{$company->id}/vacancies", $this->payload(['vacancy_type' => 'CAMPUS_EMPLOYMENT']))
            ->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_FAILED');

        $this->actingAs($recruiter)
            ->postJson("/companies/{$company->id}/vacancies", $this->payload(['vacancy_type' => 'INTERNSHIP']))
            ->assertCreated()->assertJsonPath('data.vacancy_type', 'INTERNSHIP');
    }

    public function test_all_four_audiences_are_accepted_and_anything_else_is_rejected(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('audience@example.test');

        foreach (['PUBLIC', 'ALUMNI_ONLY', 'FINAL_YEAR_AND_ALUMNI', 'INTERNAL'] as $audience) {
            $this->actingAs($recruiter)
                ->postJson("/companies/{$company->id}/vacancies", $this->payload(['target_audience' => $audience]))
                ->assertCreated()->assertJsonPath('data.target_audience', $audience);
        }

        foreach (['ACTIVE_STUDENT', 'FRESH_GRADUATE', 'EVERYONE'] as $invalid) {
            $this->actingAs($recruiter)
                ->postJson("/companies/{$company->id}/vacancies", $this->payload(['target_audience' => $invalid]))
                ->assertStatus(422)->assertJsonPath('error.code', 'VACANCY_TARGET_AUDIENCE_INVALID');
        }
    }

    public function test_draft_may_be_created_without_the_eventual_submit_content(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('incomplete@example.test');

        // No dates, no geography, no requirements, no screening questions, no
        // salary. DRAFT is allowed to be incomplete.
        $id = $this->createVacancy($recruiter, $company);

        $row = DB::table('vacancies')->where('id', $id)->first();
        $this->assertNull($row->open_at);
        $this->assertNull($row->close_at);
        $this->assertNull($row->salary_min);
        $this->assertSame(0, DB::table('vacancy_requirements')->where('vacancy_id', $id)->count());
    }

    public function test_close_at_must_be_after_open_at(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('dates@example.test');
        $open = now()->addDays(5);

        foreach ([$open->copy()->subDay(), $open->copy()] as $closeAt) {
            $this->actingAs($recruiter)->postJson("/companies/{$company->id}/vacancies", $this->payload([
                'open_at' => $open->toIso8601String(), 'close_at' => $closeAt->toIso8601String(),
            ]))->assertStatus(422)->assertJsonPath('error.code', 'VACANCY_CLOSE_BEFORE_OPEN');
        }

        $this->actingAs($recruiter)->postJson("/companies/{$company->id}/vacancies", $this->payload([
            'open_at' => $open->toIso8601String(), 'close_at' => $open->copy()->addDay()->toIso8601String(),
        ]))->assertCreated();
    }

    public function test_external_ats_requires_an_https_url_and_in_portal_does_not(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('ats@example.test');

        $this->actingAs($recruiter)->postJson("/companies/{$company->id}/vacancies", $this->payload([
            'application_method' => 'EXTERNAL_ATS',
        ]))->assertStatus(422)->assertJsonPath('error.code', 'VACANCY_EXTERNAL_ATS_URL_REQUIRED');

        $this->actingAs($recruiter)->postJson("/companies/{$company->id}/vacancies", $this->payload([
            'application_method' => 'EXTERNAL_ATS', 'external_ats_url' => 'http://ats.example.test/apply',
        ]))->assertStatus(422)->assertJsonPath('error.code', 'VACANCY_EXTERNAL_ATS_URL_INVALID');

        $this->actingAs($recruiter)->postJson("/companies/{$company->id}/vacancies", $this->payload([
            'application_method' => 'EXTERNAL_ATS', 'external_ats_url' => 'https://ats.example.test/apply',
        ]))->assertCreated()->assertJsonPath('data.application_method', 'EXTERNAL_ATS');

        // IN_PORTAL needs no external URL.
        $this->actingAs($recruiter)->postJson("/companies/{$company->id}/vacancies", $this->payload())
            ->assertCreated()->assertJsonPath('data.external_ats_url', null);
    }

    public function test_organizational_unit_is_rejected_on_a_company_vacancy(): void
    {
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('ownership@example.test');

        $this->actingAs($recruiter)->postJson("/companies/{$company->id}/vacancies", $this->payload([
            'organizational_unit_id' => 1,
        ]))->assertStatus(422)->assertJsonPath('error.code', 'VACANCY_OWNERSHIP_INVALID');
    }

    public function test_career_center_and_auditor_cannot_author(): void
    {
        [, $company] = $this->verifiedCompanyWithRecruiter('deny@example.test');

        foreach ([RoleCode::CareerCenterStaff, RoleCode::CareerCenterManager, RoleCode::Auditor] as $index => $role) {
            $actor = $this->makeUser("deny-actor{$index}@example.test", UserStatus::Active);
            $this->assignRole($actor, $role);

            // Career Center reads company vacancies but never authors or edits
            // them; Auditor holds no write ability anywhere.
            $this->actingAs($actor)
                ->postJson("/companies/{$company->id}/vacancies", $this->payload())
                ->assertForbidden()->assertJsonPath('error.code', 'AUTH_FORBIDDEN');
        }
    }
}
