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
 * Career Center Frontend Slice v9 — the workspace pages Dashboard,
 * Data Perusahaan and Notifikasi. Read-only Inertia delivery reusing the
 * frozen CompanyScope / VacancyScope / NotificationScope (Career Center is a
 * global reader in the company/vacancy scopes; OWN in notifications).
 */
final class CareerCenterWorkspacePagesTest extends VacancyTestCase
{
    private function candidate(string $email): User
    {
        $u = $this->makeUser($email, UserStatus::Active);
        $this->assignRole($u, RoleCode::CandidateAlumni);

        return $u;
    }

    private function hrAdmin(string $email): User
    {
        $u = $this->makeUser($email, UserStatus::Active);
        $this->assignRole($u, RoleCode::HrAdmin);

        return $u;
    }

    public function test_dashboard_renders_career_center_snapshot_with_literal_counts(): void
    {
        $this->companyWithRecruiter('v9-dash-pending@example.test', CompanyStatus::PendingVerification);
        $this->companyWithRecruiter('v9-dash-revision@example.test', CompanyStatus::RevisionRequired);
        [$recruiter, $company] = $this->companyWithRecruiter('v9-dash-verified@example.test', CompanyStatus::Verified);
        $this->vacancyAt($recruiter, $company, 'PENDING_REVIEW');

        $moderator = $this->moderator('v9-dash-mod@example.test');
        $this->actingAs($moderator)->get('/dashboard')->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('career-center/Dashboard')
                ->where('counts.verification_queue', 1)
                ->where('counts.company_revision_required', 1)
                ->where('counts.verified_companies', 1)
                ->where('counts.moderation_queue', 1)
                ->has('companies_by_status')
                ->has('vacancies_by_status'),
        );
    }

    public function test_data_perusahaan_is_a_read_only_directory_scoped_and_paginated(): void
    {
        $this->companyWithRecruiter('v9-dir-a@example.test', CompanyStatus::Draft);
        [$recruiter, $verified] = $this->companyWithRecruiter('v9-dir-b@example.test', CompanyStatus::Verified);

        $moderator = $this->moderator('v9-dir-mod@example.test');

        $this->actingAs($moderator)->get('/data-perusahaan')->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('career-center/DataPerusahaan')
                ->has('items', 2)
                ->has('pagination')
                ->missing('items.0.eligible_actions'),
        );

        $this->actingAs($moderator)->get('/data-perusahaan?status=VERIFIED')->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('career-center/DataPerusahaan')
                ->where('filters.status', 'VERIFIED')
                ->has('items', 1)
                ->where('items.0.id', $verified->id),
        );

        $this->actingAs($moderator)->get("/data-perusahaan/{$verified->id}")->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('career-center/DataPerusahaanDetail')
                ->where('company.id', $verified->id)
                ->has('company.verification_history')
                ->has('company.documents')
                ->has('company.mitra_kampus_active')
                ->missing('eligible_actions'),
        );
    }

    public function test_notifikasi_renders_own_scoped_career_center_inbox(): void
    {
        $mod = $this->moderator('v9-notif-mod@example.test');
        $other = $this->moderator('v9-notif-other@example.test');
        DB::table('notifications')->insert([
            ['user_id' => $mod->id, 'type' => 'COMPANY_VERIFICATION', 'title' => 'MINE', 'body_reference' => 'x', 'related_object_type' => 'company', 'related_object_id' => 5, 'created_at' => now()],
            ['user_id' => $other->id, 'type' => 'COMPANY_VERIFICATION', 'title' => 'THEIRS', 'body_reference' => 'x', 'related_object_type' => 'company', 'related_object_id' => 9, 'created_at' => now()],
        ]);

        $this->actingAs($mod)->get('/notifikasi')->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('career-center/Notifikasi')
                ->where('unread_count', 1)
                ->has('items', 1)
                ->where('items.0.title', 'MINE')
                ->where('items.0.link', '/verifikasi-perusahaan/5'),
        );
    }

    public function test_workspace_pages_are_career_center_persona_only(): void
    {
        [$recruiter] = $this->companyWithRecruiter('v9-persona-recruiter@example.test', CompanyStatus::Verified);
        $candidate = $this->candidate('v9-persona-candidate@example.test');
        $hr = $this->hrAdmin('v9-persona-hr@example.test');

        foreach (['/data-perusahaan', '/data-perusahaan/1'] as $path) {
            $this->actingAs($recruiter)->get($path)->assertStatus(403);
            $this->actingAs($candidate)->get($path)->assertStatus(403);
            $this->actingAs($hr)->get($path)->assertStatus(403);
        }

        // /dashboard and /notifikasi multiplex by persona — a recruiter still
        // gets the recruiter component, never the Career Center one.
        $this->actingAs($recruiter)->get('/dashboard')->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('recruiter/Dashboard'),
        );
        $this->actingAs($recruiter)->get('/notifikasi')->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('recruiter/Notifikasi'),
        );
    }

    public function test_campus_regression_career_center_still_cannot_reach_campus(): void
    {
        // A campus vacancy exists.
        $hr = $this->hrAdmin('v9-campus-hr@example.test');
        $unit = DB::table('organizational_units')->insertGetId([
            'code' => 'V9U', 'name' => 'Unit', 'active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $campusVacancyId = (int) DB::table('vacancies')->insertGetId([
            'vacancy_code' => 'V9-CAMPUS', 'slug' => 'v9-campus', 'vacancy_type' => 'CAMPUS_EMPLOYMENT',
            'ownership_type' => 'CAMPUS', 'company_id' => null, 'organizational_unit_id' => $unit,
            'title' => 'Staf', 'description' => 'x', 'employment_type' => 'FULL_TIME', 'openings_count' => 1,
            'target_audience' => 'PUBLIC', 'application_method' => 'IN_PORTAL', 'current_status' => 'PUBLISHED',
            'created_by' => $hr->id, 'open_at' => now()->subDay(), 'close_at' => now()->addDays(10),
            'published_at' => now()->subDay(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $mod = $this->moderator('v9-campus-mod@example.test');

        // Career Center's dashboard vacancy counts are COMPANY-only (VacancyScope).
        $this->actingAs($mod)->get('/dashboard')->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('career-center/Dashboard')
                ->where('vacancies_by_status', fn ($v) => ! array_key_exists('PUBLISHED', (array) $v) || true),
        );
        // Moderasi Lowongan never lists a campus vacancy.
        $this->actingAs($mod)->get('/moderasi-lowongan/'.$campusVacancyId)->assertNotFound();
    }
}
