<?php

declare(strict_types=1);

namespace Tests\Feature\SuperAdmin;

use App\Domains\Identity\Enums\RoleCode;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Identity\IdentityTestCase;

/**
 * PGC-V1 / PD-F — Super Admin user directory + ACTIVE <-> SUSPENDED lifecycle.
 */
final class UserDirectoryAndSuspensionTest extends IdentityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function superAdmin(): User
    {
        $u = $this->makeUser('pf-sa@example.test', UserStatus::Active);
        $this->assignRole($u, RoleCode::SuperAdmin);

        return $u;
    }

    public function test_directory_returns_only_the_approved_fields_and_no_secret(): void
    {
        $sa = $this->superAdmin();
        $target = $this->makeUser('pf-target@example.test', UserStatus::Active);
        $this->assignRole($target, RoleCode::CompanyRecruiter);

        $response = $this->actingAs($sa)->getJson('/admin/users')->assertOk();
        $item = collect($response->json('data.items'))->firstWhere('email', 'pf-target@example.test');

        self::assertNotNull($item);
        self::assertSame(['id', 'name', 'email', 'status', 'active_roles', 'created_at'], array_keys($item));
        self::assertContains('COMPANY_RECRUITER', $item['active_roles']);
        $response->assertJsonMissing(['password' => true]);
        $raw = $response->getContent();
        self::assertStringNotContainsString('password', $raw);
        self::assertStringNotContainsString('remember_token', $raw);
    }

    public function test_directory_supports_text_status_and_role_filters_and_caps_pagination(): void
    {
        $sa = $this->superAdmin();
        $a = $this->makeUser('alice.match@example.test', UserStatus::Active);
        $this->assignRole($a, RoleCode::Auditor);
        $this->makeUser('bob.other@example.test', UserStatus::Suspended);

        $this->actingAs($sa)->getJson('/admin/users?q=alice')->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.items.0.email', 'alice.match@example.test');
        $this->actingAs($sa)->getJson('/admin/users?status=SUSPENDED')->assertOk()
            ->assertJsonPath('data.items.0.email', 'bob.other@example.test');
        $this->actingAs($sa)->getJson('/admin/users?role=AUDITOR')->assertOk()
            ->assertJsonPath('data.items.0.email', 'alice.match@example.test');
        $this->actingAs($sa)->getJson('/admin/users?per_page=500')
            ->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    public function test_directory_and_lifecycle_are_super_admin_only(): void
    {
        $recruiter = $this->makeUser('pf-rec@example.test', UserStatus::Active);
        $this->assignRole($recruiter, RoleCode::CompanyRecruiter);
        $victim = $this->makeUser('pf-victim@example.test', UserStatus::Active);

        $this->actingAs($recruiter)->getJson('/admin/users')->assertStatus(403);
        $this->actingAs($recruiter)->postJson("/admin/users/{$victim->id}/suspend", ['reason' => 'x'])->assertStatus(403);
    }

    public function test_directory_requires_authentication(): void
    {
        $this->getJson('/admin/users')->assertStatus(401)->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }

    public function test_suspend_requires_a_reason_and_immediately_invalidates_sessions(): void
    {
        $sa = $this->superAdmin();
        $target = $this->makeUser('pf-suspendme@example.test', UserStatus::Active);
        // A stand-in server-side session row for the target.
        DB::table('sessions')->insert([
            'id' => 'sess-'.$target->id, 'user_id' => $target->id, 'ip_address' => null,
            'user_agent' => null, 'payload' => 'x', 'last_activity' => time(),
        ]);

        $this->actingAs($sa)->postJson("/admin/users/{$target->id}/suspend", [])
            ->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_FAILED');

        $this->actingAs($sa)->postJson("/admin/users/{$target->id}/suspend", ['reason' => 'Dugaan penyalahgunaan akun.'])
            ->assertOk()->assertJsonPath('data.status', 'SUSPENDED');

        self::assertSame(UserStatus::Suspended, $target->fresh()->status);
        self::assertSame(0, DB::table('sessions')->where('user_id', $target->id)->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'user_suspended', 'object_id' => $target->id]);
        $audit = DB::table('audit_logs')->where('action', 'user_suspended')->where('object_id', $target->id)->value('change_summary');
        self::assertStringContainsString('Dugaan penyalahgunaan', (string) $audit);

        // No notification is sent (silent containment).
        self::assertSame(0, DB::table('notifications')->where('user_id', $target->id)->count());
        self::assertSame(0, DB::table('email_outbox')->where('recipient', $target->email)->count());
    }

    public function test_a_suspended_user_cannot_continue_authenticated_operations(): void
    {
        $sa = $this->superAdmin();
        $target = $this->makeUser('pf-blocked@example.test', UserStatus::Active);
        $this->assignRole($target, RoleCode::CompanyRecruiter);

        $this->actingAs($sa)->postJson("/admin/users/{$target->id}/suspend", ['reason' => 'test'])->assertOk();

        $this->actingAs($target->fresh())->getJson('/me')
            ->assertStatus(403)->assertJsonPath('error.code', 'AUTH_ACCOUNT_SUSPENDED');
    }

    public function test_restore_returns_a_suspended_account_to_active_and_rejects_a_bad_transition(): void
    {
        $sa = $this->superAdmin();
        $target = $this->makeUser('pf-restoreme@example.test', UserStatus::Suspended);

        $this->actingAs($sa)->postJson("/admin/users/{$target->id}/restore")
            ->assertOk()->assertJsonPath('data.status', 'ACTIVE');
        self::assertSame(UserStatus::Active, $target->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user_restored', 'object_id' => $target->id]);

        // Restoring an already-active account is a 409.
        $this->actingAs($sa)->postJson("/admin/users/{$target->id}/restore")
            ->assertStatus(409)->assertJsonPath('error.code', 'CONFLICT');
    }
}
