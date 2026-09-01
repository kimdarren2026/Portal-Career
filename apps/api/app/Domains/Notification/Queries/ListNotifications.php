<?php

declare(strict_types=1);

namespace App\Domains\Notification\Queries;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * `GET /notifications` (API_CONTRACT.md Part IX). Takes an already
 * actor-scoped Builder (`NotificationScope::queryFor()`) — never scopes or
 * filters after fetch. Read-only: this class only ever selects.
 *
 * Frozen filters exactly: `read` (boolean), `type`, `created_from`,
 * `created_to`. Sort is fixed at `created_at` descending (the only sortable
 * field the contract lists). No other filter is accepted.
 */
final class ListNotifications
{
    /** @var list<string> */
    public const FILTERS = ['read', 'type', 'created_from', 'created_to'];

    private const PER_PAGE = 20;

    /** @param array<string, mixed> $filters */
    public function execute(Builder $scoped, array $filters = []): LengthAwarePaginator
    {
        return $scoped
            ->when(array_key_exists('read', $filters), function (Builder $q) use ($filters): void {
                filter_var($filters['read'], FILTER_VALIDATE_BOOL)
                    ? $q->whereNotNull('read_at')
                    : $q->whereNull('read_at');
            })
            ->when(isset($filters['type']), fn (Builder $q) => $q->where('type', $filters['type']))
            ->when(isset($filters['created_from']), fn (Builder $q) => $q->where('created_at', '>=', $filters['created_from']))
            ->when(isset($filters['created_to']), fn (Builder $q) => $q->where('created_at', '<=', $filters['created_to']))
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(self::PER_PAGE);
    }
}
