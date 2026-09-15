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

/**
 * Career Center read-only reference/recap pages (Alumni & Outcome, Template
 * Email, Pengaturan Moderasi). Kemitraan is intentionally excluded — the
 * partnership lifecycle remains an open business decision and is not
 * activated (reconciliation audit, 2026-09-15).
 */
final class CareerCenterReadOnlyModulesTest extends VacancyTestCase
{
    public function test_all_read_only_modules_render_for_career_center(): void
    {
        $moderator = $this->moderator('readonly-modules@example.test');

        $components = [
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

        foreach (['/alumni-outcome', '/template-email', '/pengaturan-moderasi'] as $path) {
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
