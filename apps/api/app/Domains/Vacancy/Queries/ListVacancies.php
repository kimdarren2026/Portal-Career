<?php

declare(strict_types=1);

namespace App\Domains\Vacancy\Queries;

use App\Domains\Identity\Models\User;
use App\Domains\Vacancy\Support\VacancyPresenter;
use App\Domains\Vacancy\Support\VacancyScope;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * GET /vacancies — scoped in the query itself, never filtered after fetch.
 * Filters and sorts are allow-listed exactly as the contract lists them.
 */
final class ListVacancies
{
    public const FILTERS = ['status', 'vacancy_type', 'target_audience', 'application_method', 'company_id', 'open_from', 'close_to', 'q'];
    public const SORTABLE = ['created_at', 'published_at', 'close_at', 'title'];

    private const PER_PAGE = 20;

    /** @param array<string, mixed> $filters */
    public function execute(User $user, array $filters = [], string $sort = 'created_at', string $direction = 'desc'): LengthAwarePaginator
    {
        $sort = in_array($sort, self::SORTABLE, true) ? $sort : 'created_at';
        $direction = $direction === 'asc' ? 'asc' : 'desc';

        $vacancies = VacancyScope::queryFor($user)
            ->when(isset($filters['status']), fn (Builder $q) => $q->where('current_status', $filters['status']))
            ->when(isset($filters['vacancy_type']), fn (Builder $q) => $q->where('vacancy_type', $filters['vacancy_type']))
            ->when(isset($filters['target_audience']), fn (Builder $q) => $q->where('target_audience', $filters['target_audience']))
            ->when(isset($filters['application_method']), fn (Builder $q) => $q->where('application_method', $filters['application_method']))
            ->when(isset($filters['company_id']), fn (Builder $q) => $q->where('company_id', (int) $filters['company_id']))
            ->when(isset($filters['open_from']), fn (Builder $q) => $q->where('open_at', '>=', $filters['open_from']))
            ->when(isset($filters['close_to']), fn (Builder $q) => $q->where('close_at', '<=', $filters['close_to']))
            ->when(isset($filters['q']), fn (Builder $q) => $q->whereRaw('lower(title) like ?', ['%'.mb_strtolower((string) $filters['q']).'%']))
            ->orderBy($sort, $direction)
            ->orderBy('id')
            ->paginate(self::PER_PAGE);

        $internal = VacancyScope::maySeeInternalNotes($user);

        return $vacancies->through(fn ($vacancy): array => VacancyPresenter::summary($vacancy, $internal));
    }
}
