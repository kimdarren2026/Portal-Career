<?php

declare(strict_types=1);

namespace App\Domains\Recruitment\SelectionSchedule\Queries;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * `GET /schedules` — filter/sort vocabulary frozen by `API_CONTRACT.md`.
 * Takes an already actor-scoped Builder (`SelectionScheduleScope::operationalQueryFor()`
 * or `::candidateQueryFor()`) — never scopes or filters after fetch.
 */
final class ListSelectionSchedules
{
    public const FILTERS = ['application_id', 'recruitment_stage_id', 'status', 'starts_from', 'starts_to'];

    private const SORTABLE = ['starts_at'];

    private const PER_PAGE = 20;

    /** @param array<string, mixed> $filters */
    public function execute(Builder $scoped, array $filters, string $sort = 'starts_at', string $direction = 'asc'): LengthAwarePaginator
    {
        $sort = in_array($sort, self::SORTABLE, true) ? $sort : 'starts_at';
        $direction = $direction === 'desc' ? 'desc' : 'asc';

        return $scoped
            ->when(isset($filters['application_id']), fn (Builder $q) => $q->where('application_id', (int) $filters['application_id']))
            ->when(isset($filters['recruitment_stage_id']), fn (Builder $q) => $q->where('recruitment_stage_id', (int) $filters['recruitment_stage_id']))
            ->when(isset($filters['status']), fn (Builder $q) => $q->where('status', $filters['status']))
            ->when(isset($filters['starts_from']), fn (Builder $q) => $q->where('starts_at', '>=', $filters['starts_from']))
            ->when(isset($filters['starts_to']), fn (Builder $q) => $q->where('starts_at', '<=', $filters['starts_to']))
            ->orderBy($sort, $direction)
            ->orderBy('id')
            ->paginate(self::PER_PAGE);
    }
}
