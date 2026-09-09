<?php

declare(strict_types=1);

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Models\Role;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Models\UserRole;
use App\Domains\Identity\Support\AuditWriter;
use App\Domains\Identity\Support\RoleChangeNotifier;
use Illuminate\Support\Facades\DB;

/**
 * `POST /admin/users/{user}/roles/{role}/revoke` (API_CONTRACT.md — paired
 * with assign). Sets `revoked_at` / `revoked_by`; **never deletes** the row,
 * so the assignment history survives (INV-025).
 *
 * Revoking a role that has no active assignment is a no-op: `revoked = false`
 * is returned, nothing is written, nothing is audited, no notification is
 * sent. The frozen contract defines no error for this case.
 */
final class RevokeUserRole
{
    public function __construct(
        private readonly AuditWriter $audit,
        private readonly RoleChangeNotifier $notifier,
    ) {}

    /** @return array{revoked: bool, user_role_id: int|null} */
    public function execute(User $actor, User $target, string $roleCode): array
    {
        return DB::transaction(function () use ($actor, $target, $roleCode): array {
            /** @var Role $role */
            $role = Role::query()->where('code', $roleCode)->firstOrFail();

            /** @var UserRole|null $active */
            $active = UserRole::query()
                ->where('user_id', $target->getKey())
                ->where('role_id', $role->getKey())
                ->whereNull('revoked_at')
                ->lockForUpdate()
                ->first();

            if ($active === null) {
                return ['revoked' => false, 'user_role_id' => null];
            }

            $active->forceFill([
                'revoked_at' => now(),
                'revoked_by' => $actor->getKey(),
            ])->save();

            $this->audit->record('role_changed', $actor, 'user', (int) $target->getKey(), [
                'role_code' => $roleCode,
                'direction' => 'REVOKED',
                'user_role_id' => (int) $active->getKey(),
            ]);

            $this->notifier->revoked($target, $roleCode);

            return ['revoked' => true, 'user_role_id' => (int) $active->getKey()];
        });
    }
}
