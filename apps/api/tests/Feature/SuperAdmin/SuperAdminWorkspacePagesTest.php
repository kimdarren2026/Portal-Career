<?php

declare(strict_types=1);

namespace Tests\Feature\SuperAdmin;

use App\Domains\Identity\Enums\RoleCode;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Vacancy\VacancyTestCase;

/**
 * Super Admin Control Plane Frontend Slice v10 — the one canonical Super Admin
 * module with a frozen runtime, "Audit Log" (read-only, append-only), plus
 * the Phase 0 persona-boundary hardening for the shared `/dashboard` and
 * `/notifikasi` routes.
 */
final class SuperAdminWorkspacePagesTest extends VacancyTestCase
{
    private function superAdmin(string $email): User
    {
        $u = $this->makeUser($email, UserStatus::Active);
        $this->assignRole($u, RoleCode::SuperAdmin);

        return $u;
    }

    private function auditor(string $email): User
    {
        $u = $this->makeUser($email, UserStatus::Active);
        $this->assignRole($u, RoleCode::Auditor);

        return $u;
    }

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

    /** @param array<string, scalar|array|null> $overrides */
    private function auditRow(array $overrides = []): void
    {
        DB::table('audit_logs')->insert(array_merge([
            'actor_user_id' => null,
            'action' => 'login_succeeded',
            'object_type' => 'user',
            'object_id' => 1,
            'change_summary' => json_encode(['field' => 'value']),
            'correlation_id' => 'corr-'.bin2hex(random_bytes(4)),
            'ip_address' => null,
            'user_agent_device_metadata' => null,
            'created_at' => now(),
        ], $overrides));
    }

    public function test_audit_log_renders_for_super_admin_newest_first_and_paginated(): void
    {
        $admin = $this->superAdmin('v10-audit-admin@example.test');

        $this->auditRow(['action' => 'role_changed', 'created_at' => now()->subDays(2)]);
        $this->auditRow(['action' => 'company_verified', 'created_at' => now()->subDay()]);
        $this->auditRow(['action' => 'vacancy_approved', 'created_at' => now()]);

        $this->actingAs($admin)->get('/audit-log')->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('super-admin/AuditLog')
                ->has('items', 3)
                ->where('items.0.action', 'vacancy_approved') // newest first
                ->where('items.2.action', 'role_changed')
                ->has('items.0.change_summary')
                ->has('pagination')
                ->has('action_options')
                ->has('object_type_options'),
        );
    }

    public function test_audit_log_filters_are_allow_listed_and_scoped_in_query(): void
    {
        $admin = $this->superAdmin('v10-audit-filter@example.test');

        $this->auditRow(['action' => 'role_changed', 'object_type' => 'user', 'object_id' => 7]);
        $this->auditRow(['action' => 'company_verified', 'object_type' => 'company', 'object_id' => 9]);

        $this->actingAs($admin)->get('/audit-log?action=role_changed')->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('super-admin/AuditLog')
                ->has('items', 1)
                ->where('items.0.action', 'role_changed'),
        );

        $this->actingAs($admin)->get('/audit-log?object_type=company&object_id=9')->assertOk()->assertInertia(
            fn (Assert $page) => $page->has('items', 1)->where('items.0.object_type', 'company'),
        );

        // An unlisted query parameter is rejected, matching GET /notifications.
        $this->actingAs($admin)->get('/audit-log?sort=action')->assertStatus(422);
    }

    public function test_audit_log_never_exposes_ip_or_device_metadata(): void
    {
        $admin = $this->superAdmin('v10-audit-privacy@example.test');
        $this->auditRow([
            'ip_address' => '203.0.113.7',
            'user_agent_device_metadata' => json_encode(['ua' => 'secret-agent']),
        ]);

        $this->actingAs($admin)->get('/audit-log')->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('super-admin/AuditLog')
                ->has('items', 1)
                ->missing('items.0.ip_address')
                ->missing('items.0.user_agent_device_metadata'),
        );
    }

    public function test_audit_log_is_visible_to_auditor(): void
    {
        $this->auditRow();
        $auditor = $this->auditor('v10-audit-auditor@example.test');

        $this->actingAs($auditor)->get('/audit-log')->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('super-admin/AuditLog'),
        );
    }

    public function test_audit_log_denied_to_every_other_persona(): void
    {
        [$recruiter] = $this->companyWithRecruiter('v10-audit-recruiter@example.test', \App\Domains\Company\Enums\CompanyStatus::Verified);
        $candidate = $this->candidate('v10-audit-candidate@example.test');
        $hr = $this->hrAdmin('v10-audit-hr@example.test');
        $cc = $this->moderator('v10-audit-cc@example.test');

        foreach ([$recruiter, $candidate, $hr, $cc] as $user) {
            $this->actingAs($user)->get('/audit-log')->assertStatus(403);
        }
    }

    public function test_audit_log_has_no_mutation_route(): void
    {
        $this->assertTrue(Route::has('pages.super-admin.audit-log'));

        foreach (Route::getRoutes() as $route) {
            if (str_starts_with((string) $route->uri(), 'audit-log')) {
                $this->assertSame(['GET', 'HEAD'], $route->methods());
            }
        }
    }

    public function test_super_admin_dashboard_redirects_to_first_active_module(): void
    {
        $admin = $this->superAdmin('v10-dash-admin@example.test');

        $this->actingAs($admin)->get('/dashboard')->assertRedirect('/audit-log');
    }

    public function test_super_admin_notifikasi_renders_super_admin_surface_own_scoped(): void
    {
        $admin = $this->superAdmin('v10-notif-admin@example.test');
        $other = $this->superAdmin('v10-notif-other@example.test');

        DB::table('notifications')->insert([
            ['user_id' => $admin->id, 'type' => 'ROLE_CHANGED', 'title' => 'MINE', 'body_reference' => 'x', 'related_object_type' => 'user', 'related_object_id' => 1, 'created_at' => now()],
            ['user_id' => $other->id, 'type' => 'ROLE_CHANGED', 'title' => 'THEIRS', 'body_reference' => 'x', 'related_object_type' => 'user', 'related_object_id' => 2, 'created_at' => now()],
        ]);

        $this->actingAs($admin)->get('/notifikasi')->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('super-admin/Notifikasi')
                ->where('unread_count', 1)
                ->has('items', 1)
                ->where('items.0.title', 'MINE')
                ->where('items.0.link', null),
        );
    }

    public function test_recruiter_and_career_center_dashboard_notifikasi_unchanged(): void
    {
        [$recruiter] = $this->companyWithRecruiter('v10-reg-recruiter@example.test', \App\Domains\Company\Enums\CompanyStatus::Verified);
        $cc = $this->moderator('v10-reg-cc@example.test');

        $this->actingAs($recruiter)->get('/dashboard')->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('recruiter/Dashboard'),
        );
        $this->actingAs($recruiter)->get('/notifikasi')->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('recruiter/Notifikasi'),
        );
        $this->actingAs($cc)->get('/dashboard')->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('career-center/Dashboard'),
        );
        $this->actingAs($cc)->get('/notifikasi')->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('career-center/Notifikasi'),
        );
    }
}
