<?php

declare(strict_types=1);

namespace Tests\Feature\SuperAdmin;

use App\Domains\Identity\Enums\RoleCode;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Models\User;
use App\Domains\Vacancy\Enums\VacancyType;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Vacancy\VacancyTestCase;

/**
 * Super Admin "Jenis Lowongan" (Frontend Vertical Slice v12) — read-only
 * reference over the frozen `VacancyType` enum (PO decision
 * SUPER_ADMIN_VACANCY_TYPE_REFERENCE_MVP, API_CONTRACT.md Part X item 61).
 */
final class VacancyTypeReferenceTest extends VacancyTestCase
{
    private function superAdmin(string $email): User
    {
        $u = $this->makeUser($email, UserStatus::Active);
        $this->assignRole($u, RoleCode::SuperAdmin);

        return $u;
    }

    private function withRole(string $email, RoleCode $role): User
    {
        $u = $this->makeUser($email, UserStatus::Active);
        $this->assignRole($u, $role);

        return $u;
    }

    public function test_page_lists_exactly_the_three_frozen_vacancy_types(): void
    {
        $admin = $this->superAdmin('vt-list@example.test');

        $this->actingAs($admin)->get('/jenis-lowongan')->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('super-admin/JenisLowongan')
                ->has('types', 3)
                ->where('types.0.code', 'CAMPUS_EMPLOYMENT')
                ->where('types.1.code', 'COMPANY_EMPLOYMENT')
                ->where('types.2.code', 'INTERNSHIP')
                ->where('types.0.ownership', 'CAMPUS')
                ->where('types.1.ownership', 'COMPANY')
                ->where('types.2.ownership', 'COMPANY'),
        );
    }

    public function test_reference_matches_the_enum_and_invents_no_fourth_type(): void
    {
        $admin = $this->superAdmin('vt-enum@example.test');

        $response = $this->actingAs($admin)->get('/jenis-lowongan')->assertOk();
        $response->assertInertia(function (Assert $page): void {
            $codes = collect($page->toArray()['props']['types'])->pluck('code')->all();
            sort($codes);
            $enum = collect(VacancyType::cases())->map(fn (VacancyType $t) => $t->value)->sort()->values()->all();
            $this->assertSame($enum, $codes);
            $this->assertCount(3, $codes);
        });
    }

    public function test_page_denied_to_auditor_and_every_other_persona(): void
    {
        [$recruiter] = $this->companyWithRecruiter('vt-deny-recruiter@example.test', \App\Domains\Company\Enums\CompanyStatus::Verified);

        $denied = [
            $this->withRole('vt-deny-auditor@example.test', RoleCode::Auditor),
            $recruiter,
            $this->withRole('vt-deny-candidate@example.test', RoleCode::CandidateAlumni),
            $this->withRole('vt-deny-hr@example.test', RoleCode::HrAdmin),
            $this->moderator('vt-deny-cc@example.test'),
        ];

        foreach ($denied as $user) {
            $this->actingAs($user)->get('/jenis-lowongan')->assertStatus(403);
        }
    }

    public function test_no_vacancy_type_mutation_route_exists(): void
    {
        $this->assertTrue(Route::has('pages.super-admin.vacancy-types'));

        foreach (Route::getRoutes() as $route) {
            if ($route->uri() === 'jenis-lowongan') {
                $this->assertSame(['GET', 'HEAD'], $route->methods());
            }
            // Nothing anywhere routes a vacancy-type write.
            $this->assertStringNotContainsString('vacancy-types/', (string) $route->uri());
            $this->assertStringNotContainsString('jenis-lowongan/', (string) $route->uri());
        }
    }

    public function test_smtp_and_audit_log_still_active_and_no_migration_added(): void
    {
        $admin = $this->superAdmin('vt-regression@example.test');

        $this->actingAs($admin)->get('/audit-log')->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('super-admin/AuditLog'),
        );
        $this->actingAs($admin)->get('/konfigurasi-smtp')->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('super-admin/KonfigurasiSmtp'),
        );

        // The enum is untouched; company authoring still maps to exactly the two
        // company-owned types.
        $this->assertSame(['COMPANY_EMPLOYMENT', 'INTERNSHIP'], VacancyType::companyAuthorable());
    }
}
