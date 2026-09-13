<?php

declare(strict_types=1);

namespace Tests\Feature\SuperAdmin;

use App\Domains\Identity\Enums\RoleCode;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Vacancy\VacancyTestCase;

/**
 * Super Admin role management — the deterministic, frozen part of "Pengguna dan
 * Role" (`POST /admin/users/{user}/roles` and its revoke pair).
 */
final class AdminUserRoleManagementTest extends VacancyTestCase
{
    private function superAdmin(string $email): User
    {
        $u = $this->makeUser($email, UserStatus::Active);
        $this->assignRole($u, RoleCode::SuperAdmin);

        return $u;
    }

    private function plainUser(string $email): User
    {
        return $this->makeUser($email, UserStatus::Active);
    }

    private function roleId(RoleCode $code): int
    {
        return (int) DB::table('roles')->where('code', $code->value)->value('id');
    }

    public function test_assign_creates_active_assignment_with_audit_and_notification(): void
    {
        $admin = $this->superAdmin('role-assign-admin@example.test');
        $target = $this->plainUser('role-assign-target@example.test');

        $this->actingAs($admin)
            ->withHeader('Idempotency-Key', 'assign-key-1')
            ->postJson("/admin/users/{$target->id}/roles", ['role_code' => RoleCode::Auditor->value])
            ->assertStatus(201)
            ->assertJsonPath('data.assignment.role_code', RoleCode::Auditor->value);

        $this->assertDatabaseHas('user_roles', [
            'user_id' => $target->id,
            'role_id' => $this->roleId(RoleCode::Auditor),
            'assigned_by' => $admin->id,
            'revoked_at' => null,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $admin->id,
            'action' => 'role_changed',
            'object_type' => 'user',
            'object_id' => $target->id,
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $target->id,
            'type' => 'ROLE_CHANGED',
        ]);
        $this->assertDatabaseHas('email_outbox', [
            'recipient' => $target->email,
            'template_reference' => 'identity.role.assigned',
        ]);
    }

    public function test_duplicate_active_assignment_is_conflict(): void
    {
        $admin = $this->superAdmin('role-dup-admin@example.test');
        $target = $this->plainUser('role-dup-target@example.test');

        $this->actingAs($admin)
            ->withHeader('Idempotency-Key', 'dup-key-1')
            ->postJson("/admin/users/{$target->id}/roles", ['role_code' => RoleCode::HrAdmin->value])
            ->assertStatus(201);

        $this->actingAs($admin)
            ->withHeader('Idempotency-Key', 'dup-key-2')
            ->postJson("/admin/users/{$target->id}/roles", ['role_code' => RoleCode::HrAdmin->value])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'CONFLICT');

