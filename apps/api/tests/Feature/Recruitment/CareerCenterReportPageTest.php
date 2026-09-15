<?php

declare(strict_types=1);

namespace Tests\Feature\Recruitment;

use App\Domains\Company\Enums\CompanyStatus;
use App\Domains\Identity\Enums\RoleCode;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Vacancy\VacancyTestCase;

final class CareerCenterReportPageTest extends VacancyTestCase
{
    public function test_report_renders_literal_company_and_company_vacancy_counts(): void
    {
        $this->companyWithRecruiter('report-pending@example.test', CompanyStatus::PendingVerification);
        [$recruiter, $company] = $this->companyWithRecruiter('report-verified@example.test', CompanyStatus::Verified);
        $this->vacancyAt($recruiter, $company, 'PUBLISHED');

        $moderator = $this->moderator('report-moderator@example.test');

        $this->actingAs($moderator)->get('/laporan')->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('career-center/Laporan')
                ->where('companies_by_status.PENDING_VERIFICATION', 1)
                ->where('companies_by_status.VERIFIED', 1)
                ->where('vacancies_by_status.PUBLISHED', 1),
        );
    }

    public function test_report_is_available_to_both_career_center_roles(): void
    {
        $manager = $this->moderator('report-manager@example.test', RoleCode::CareerCenterManager);

        $this->actingAs($manager)->get('/laporan')->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('career-center/Laporan'),
        );
    }

    public function test_report_rejects_non_career_center_personas(): void
    {
        [$recruiter] = $this->companyWithRecruiter('report-recruiter@example.test', CompanyStatus::Verified);
        $candidate = $this->userWithRole('report-candidate@example.test', RoleCode::CandidateAlumni);
        $hr = $this->userWithRole('report-hr@example.test', RoleCode::HrAdmin);

        foreach ([$recruiter, $candidate, $hr] as $actor) {
            $this->actingAs($actor)->get('/laporan')->assertStatus(403);
        }
    }

    public function test_report_never_includes_campus_vacancies(): void
    {
        $hr = $this->userWithRole('report-campus-hr@example.test', RoleCode::HrAdmin);
        $unitId = DB::table('organizational_units')->insertGetId([
            'code' => 'REPORT-UNIT',
            'name' => 'Unit Laporan',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('vacancies')->insert([
            'vacancy_code' => 'REPORT-CAMPUS',
            'slug' => 'report-campus',
            'vacancy_type' => 'CAMPUS_EMPLOYMENT',
            'ownership_type' => 'CAMPUS',
            'company_id' => null,
            'organizational_unit_id' => $unitId,
            'title' => 'Lowongan Kampus',
            'description' => 'Tidak boleh masuk laporan Career Center.',
            'employment_type' => 'FULL_TIME',
            'openings_count' => 1,
            'target_audience' => 'PUBLIC',
            'application_method' => 'IN_PORTAL',
            'current_status' => 'PUBLISHED',
            'created_by' => $hr->id,
            'open_at' => now()->subDay(),
            'close_at' => now()->addWeek(),
            'published_at' => now()->subDay(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $moderator = $this->moderator('report-scope-moderator@example.test');

        $this->actingAs($moderator)->get('/laporan')->assertOk()->assertInertia(
            fn (Assert $page) => $page
                ->component('career-center/Laporan')
                ->where('vacancies_by_status', []),
        );
    }

    private function userWithRole(string $email, RoleCode $role): User
    {
        $user = $this->makeUser($email, UserStatus::Active);
        $this->assignRole($user, $role);

        return $user;
    }
}
