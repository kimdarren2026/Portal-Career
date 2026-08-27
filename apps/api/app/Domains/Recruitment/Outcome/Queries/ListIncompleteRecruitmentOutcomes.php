<?php

declare(strict_types=1);

namespace App\Domains\Recruitment\Outcome\Queries;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * `GET /recruitment-outcomes/incomplete` — H-5 (approved and CLOSED,
 * 27 August 2026). An `INTERNAL_APPLICATION` is incomplete iff
 * `applications.current_status` is terminal (`HIRED`, `REJECTED`,
 * `WITHDRAWN`, `NO_SHOW`) AND no `recruitment_outcomes` row exists for it.
 * No other qualifier — no minimum age, no offer requirement, no vacancy or
 * company lifecycle gate. Takes an already actor-scoped Builder
 * (`RecruitmentOutcomeScope::incompleteQueryFor()`) — never scopes or
 * filters after fetch. Read-only: this class only ever selects.
 */
final class ListIncompleteRecruitmentOutcomes
{
    private const TERMINAL_STATUSES = ['HIRED', 'REJECTED', 'WITHDRAWN', 'NO_SHOW'];

    private const PER_PAGE = 20;

    public function execute(Builder $scoped): LengthAwarePaginator
    {
        return $scoped
            ->whereIn('current_status', self::TERMINAL_STATUSES)
            ->whereNotExists(function ($query): void {
                $query->select(DB::raw(1))
                    ->from('recruitment_outcomes')
                    ->where('recruitment_outcomes.source_type', 'INTERNAL_APPLICATION')
                    ->whereColumn('recruitment_outcomes.application_id', 'applications.id');
            })
            ->orderBy('id', 'desc')
            ->paginate(self::PER_PAGE);
    }
}
