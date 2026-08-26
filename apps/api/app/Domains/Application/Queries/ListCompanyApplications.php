<?php

declare(strict_types=1);

namespace App\Domains\Application\Queries;

use App\Domains\Application\Support\RecruiterApplicationPresenter;
use App\Domains\Application\Support\RecruiterApplicationScope;
use App\Domains\Identity\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * `GET /applications` — COMPANY_SCOPE (`COMPANY_RECRUITER`, `COMPANY_ADMIN`)
 * and SUPER_ADMIN's ALLOW, per Recruiter Applicant Management Foundation v1.
 * Query-scoped, never filtered after fetch — reuses the same filter/sort
 * vocabulary already frozen for the candidate list.
 */
final class ListCompanyApplications
{
    public const FILTERS = ['vacancy_id', 'current_status', 'applied_from', 'applied_to'];

    public const SORTABLE = ['first_applied_at', 'updated_at', 'current_status'];

    private const PER_PAGE = 20;

    /** @param array<string, mixed> $filters */
    public function execute(User $user, array $filters = [], string $sort = 'first_applied_at', string $direction = 'desc'): LengthAwarePaginator
    {
        $sort = in_array($sort, self::SORTABLE, true) ? $sort : 'first_applied_at';
        $direction = $direction === 'asc' ? 'asc' : 'desc';

        $applications = RecruiterApplicationScope::queryFor($user)
            ->with(['candidateProfile.user'])
            ->when(isset($filters['vacancy_id']), fn (Builder $q) => $q->where('vacancy_id', (int) $filters['vacancy_id']))
            ->when(isset($filters['current_status']), fn (Builder $q) => $q->where('current_status', $filters['current_status']))
            ->when(isset($filters['applied_from']), fn (Builder $q) => $q->where('first_applied_at', '>=', $filters['applied_from']))
            ->when(isset($filters['applied_to']), fn (Builder $q) => $q->where('first_applied_at', '<=', $filters['applied_to']))
            ->orderBy($sort, $direction)
            ->orderBy('id')
            ->paginate(self::PER_PAGE);

        return $applications->through(fn ($application): array => RecruiterApplicationPresenter::summary($application));
    }
}
