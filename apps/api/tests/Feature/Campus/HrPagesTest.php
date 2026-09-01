<?php

declare(strict_types=1);

namespace Tests\Feature\Campus;

use App\Domains\Identity\Enums\RoleCode;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Vacancy\VacancyTestCase;

/**
 * Campus Recruitment Frontend Slice v8 — the Admin Kepegawaian Inertia pages
 * under `/kepegawaian/*`. Proves component selection, the persona boundary
 * and campus-only scoping. Business rules stay covered by
 * CampusRecruitmentFoundationTest against the JSON routes.
 */
final class HrPagesTest extends VacancyTestCase
{
    private function hrAdmin(string $email): User
    {
        $user = $this->makeUser($email, UserStatus::Active);
        $this->assignRole($user, RoleCode::HrAdmin);

        return $user;
    }

    private function campusVacancy(User $hr, string $status = 'PUBLISHED'): int
    {
        $unit = DB::table('organizational_units')->insertGetId([
            'code' => 'U'.$this->sequence++, 'name' => 'Unit', 'active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return (int) DB::table('vacancies')->insertGetId([
            'vacancy_code' => 'VC-'.uniqid(), 'slug' => 'campus-'.uniqid(),
            'vacancy_type' => 'CAMPUS_EMPLOYMENT', 'ownership_type' => 'CAMPUS',
            'company_id' => null, 'organizational_unit_id' => $unit,
            'title' => 'Staf Kampus', 'description' => 'x', 'employment_type' => 'FULL_TIME',
            'openings_count' => 1, 'target_audience' => 'PUBLIC', 'application_method' => 'IN_PORTAL',
            'current_status' => $status, 'created_by' => $hr->id,
            'open_at' => now()->subDay(), 'close_at' => now()->addDays(20),
            'published_at' => $status === 'PUBLISHED' ? now()->subDay() : null,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_every_active_admin_page_renders_for_hr_admin(): void
    {
        $hr = $this->hrAdmin('hr-pages@example.test');
        $vacancyId = $this->campusVacancy($hr);

        $map = [
            '/kepegawaian/dashboard' => 'admin-kepegawaian/Dashboard',
            '/kepegawaian/lowongan-kampus' => 'admin-kepegawaian/LowonganKampus',
            '/kepegawaian/lowongan-kampus/baru' => 'admin-kepegawaian/LowonganKampusForm',
            "/kepegawaian/lowongan-kampus/{$vacancyId}" => 'admin-kepegawaian/LowonganKampusForm',
            '/kepegawaian/pelamar' => 'admin-kepegawaian/Pelamar',
            '/kepegawaian/jadwal-seleksi' => 'admin-kepegawaian/JadwalSeleksi',
            '/kepegawaian/penilaian' => 'admin-kepegawaian/Penilaian',
            '/kepegawaian/offering' => 'admin-kepegawaian/Offering',
            '/kepegawaian/outcome-rekrutmen' => 'admin-kepegawaian/OutcomeRekrutmen',
            '/kepegawaian/notifikasi' => 'admin-kepegawaian/Notifikasi',
            '/kepegawaian/pengaturan' => 'admin-kepegawaian/Pengaturan',
        ];

        foreach ($map as $path => $component) {
            $this->actingAs($hr)->get($path)->assertOk()->assertInertia(
                fn (Assert $page) => $page->component($component),
            );
        }
    }

    public function test_admin_pages_are_denied_to_other_personas(): void
    {
        $candidate = $this->makeUser('hr-pages-cand@example.test', UserStatus::Active);
        $this->assignRole($candidate, RoleCode::CandidateAlumni);
        [$recruiter] = $this->verifiedCompanyWithRecruiter('hr-pages-recruiter@example.test');

        foreach (['/kepegawaian/dashboard', '/kepegawaian/lowongan-kampus', '/kepegawaian/pelamar', '/kepegawaian/outcome-rekrutmen'] as $path) {
            $this->actingAs($candidate)->get($path)->assertStatus(403);
            $this->actingAs($recruiter)->get($path)->assertStatus(403);
        }
    }

    public function test_dashboard_and_vacancy_list_are_campus_scoped(): void
    {
        $hr = $this->hrAdmin('hr-pages-scope@example.test');
        $this->campusVacancy($hr, 'DRAFT');
        $this->campusVacancy($hr, 'PUBLISHED');

        // A company vacancy exists and must never appear for the HR admin.
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('hr-pages-scope-co@example.test');
        $this->vacancyAt($recruiter, $company, 'PUBLISHED');

        $this->actingAs($hr)->get('/kepegawaian/lowongan-kampus')->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('admin-kepegawaian/LowonganKampus')->has('items', 2),
        );
        $this->actingAs($hr)->get('/kepegawaian/dashboard')->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('admin-kepegawaian/Dashboard')
                ->where('vacancies_by_status.DRAFT', 1)
                ->where('vacancies_by_status.PUBLISHED', 1),
        );
    }

    public function test_vacancy_detail_is_enumeration_safe_for_a_company_vacancy(): void
    {
        $hr = $this->hrAdmin('hr-pages-enum@example.test');
        [$recruiter, $company] = $this->verifiedCompanyWithRecruiter('hr-pages-enum-co@example.test');
        $companyVacancyId = $this->vacancyAt($recruiter, $company, 'DRAFT');

        $this->actingAs($hr)->get("/kepegawaian/lowongan-kampus/{$companyVacancyId}")->assertNotFound();
    }
}
