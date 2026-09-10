<?php

declare(strict_types=1);

namespace App\Domains\VacancyReport\Actions;

use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\AuditWriter;
use App\Domains\Vacancy\Support\PublicVacancyScope;
use App\Domains\VacancyReport\Exceptions\VacancyReportVacancyNotFound;
use App\Domains\VacancyReport\Models\VacancyReport;
use App\Domains\VacancyReport\Support\VacancyReportVocabulary;
use Illuminate\Support\Facades\DB;

/**
 * `POST /lowongan/{vacancy}/laporkan` (PGC-V1 / PD-C).
 *
 * The report always targets an actually-published, publicly-visible vacancy
 * (resolved through `PublicVacancyScope` — a non-public/non-existent slug is
 * an enumeration-safe 404). Both anonymous and authenticated reporters are
 * accepted; an authenticated reporter's id is attached server-side and any
 * client-supplied name/email is ignored for them. Reporter identity is never
 * exposed publicly. Audited as `vacancy_report_created`.
 */
final class SubmitVacancyReport
{
    public function __construct(private readonly AuditWriter $audit) {}

    /**
     * @param  array{reason: string, details?: string|null, reporter_name?: string|null, reporter_email?: string|null}  $data
     */
    public function execute(?User $reporter, string $slug, array $data): VacancyReport
    {
        return DB::transaction(function () use ($reporter, $slug, $data): VacancyReport {
            $vacancy = PublicVacancyScope::query()->where('slug', $slug)->first();
            if ($vacancy === null) {
                throw new VacancyReportVacancyNotFound();
            }

            $now = now();
            $report = new VacancyReport();
            $report->forceFill([
                'vacancy_id' => $vacancy->getKey(),
                'reporter_user_id' => $reporter?->getKey(),
                'reporter_name' => $reporter !== null ? null : $this->clean($data['reporter_name'] ?? null),
                'reporter_email' => $reporter !== null ? null : $this->clean($data['reporter_email'] ?? null),
                'reason' => $data['reason'],
                'details' => $this->clean($data['details'] ?? null, VacancyReportVocabulary::MAX_DETAILS),
                'status' => VacancyReportVocabulary::STATUS_NEW,
                'created_at' => $now,
                'updated_at' => $now,
            ])->save();

            // No reporter identity in the audit payload — only the fact and the
            // reason category.
            $this->audit->record('vacancy_report_created', $reporter, 'vacancy_report', (int) $report->getKey(), [
                'vacancy_id' => (int) $vacancy->getKey(),
                'reason' => $data['reason'],
                'anonymous' => $reporter === null,
            ]);

            return $report->refresh();
        });
    }

    private function clean(?string $value, ?int $max = null): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        return $max === null ? $value : mb_substr($value, 0, $max);
    }
}
