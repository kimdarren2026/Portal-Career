<?php

declare(strict_types=1);

namespace App\Domains\ExternalApply\Queries;

use App\Domains\Candidate\Models\CandidateProfile;
use App\Domains\ExternalApply\Models\ExternalApplyEvent;
use App\Domains\ExternalApply\Support\ExternalApplyEventPresenter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * `GET /candidate/external-apply-events` — the candidate's OWN external-apply
 * history, query-scoped by `candidate_profile_id` and never filtered after
 * fetch. Another candidate's events are unreachable here.
 */
final class ListCandidateExternalApplyEvents
{
    private const PER_PAGE = 20;

    public function execute(CandidateProfile $profile): LengthAwarePaginator
    {
        return ExternalApplyEvent::query()
            ->where('candidate_profile_id', $profile->getKey())
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->through(fn (ExternalApplyEvent $event): array => ExternalApplyEventPresenter::summary($event));
    }
}
