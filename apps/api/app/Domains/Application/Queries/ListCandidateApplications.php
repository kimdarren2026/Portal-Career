<?php

declare(strict_types=1);

namespace App\Domains\Application\Queries;

use App\Domains\Application\Support\ApplicationPresenter;
use App\Domains\Application\Support\ApplicationScope;
use App\Domains\Identity\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * `GET /applications` — candidate `OWN` scope ("Lamaran Saya"). Query-scoped,
 * never filtered after fetch. Foundation v1 implements the candidate scope
 * only; COMPANY_SCOPE/CAMPUS_SCOPE/ASSIGNED_STAGE/Auditor belong to the
 * Recruiter Applicant Management phase.
 */
final class ListCandidateApplications
{
    public const FILTERS = ['vacancy_id', 'current_status', 'applied_from', 'applied_to'];

    public const SORTABLE = ['first_applied_at', 'updated_at', 'current_status'];

    private const PER_PAGE = 20;

    public function __construct(private readonly ApplicationScope $scope) {}

    /** @param array<string, mixed> $filters */
    public function execute(User $user, array $filters = [], string $sort = 'first_applied_at', string $direction = 'desc'): LengthAwarePaginator
    {
        $sort = in_array($sort, self::SORTABLE, true) ? $sort : 'first_applied_at';
        $direction = $direction === 'asc' ? 'asc' : 'desc';

        $applications = $this->scope->queryFor($user)
            ->when(isset($filters['vacancy_id']), fn (Builder $q) => $q->where('vacancy_id', (int) $filters['vacancy_id']))
            ->when(isset($filters['current_status']), fn (Builder $q) => $q->where('current_status', $filters['current_status']))
            ->when(isset($filters['applied_from']), fn (Builder $q) => $q->where('first_applied_at', '>=', $filters['applied_from']))
            ->when(isset($filters['applied_to']), fn (Builder $q) => $q->where('first_applied_at', '<=', $filters['applied_to']))
            ->orderBy($sort, $direction)
            ->orderBy('id')
            ->paginate(self::PER_PAGE);

        return $applications->through(fn ($application): array => ApplicationPresenter::summary($application));
    }
}
