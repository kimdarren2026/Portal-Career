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

final class CareerCenterReadOnlyModulesTest extends VacancyTestCase
{
    public function test_all_read_only_modules_render_for_career_center(): void
    {
        $moderator = $this->moderator('readonly-modules@example.test');

        $components = [
            '/kemitraan' => 'career-center/Kemitraan',
            '/alumni-outcome' => 'career-center/AlumniOutcome',
            '/template-email' => 'career-center/TemplateEmail',
            '/pengaturan-moderasi' => 'career-center/PengaturanModerasi',
        ];

        foreach ($components as $path => $component) {
            $this->actingAs($moderator)->get($path)->assertOk()->assertInertia(
                fn (Assert $page) => $page->component($component),
            );
        }
    }

    public function test_partnership_register_returns_safe_read_only_data(): void
    {
        [, $company] = $this->companyWithRecruiter('readonly-partner@example.test', CompanyStatus::Verified);
        DB::table('partnerships')->insert([
            'company_id' => $company->id,
            'partnership_type' => 'RECRUITMENT',
            'agreement_number' => 'AGR-READ-ONLY',
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'status' => 'ACTIVE',
            'campus_pic' => 'PIC Kampus',
            'company_pic' => 'PIC Perusahaan',
            'document_reference' => 'private/internal/agreement.pdf',
            'notes' => 'Kemitraan aktif.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $moderator = $this->moderator('readonly-partner-mod@example.test');

        $this->actingAs($moderator)->get('/kemitraan')->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('career-center/Kemitraan')
                ->has('items', 1)
                ->where('items.0.company_name', $company->name)
                ->where('items.0.currently_active', true)
                ->where('items.0.document_available', true)
                ->missing('items.0.document_reference')
                ->where('active_count', 1)
                ->where('by_status.ACTIVE', 1),
        );
    }

    public function test_alumni_outcome_page_returns_external_aggregates_without_identity(): void
    {
        [$recruiter, $company] = $this->companyWithRecruiter('readonly-outcome-company@example.test', CompanyStatus::Verified);
        $vacancyId = $this->createVacancy($recruiter, $company, [
            'application_method' => 'EXTERNAL_ATS',
            'external_ats_url' => 'https://ats.example.test/apply',
        ]);
        $candidate = $this->userWithRole('readonly-outcome-candidate@example.test', RoleCode::CandidateAlumni);
        $profileId = DB::table('candidate_profiles')->insertGetId([
            'user_id' => $candidate->id,
            'current_candidate_type' => 'ALUMNI',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $eventId = DB::table('external_apply_events')->insertGetId([
            'candidate_profile_id' => $profileId,
            'vacancy_id' => $vacancyId,
            'event_type' => 'EXTERNAL_APPLY_STARTED',
            'destination_url_reference' => 'https://ats.example.test/apply',
            'started_at' => now(),
            'confirmation_status' => 'CONFIRMED',
            'confirmed_at' => now(),
            'confirmed_by' => $candidate->id,
            'created_at' => now(),
        ]);
        DB::table('recruitment_outcomes')->insert([
            'source_type' => 'EXTERNAL_APPLY',
            'application_id' => null,
            'external_apply_event_id' => $eventId,
            'outcome' => 'HIRED',
            'reported_by_source' => 'CANDIDATE',
            'confirmed_by' => $candidate->id,
            'confirmed_at' => now(),
            'created_at' => now(),
        ]);

        $moderator = $this->moderator('readonly-outcome-mod@example.test');

        $this->actingAs($moderator)->get('/alumni-outcome')->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('career-center/AlumniOutcome')
                ->where('counts.external_apply_started', 1)
                ->where('counts.external_apply_confirmed', 1)
                ->where('counts.outcomes_recorded', 1)
                ->where('outcomes_by_type.HIRED', 1)
                ->missing('items')
                ->missing('candidate'),
        );
    }

    public function test_read_only_modules_reject_non_career_center_personas(): void
    {
        $candidate = $this->userWithRole('readonly-denied@example.test', RoleCode::CandidateAlumni);

        foreach (['/kemitraan', '/alumni-outcome', '/template-email', '/pengaturan-moderasi'] as $path) {
            $this->actingAs($candidate)->get($path)->assertStatus(403);
        }
    }

    private function userWithRole(string $email, RoleCode $role): User
    {
        $user = $this->makeUser($email, UserStatus::Active);
        $this->assignRole($user, $role);

        return $user;
    }
}