        $this->assertSame(1, DB::table('user_roles')
            ->where('user_id', $target->id)
            ->where('role_id', $this->roleId(RoleCode::HrAdmin))
            ->whereNull('revoked_at')
            ->count());
    }

    public function test_same_idempotency_key_replays_the_first_response(): void
    {
        $admin = $this->superAdmin('role-idem-admin@example.test');
        $target = $this->plainUser('role-idem-target@example.test');

        $first = $this->actingAs($admin)
            ->withHeader('Idempotency-Key', 'idem-key-1')
            ->postJson("/admin/users/{$target->id}/roles", ['role_code' => RoleCode::Selector->value])
            ->assertStatus(201);

        $this->actingAs($admin)
            ->withHeader('Idempotency-Key', 'idem-key-1')
            ->postJson("/admin/users/{$target->id}/roles", ['role_code' => RoleCode::Selector->value])
            ->assertStatus(201)
            ->assertHeader('Idempotency-Replayed', 'true')
            ->assertJsonPath('data.assignment.id', $first->json('data.assignment.id'));

        $this->assertSame(1, DB::table('user_roles')
            ->where('user_id', $target->id)
            ->where('role_id', $this->roleId(RoleCode::Selector))
            ->count());
    }

    public function test_revoke_sets_revoked_columns_and_never_deletes(): void
    {
        $admin = $this->superAdmin('role-revoke-admin@example.test');
        $target = $this->plainUser('role-revoke-target@example.test');

        $this->actingAs($admin)
            ->withHeader('Idempotency-Key', 'rev-assign-1')
            ->postJson("/admin/users/{$target->id}/roles", ['role_code' => RoleCode::Auditor->value])
            ->assertStatus(201);

        $this->actingAs($admin)
            ->postJson("/admin/users/{$target->id}/roles/".RoleCode::Auditor->value.'/revoke')
            ->assertOk()
            ->assertJsonPath('data.revoked', true);

        $row = DB::table('user_roles')
            ->where('user_id', $target->id)
            ->where('role_id', $this->roleId(RoleCode::Auditor))
            ->first();
        $this->assertNotNull($row);
        $this->assertNotNull($row->revoked_at);
        $this->assertSame($admin->id, (int) $row->revoked_by);
    }

    public function test_revoke_when_no_active_assignment_is_a_noop(): void
    {
        $admin = $this->superAdmin('role-noop-admin@example.test');
        $target = $this->plainUser('role-noop-target@example.test');

        $this->actingAs($admin)
            ->postJson("/admin/users/{$target->id}/roles/".RoleCode::Auditor->value.'/revoke')
            ->assertOk()
            ->assertJsonPath('data.revoked', false);

        $this->assertDatabaseMissing('user_roles', [
            'user_id' => $target->id,
            'role_id' => $this->roleId(RoleCode::Auditor),
        ]);
    }

    public function test_unknown_role_code_is_rejected(): void
    {
        $admin = $this->superAdmin('role-badcode-admin@example.test');
        $target = $this->plainUser('role-badcode-target@example.test');

        $this->actingAs($admin)
            ->withHeader('Idempotency-Key', 'bad-key-1')
            ->postJson("/admin/users/{$target->id}/roles", ['role_code' => 'NOT_A_ROLE'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    public function test_non_super_admin_cannot_assign_or_revoke(): void
    {
        $auditor = $this->makeUser('role-auditor@example.test', UserStatus::Active);
        $this->assignRole($auditor, RoleCode::Auditor);
        $target = $this->plainUser('role-forbidden-target@example.test');

        $this->actingAs($auditor)
            ->withHeader('Idempotency-Key', 'forbidden-key-1')
            ->postJson("/admin/users/{$target->id}/roles", ['role_code' => RoleCode::HrAdmin->value])
            ->assertStatus(403);

        $this->actingAs($auditor)
            ->postJson("/admin/users/{$target->id}/roles/".RoleCode::HrAdmin->value.'/revoke')
            ->assertStatus(403);

        $this->assertDatabaseMissing('user_roles', [
            'user_id' => $target->id,
            'role_id' => $this->roleId(RoleCode::HrAdmin),
        ]);
    }

    public function test_revoked_role_can_be_reissued_and_history_is_preserved(): void
    {
        $admin = $this->superAdmin('role-reissue-admin@example.test');
        $target = $this->plainUser('role-reissue-target@example.test');
        $path = "/admin/users/{$target->id}/roles";

        $this->actingAs($admin)->withHeader('Idempotency-Key', 're-1')->postJson($path, ['role_code' => RoleCode::Selector->value])->assertStatus(201);
        $this->actingAs($admin)->postJson("{$path}/".RoleCode::Selector->value.'/revoke')->assertOk();
        $this->actingAs($admin)->withHeader('Idempotency-Key', 're-2')->postJson($path, ['role_code' => RoleCode::Selector->value])->assertStatus(201);

        $this->assertSame(2, DB::table('user_roles')
            ->where('user_id', $target->id)
            ->where('role_id', $this->roleId(RoleCode::Selector))
            ->count());
        $this->assertSame(1, DB::table('user_roles')
            ->where('user_id', $target->id)
            ->where('role_id', $this->roleId(RoleCode::Selector))
            ->whereNull('revoked_at')
            ->count());
    }
}
