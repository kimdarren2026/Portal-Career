<?php

declare(strict_types=1);

namespace Tests\Feature\SuperAdmin;

use App\Domains\Identity\Enums\RoleCode;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Models\User;
use App\Domains\Notification\Models\SmtpConfiguration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Vacancy\VacancyTestCase;

/**
 * Super Admin "Konfigurasi SMTP" page (Frontend Vertical Slice v11) — the
 * Inertia delivery for the frozen SMTP configuration runtime.
 */
final class SmtpConfigurationPageTest extends VacancyTestCase
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

    private function seedConfig(): SmtpConfiguration
    {
        $admin = $this->superAdmin('smtp-seed-'.uniqid().'@example.test');
        $row = new SmtpConfiguration();
        $row->forceFill([
            'host' => 'mail.example.test', 'port' => 587, 'encryption_mode' => 'STARTTLS',
            'username' => 'mailer', 'encrypted_password' => Crypt::encryptString('page-secret'),
            'from_address' => 'noreply@example.test', 'from_name' => 'Portal', 'reply_to_address' => null,
            'timeout_seconds' => 30, 'max_attempts' => 3, 'retry_backoff_seconds' => 60, 'is_active' => true,
            'last_tested_at' => null, 'last_test_result' => 'NOT_TESTED',
            'updated_by_user_id' => $admin->id, 'created_at' => now(), 'updated_at' => now(),
        ])->save();

        return $row->refresh();
    }

    public function test_page_renders_for_super_admin_with_safe_fields_and_no_secret(): void
    {
        $this->seedConfig();
        $admin = $this->superAdmin('smtp-page-ok@example.test');

        $response = $this->actingAs($admin)->get('/konfigurasi-smtp')->assertOk();
        $response->assertInertia(
            fn (Assert $page) => $page->component('super-admin/KonfigurasiSmtp')
                ->where('configuration.host', 'mail.example.test')
                ->where('configuration.secret_configured', true)
                ->where('configuration.last_test_result', 'NOT_TESTED')
                ->missing('configuration.encrypted_password')
                ->missing('configuration.password'),
        );

        // The encrypted value and the plaintext are both absent from the payload.
        $this->assertStringNotContainsString('page-secret', $response->getContent());
        $this->assertStringNotContainsString('encrypted_password', $response->getContent());
    }

    public function test_page_renders_with_null_configuration_when_none_exists(): void
    {
        $admin = $this->superAdmin('smtp-page-empty@example.test');

        $this->actingAs($admin)->get('/konfigurasi-smtp')->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('super-admin/KonfigurasiSmtp')->where('configuration', null),
        );
    }

    public function test_page_denied_to_auditor_and_every_other_persona(): void
    {
        [$recruiter] = $this->companyWithRecruiter('smtp-page-deny-recruiter@example.test', \App\Domains\Company\Enums\CompanyStatus::Verified);

        $denied = [
            $this->withRole('smtp-page-deny-auditor@example.test', RoleCode::Auditor),
            $recruiter,
            $this->withRole('smtp-page-deny-candidate@example.test', RoleCode::CandidateAlumni),
            $this->withRole('smtp-page-deny-hr@example.test', RoleCode::HrAdmin),
            $this->moderator('smtp-page-deny-cc@example.test'),
        ];

        foreach ($denied as $user) {
            $this->actingAs($user)->get('/konfigurasi-smtp')->assertStatus(403);
        }
    }

    public function test_smtp_page_route_is_get_only(): void
    {
        $this->assertTrue(Route::has('pages.super-admin.smtp'));

        foreach (Route::getRoutes() as $route) {
            if ($route->uri() === 'konfigurasi-smtp') {
                $this->assertSame(['GET', 'HEAD'], $route->methods());
            }
        }
    }

    public function test_audit_log_still_active_and_super_admin_dashboard_redirect_unchanged(): void
    {
        $admin = $this->superAdmin('smtp-page-regression@example.test');

        $this->actingAs($admin)->get('/audit-log')->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('super-admin/AuditLog'),
        );
        $this->actingAs($admin)->get('/dashboard')->assertRedirect('/audit-log');
    }
}
