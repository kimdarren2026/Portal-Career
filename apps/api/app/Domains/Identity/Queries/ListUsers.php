<?php

declare(strict_types=1);

namespace App\Domains\Identity\Queries;

use App\Domains\Identity\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * `GET /admin/users` — Super Admin user directory (PGC-V1 / PD-F).
 *
 * Returns exactly `id`, `name`, `email`, `status`, `active_roles`,
 * `created_at`. Never a credential/hash, token, secret, or profile detail.
 * Filters: free-text over name OR normalized email; `status`; `role`.
 * Pagination default 25, max 100. Sort `created_at DESC` with an id
 * tie-breaker.
 */
final class ListUsers
{
    public const FILTERS = ['q', 'status', 'role', 'page', 'per_page'];

    private const DEFAULT_PER_PAGE = 25;
    private const MAX_PER_PAGE = 100;

    /** @param array<string, mixed> $filters */
    public function execute(array $filters = []): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? self::DEFAULT_PER_PAGE);
        $perPage = max(1, min($perPage, self::MAX_PER_PAGE));

        $query = User::query()
            ->when(isset($filters['q']) && trim((string) $filters['q']) !== '', function (Builder $q) use ($filters): void {
                $term = mb_strtolower(trim((string) $filters['q']));
                $q->where(function (Builder $inner) use ($term): void {
                    $inner->whereRaw('lower(name) like ?', ['%'.$term.'%'])
                        ->orWhereRaw('lower(email_normalized) like ?', ['%'.$term.'%']);
                });
            })
            ->when(isset($filters['status']) && $filters['status'] !== '', fn (Builder $q) => $q->where('status', $filters['status']))
            ->when(isset($filters['role']) && $filters['role'] !== '', fn (Builder $q) => $q->whereHas(
                'userRoles',
                fn (Builder $r) => $r->whereNull('revoked_at')->whereHas('role', fn (Builder $rr) => $rr->where('code', $filters['role'])),
            ))
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $page = $query->paginate($perPage);

        $roleMap = $this->activeRolesByUser($page->getCollection()->map(fn (User $u): int => (int) $u->getKey())->all());

        return $page->through(fn (User $user): array => [
            'id' => (int) $user->getKey(),
            'name' => $user->name,
            'email' => $user->email,
            'status' => $user->status->value,
            'active_roles' => $roleMap[(int) $user->getKey()] ?? [],
            'created_at' => $user->created_at?->toIso8601String(),
        ]);
    }

    /**
     * @param list<int> $userIds
     * @return array<int, list<string>>
     */
    private function activeRolesByUser(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        $rows = \Illuminate\Support\Facades\DB::table('user_roles')
            ->join('roles', 'roles.id', '=', 'user_roles.role_id')
            ->whereIn('user_roles.user_id', $userIds)
            ->whereNull('user_roles.revoked_at')
            ->orderBy('roles.code')
            ->get(['user_roles.user_id', 'roles.code']);

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row->user_id][] = (string) $row->code;
        }

        return $map;
    }
}
