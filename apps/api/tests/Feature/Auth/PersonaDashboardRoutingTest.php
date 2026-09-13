<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Domains\Identity\Enums\RoleCode;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Vacancy\VacancyTestCase;

/**
 * GAP-011 — `GET /dashboard` resolves every persona explicitly. No
 * unsupported or role-less actor may silently inherit the Candidate UI:
 * the previous `candidate/Dashboard` fallback is gone.
 */
final class PersonaDashboardRoutingTest extends VacancyTestCase
{
    private function userWith(string $email, ?RoleCode $role): User
    {
        $user = $this->makeUser($email, UserStatus::Active);
        if ($role !== null) {
            $this->assignRole($user, $role);
        }

        return $user;
    }

    public function test_candidate_gets_the_candidate_dashboard(): void
    {
        $user = $this->userWith('persona-candidate@example.test', RoleCode::CandidateExternal);
        DB::table('candidate_profiles')->insert([
            'user_id' => $user->id, 'current_candidate_type' => 'EXTERNAL',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($user)->get('/dashboard')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('candidate/Dashboard'));
    }

    public function test_career_center_gets_the_career_center_dashboard(): void
    {
        $user = $this->userWith('persona-cc@example.test', RoleCode::CareerCenterStaff);

        $this->actingAs($user)->get('/dashboard')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('career-center/Dashboard'));
    }

    public function test_recruiter_gets_the_recruiter_dashboard(): void
    {
        [$recruiter] = $this->verifiedCompanyWithRecruiter('persona-recruiter@example.test');

        $this->actingAs($recruiter)->get('/dashboard')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('recruiter/Dashboard'));
    }

    public function test_hr_admin_is_sent_to_its_own_workspace(): void
    {
        $user = $this->userWith('persona-hr@example.test', RoleCode::HrAdmin);

        $this->actingAs($user)->get('/dashboard')->assertRedirect('/kepegawaian/dashboard');
    }

    public function test_pure_super_admin_is_sent_to_the_audit_log(): void
    {
        $user = $this->userWith('persona-sa@example.test', RoleCode::SuperAdmin);

        $this->actingAs($user)->get('/dashboard')->assertRedirect('/audit-log');
    }

    public function test_auditor_is_sent_to_the_audit_log(): void
    {
        $user = $this->userWith('persona-auditor@example.test', RoleCode::Auditor);

        $this->actingAs($user)->get('/dashboard')->assertRedirect('/audit-log');
    }

    public function test_bare_selector_gets_a_truthful_403_never_a_candidate_page(): void
    {
        $user = $this->userWith('persona-selector@example.test', RoleCode::Selector);

        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertStatus(403);
        $response->assertInertia(fn (Assert $page) => $page->component('Error')->where('status', 403));
    }

    public function test_actor_with_no_supported_role_gets_a_truthful_403(): void
    {
        $user = $this->userWith('persona-none@example.test', null);

        $this->actingAs($user)->get('/dashboard')->assertStatus(403)
            ->assertInertia(fn (Assert $page) => $page->component('Error'));
    }
}
