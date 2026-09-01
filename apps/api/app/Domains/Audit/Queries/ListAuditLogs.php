<?php

declare(strict_types=1);

namespace App\Domains\Audit\Queries;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * `GET /audit-logs` (API_CONTRACT.md Part XI). Takes an already actor-scoped
 * Builder (`AuditLogScope::queryFor()`) and only ever selects — never scopes
 * or filters after fetch.
 *
 * Frozen, allow-listed filters exactly: `actor_user_id`, `action`,
 * `object_type`, `object_id`, `correlation_id`, `created_from`, `created_to`.
 * Sort is fixed at `created_at` descending — the only sortable field the
 * contract lists, and its stated default. No other filter or sort is honoured.
 * Page-based pagination (contract §6 baseline, default 25, max 100).
 */
final class ListAuditLogs
{
    /** @var list<string> */
    public const FILTERS = [
        'actor_user_id', 'action', 'object_type', 'object_id', 'correlation_id',
        'created_from', 'created_to',
    ];

    private const DEFAULT_PER_PAGE = 25;

    private const MAX_PER_PAGE = 100;

    /** @param array<string, mixed> $filters */
    public function execute(Builder $scoped, array $filters = [], ?int $perPage = null): LengthAwarePaginator
    {
        $perPage = max(1, min($perPage ?? self::DEFAULT_PER_PAGE, self::MAX_PER_PAGE));

        return $scoped
            ->when(isset($filters['actor_user_id']), fn (Builder $q) => $q->where('actor_user_id', (int) $filters['actor_user_id']))
            ->when(isset($filters['action']), fn (Builder $q) => $q->where('action', $filters['action']))
            ->when(isset($filters['object_type']), fn (Builder $q) => $q->where('object_type', $filters['object_type']))
            ->when(isset($filters['object_id']), fn (Builder $q) => $q->where('object_id', (int) $filters['object_id']))
            ->when(isset($filters['correlation_id']), fn (Builder $q) => $q->where('correlation_id', $filters['correlation_id']))
            ->when(isset($filters['created_from']), fn (Builder $q) => $q->where('created_at', '>=', $filters['created_from']))
            ->when(isset($filters['created_to']), fn (Builder $q) => $q->where('created_at', '<=', $filters['created_to']))
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->paginate($perPage);
    }
}
