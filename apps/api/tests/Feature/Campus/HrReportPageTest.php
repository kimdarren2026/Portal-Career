<?php

declare(strict_types=1);

namespace Tests\Feature\Campus;

use App\Domains\Identity\Enums\RoleCode;
use App\Domains\Identity\Enums\UserStatus;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Identity\IdentityTestCase;

/**
 * `GET /kepegawaian/laporan` — the Admin Kepegawaian operational report page.
 *
 * Regression guard for the blank-page defect: with no campus data the page
 * must still render (HTTP 200, `admin-kepegawaian/Laporan`) and every grouped-
 * count prop the Vue page reads must be present, so the empty state renders
 * instead of a client-side render crash on an undefined prop.
 */
final class HrReportPageTest extends IdentityTestCase
{
    private const PROPS = [
        'vacancies_by_status',
        'applications_by_status',
        'schedules_by_status',
        'offers_by_status',
        'outcomes_by_type',
    ];

    public function test_report_page_renders_with_empty_state_for_hr_admin_without_data(): void
    {
        $hr = $this->makeUser('hr-report-empty@example.test', UserStatus::Active);
        $this->assignRole($hr, RoleCode::HrAdmin);

        $this->actingAs($hr)->get('/kepegawaian/laporan')->assertOk()->assertInertia(
            fn (Assert $page) => $page
                ->component('admin-kepegawaian/Laporan')
                ->where('vacancies_by_status', [])
                ->where('applications_by_status', [])
                ->where('schedules_by_status', [])
                ->where('offers_by_status', [])
                ->where('outcomes_by_type', []),
        );
    }

    public function test_report_page_is_reachable_by_super_admin(): void
    {
        $admin = $this->makeUser('hr-report-superadmin@example.test', UserStatus::Active);
        $this->assignRole($admin, RoleCode::SuperAdmin);

        $this->actingAs($admin)->get('/kepegawaian/laporan')->assertOk()->assertInertia(
            fn (Assert $page) => collect(self::PROPS)->reduce(
                fn (Assert $p, string $prop) => $p->has($prop),
                $page->component('admin-kepegawaian/Laporan'),
            ),
        );
    }

    public function test_report_page_is_denied_to_a_candidate(): void
    {
        $candidate = $this->makeUser('hr-report-candidate@example.test', UserStatus::Active);
        $this->assignRole($candidate, RoleCode::CandidateAlumni);

        $this->actingAs($candidate)->get('/kepegawaian/laporan')->assertStatus(403);
    }
}
