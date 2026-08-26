<?php

declare(strict_types=1);

namespace App\Domains\Vacancy\Queries;

use App\Domains\Vacancy\Support\PublicVacancyPresenter;
use App\Domains\Vacancy\Support\PublicVacancyScope;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * GET /api/v1/public/vacancies (API_CONTRACT.md, VERSIONED_API, no
 * authentication). Filters, sort, and pagination are exactly the frozen
 * allow-list — nothing here is invented beyond what the contract names.
 */
final class ListPublicVacancies
{
    public const FILTERS = [
        'q', 'vacancy_type', 'employment_type', 'workplace_mode',
        'province_geographic_area_id', 'city_geographic_area_id',
        'study_program_id', 'industry_id', 'company_id', 'target_audience',
    ];

    public const SORTABLE = ['published_at', 'close_at', 'title'];

    private const DEFAULT_SORT = 'published_at';

    private const DEFAULT_PER_PAGE = 20;

    private const MAX_PER_PAGE = 50;

    /** @param array<string, mixed> $filters */
    public function execute(
        array $filters = [],
        string $sort = self::DEFAULT_SORT,
        string $direction = 'desc',
        int $perPage = self::DEFAULT_PER_PAGE,
        ?string $cursor = null,
    ): LengthAwarePaginator|CursorPaginator {
        $sort = in_array($sort, self::SORTABLE, true) ? $sort : self::DEFAULT_SORT;
        $direction = $direction === 'asc' ? 'asc' : 'desc';
        $perPage = max(1, min($perPage, self::MAX_PER_PAGE));

        $query = PublicVacancyScope::query()
            ->with('company')
            ->when(isset($filters['vacancy_type']), fn (Builder $q) => $q->where('vacancy_type', $filters['vacancy_type']))
            ->when(isset($filters['employment_type']), fn (Builder $q) => $q->where('employment_type', $filters['employment_type']))
            ->when(isset($filters['workplace_mode']), fn (Builder $q) => $q->where('workplace_mode', $filters['workplace_mode']))
            ->when(isset($filters['province_geographic_area_id']), fn (Builder $q) => $q->where('province_geographic_area_id', (int) $filters['province_geographic_area_id']))
            ->when(isset($filters['city_geographic_area_id']), fn (Builder $q) => $q->where('city_geographic_area_id', (int) $filters['city_geographic_area_id']))
            ->when(isset($filters['industry_id']), fn (Builder $q) => $q->whereHas('company', fn (Builder $c) => $c->where('industry_id', (int) $filters['industry_id'])))
            ->when(isset($filters['company_id']), fn (Builder $q) => $q->where('company_id', (int) $filters['company_id']))
            ->when(isset($filters['target_audience']), fn (Builder $q) => $q->where('target_audience', $filters['target_audience']))
            ->when(isset($filters['study_program_id']), fn (Builder $q) => $q->whereHas(
                'requirements',
                fn (Builder $r) => $r->where('requirement_type', 'STUDY_PROGRAM')->where('study_program_id', (int) $filters['study_program_id']),
            ))
            // IMPLEMENTATION-LEVEL search behaviour (INDEX_STRATEGY.md §3.5): a
            // case-insensitive substring match on title, the MVP-recommended
            // approach at current corpus size. Not new business semantics — no
            // ranking, fuzzy, or semantic matching is introduced.
            ->when(isset($filters['q']) && trim((string) $filters['q']) !== '', fn (Builder $q) => $q->whereRaw(
                'lower(title) like ?',
                ['%'.mb_strtolower(trim((string) $filters['q'])).'%'],
            ))
            // Deterministic tie-breaker for stable pagination at scale — a
            // technical determinism measure, not a new sort mode.
            ->orderBy($sort, $direction)
            ->orderBy('id', 'desc');

        $page = $cursor !== null
            ? $query->cursorPaginate($perPage, ['*'], 'cursor', $cursor)
            : $query->paginate($perPage);

        $companyIds = $page->getCollection()
            ->map(fn ($v) => $v->company_id === null ? null : (int) $v->company_id)
            ->filter()->unique()->values()->all();
        $mitraLookup = PublicVacancyPresenter::batchMitraKampusActive($companyIds);

        return $page->through(fn ($vacancy): array => PublicVacancyPresenter::summary($vacancy, $mitraLookup));
    }
}
