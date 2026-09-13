<?php

declare(strict_types=1);

namespace App\Domains\VacancyReport\Queries;

use App\Domains\VacancyReport\Models\VacancyReport;
use App\Domains\VacancyReport\Support\VacancyReportVocabulary;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Career Center review queue for vacancy reports (PGC-V1 / PD-C). Career
 * Center is a global reader — the queue is not company-scoped. Reporter
 * identity is limited to "authenticated vs anonymous" plus, for an anonymous
 * reporter, whatever contact they volunteered; the reporter's user id and
 * name are not surfaced to keep review free of bias.
 */
final class ListVacancyReports
{
    public const FILTERS = ['status', 'reason', 'page'];

    private const PER_PAGE = 25;

    /** @param array<string, mixed> $filters */
    public function execute(array $filters = []): LengthAwarePaginator
    {
        return VacancyReport::query()
            ->with('vacancy:id,slug,title')
            ->when(isset($filters['status']) && $filters['status'] !== '', fn (Builder $q) => $q->where('status', $filters['status']))
            ->when(isset($filters['reason']) && $filters['reason'] !== '', fn (Builder $q) => $q->where('reason', $filters['reason']))
            ->orderByRaw("case status when 'NEW' then 0 when 'UNDER_REVIEW' then 1 else 2 end")
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->through(fn (VacancyReport $report): array => [
                'id' => (int) $report->getKey(),
                'vacancy' => $report->vacancy === null ? null : [
                    'id' => (int) $report->vacancy->getKey(),
                    'slug' => $report->vacancy->slug,
                    'title' => $report->vacancy->title,
                ],
                'reason' => $report->reason,
                'reason_label' => VacancyReportVocabulary::REASONS[$report->reason] ?? $report->reason,
                'details' => $report->details,
                'status' => $report->status,
                'reporter' => $report->reporter_user_id !== null ? 'AUTHENTICATED' : 'ANONYMOUS',
                'anonymous_contact' => $report->reporter_user_id !== null ? null : array_filter([
                    'name' => $report->reporter_name,
                    'email' => $report->reporter_email,
                ]),
                'review_started_at' => $report->review_started_at?->toIso8601String(),
                'resolved_at' => $report->resolved_at?->toIso8601String(),
                'resolution_note' => $report->resolution_note,
                'created_at' => $report->created_at?->toIso8601String(),
            ]);
    }
}
