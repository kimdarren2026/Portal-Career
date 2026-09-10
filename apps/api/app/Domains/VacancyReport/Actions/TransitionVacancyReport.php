<?php

declare(strict_types=1);

namespace App\Domains\VacancyReport\Actions;

use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\AuditWriter;
use App\Domains\VacancyReport\Exceptions\VacancyReportInvalidTransition;
use App\Domains\VacancyReport\Exceptions\VacancyReportNotFound;
use App\Domains\VacancyReport\Exceptions\VacancyReportSelfReview;
use App\Domains\VacancyReport\Models\VacancyReport;
use App\Domains\VacancyReport\Support\VacancyReportVocabulary;
use Illuminate\Support\Facades\DB;

/**
 * Career Center review transitions for a vacancy report (PGC-V1 / PD-C):
 *
 *  - `start`   : NEW -> UNDER_REVIEW            (audit `vacancy_report_review_started`)
 *  - `action`  : NEW|UNDER_REVIEW -> ACTIONED   (audit `vacancy_report_actioned`)
 *  - `dismiss` : NEW|UNDER_REVIEW -> DISMISSED  (audit `vacancy_report_dismissed`)
 *
 * A Career Center reviewer who filed the report may not review it
 * (conflict-of-interest). Recruiters never reach this Action; Super Admin may
 * read reports but does not transition them (checked in the controller).
 */
final class TransitionVacancyReport
{
    public function __construct(private readonly AuditWriter $audit) {}

    public function start(User $reviewer, int $reportId): VacancyReport
    {
        return $this->transition($reviewer, $reportId, 'start');
    }

    public function action(User $reviewer, int $reportId, ?string $note): VacancyReport
    {
        return $this->transition($reviewer, $reportId, 'action', $note);
    }

    public function dismiss(User $reviewer, int $reportId, ?string $note): VacancyReport
    {
        return $this->transition($reviewer, $reportId, 'dismiss', $note);
    }

    private function transition(User $reviewer, int $reportId, string $kind, ?string $note = null): VacancyReport
    {
        return DB::transaction(function () use ($reviewer, $reportId, $kind, $note): VacancyReport {
            /** @var VacancyReport|null $report */
            $report = VacancyReport::query()->whereKey($reportId)->lockForUpdate()->first();
            if ($report === null) {
                throw new VacancyReportNotFound();
            }

            if ($report->reporter_user_id !== null && (int) $report->reporter_user_id === (int) $reviewer->getKey()) {
                throw new VacancyReportSelfReview();
            }

            $now = now();
            $note = $note !== null && trim($note) !== '' ? mb_substr(trim($note), 0, VacancyReportVocabulary::MAX_DETAILS) : null;

            match ($kind) {
                'start' => $this->applyStart($report, $reviewer, $now),
                'action' => $this->applyResolve($report, $reviewer, $now, VacancyReportVocabulary::STATUS_ACTIONED, $note),
                'dismiss' => $this->applyResolve($report, $reviewer, $now, VacancyReportVocabulary::STATUS_DISMISSED, $note),
            };

            $auditAction = match ($kind) {
                'start' => 'vacancy_report_review_started',
                'action' => 'vacancy_report_actioned',
                'dismiss' => 'vacancy_report_dismissed',
            };
            $this->audit->record($auditAction, $reviewer, 'vacancy_report', (int) $report->getKey(), array_filter([
                'vacancy_id' => (int) $report->vacancy_id,
                'note' => $note,
            ], static fn ($v): bool => $v !== null));

            return $report->refresh();
        });
    }

    private function applyStart(VacancyReport $report, User $reviewer, \DateTimeInterface $now): void
    {
        if ($report->status !== VacancyReportVocabulary::STATUS_NEW) {
            throw new VacancyReportInvalidTransition();
        }
        $report->forceFill([
            'status' => VacancyReportVocabulary::STATUS_UNDER_REVIEW,
            'reviewed_by_user_id' => $reviewer->getKey(),
            'review_started_at' => $now,
            'updated_at' => $now,
        ])->save();
    }

    private function applyResolve(VacancyReport $report, User $reviewer, \DateTimeInterface $now, string $status, ?string $note): void
    {
        if (! in_array($report->status, [VacancyReportVocabulary::STATUS_NEW, VacancyReportVocabulary::STATUS_UNDER_REVIEW], true)) {
            throw new VacancyReportInvalidTransition();
        }
        $report->forceFill([
            'status' => $status,
            'reviewed_by_user_id' => $reviewer->getKey(),
            'review_started_at' => $report->review_started_at ?? $now,
            'resolved_at' => $now,
            'resolution_note' => $note,
            'updated_at' => $now,
        ])->save();
    }
}
