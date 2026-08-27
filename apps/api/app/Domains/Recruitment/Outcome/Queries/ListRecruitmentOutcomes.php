<?php

declare(strict_types=1);

namespace App\Domains\Recruitment\Outcome\Queries;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * `GET /recruitment-outcomes` — takes an already actor-scoped Builder
 * (`RecruitmentOutcomeScope::operationalQueryFor()`) — never scopes or
 * filters after fetch, the same precedent as `ListSelectionSchedules`.
 */
final class ListRecruitmentOutcomes
{
    public const FILTERS = ['application_id', 'outcome'];

    private const PER_PAGE = 20;

    /** @param array<string, mixed> $filters */
    public function execute(Builder $scoped, array $filters): LengthAwarePaginator
    {
        return $scoped
            ->when(isset($filters['application_id']), fn (Builder $q) => $q->where('application_id', (int) $filters['application_id']))
            ->when(isset($filters['outcome']), fn (Builder $q) => $q->where('outcome', $filters['outcome']))
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(self::PER_PAGE);
    }
}
