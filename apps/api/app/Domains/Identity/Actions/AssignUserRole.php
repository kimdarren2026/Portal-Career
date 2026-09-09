<?php

declare(strict_types=1);

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Exceptions\RoleAssignmentConflict;
use App\Domains\Identity\Models\Role;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Models\UserRole;
use App\Domains\Identity\Support\AuditWriter;
use App\Domains\Identity\Support\RoleChangeNotifier;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * `POST /admin/users/{user}/roles` (API_CONTRACT.md — `INERTIA_WEB`,
 * `SUPER_ADMIN` only).
 *
 * - Creates a `user_roles` row with `assigned_by` / `assigned_at`.
 * - At most one active assignment per `(user, role)` (INV-025). A second
 *   active assignment is `RoleAssignmentConflict` → `409`. The partial unique
 *   index `uq_user_roles_user_role_active` is the real race guard; the
 *   in-transaction check just turns the violation into a clean domain error.
 * - A revoked assignment may be re-issued; history is preserved (never deleted).
 * - Audit `role_changed` (FR-AUD-001). Affected user notified.
 *
 * Role assignment is authorization only — it never proves candidate
 * eligibility (INV-028) and `SELECTOR` still needs an active stage assignment
 * (INV-037). This Action encodes neither of those side rules; it only records
 * the assignment.
 */
final class AssignUserRole
{
    public function __construct(
        private readonly AuditWriter $audit,
        private readonly RoleChangeNotifier $notifier,
    ) {}

    public function execute(User $actor, User $target, string $roleCode): UserRole
    {
        return DB::transaction(function () use ($actor, $target, $roleCode): UserRole {
            /** @var Role $role */
            $role = Role::query()->where('code', $roleCode)->firstOrFail();

            $active = UserRole::query()
                ->where('user_id', $target->getKey())
                ->where('role_id', $role->getKey())
                ->whereNull('revoked_at')
                ->lockForUpdate()
                ->first();

            if ($active !== null) {
                throw new RoleAssignmentConflict('An active assignment already exists for this user and role.');
            }

            try {
                $assignment = new UserRole;
                $assignment->forceFill([
                    'user_id' => $target->getKey(),
                    'role_id' => $role->getKey(),
                    'assigned_at' => now(),
                    'assigned_by' => $actor->getKey(),
                ])->save();
            } catch (Throwable $e) {
                // Lost race against the partial unique index.
                if (str_contains($e->getMessage(), 'uq_user_roles_user_role_active')) {
                    throw new RoleAssignmentConflict('An active assignment already exists for this user and role.');
                }

                throw $e;
            }

            $this->audit->record('role_changed', $actor, 'user', (int) $target->getKey(), [
                'role_code' => $roleCode,
                'direction' => 'ASSIGNED',
                'user_role_id' => (int) $assignment->getKey(),
            ]);

            $this->notifier->assigned($target, $roleCode);

            return $assignment->refresh();
        });
    }
}
