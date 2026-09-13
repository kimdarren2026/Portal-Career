<?php

declare(strict_types=1);

namespace App\Domains\Identity\Support;

use App\Domains\Identity\Models\Role;

/**
 * Read-only projection of the frozen role catalogue (FSD §3.1 — eleven
 * persisted codes, seeded at deployment, `RoleCode`). The Super Admin
 * "Pengguna dan Role" page renders this list; nothing here creates, renames or
 * deletes a role.
 */
final class RoleCatalogue
{
    /** @return list<array{id: int, code: string, name: string, description: string|null}> */
    public static function all(): array
    {
        return Role::query()
            ->orderBy('id')
            ->get(['id', 'code', 'name', 'description'])
            ->map(static fn (Role $role): array => [
                'id' => (int) $role->getKey(),
                'code' => $role->getRawOriginal('code'),
                'name' => (string) $role->name,
                'description' => $role->description,
            ])
            ->values()
            ->all();
    }
}
