<?php

declare(strict_types=1);

namespace App\Domains\Vacancy\Actions;

use App\Domains\Vacancy\Enums\VacancyStatus;
use App\Domains\Vacancy\Models\Vacancy;
use Illuminate\Support\Facades\DB;

/**
 * Scheduled publication (B-4): a SCHEDULED company vacancy whose `open_at` has
 * been reached becomes PUBLISHED, through the same authoritative Action the
 * approval path uses.
 *
 * Deliberately narrow. It selects SCHEDULED company vacancies only and never
 * touches APPROVED, PUBLISHED, SUSPENDED, CLOSED or EXPIRED rows.
 *
 * **This is not the expiry job.** Automatic `PUBLISHED → EXPIRED` (O-7) stays
 * open and unimplemented: its boundary semantics, audit event name,
 * notification rule and batch behaviour are undetermined, and no
 * `vacancy_expired` event is invented here.
 *
 * Each vacancy is published in its own transaction under its own row lock, and
 * the status is re-checked after the lock, so two concurrent runs cannot
 * publish the same vacancy twice or move `published_at` (INV-013).
 */
// Campus vacancies (FSD §8.4 'SCHEDULED -> PUBLISHED, Reach open date') are
// published by the same run — CAMPUS_SCOPE activation (PO / SPEC-DOC).
final class PublishScheduledVacancies
{
    public function __construct(private readonly PublishVacancy $publisher) {}

    /** @return int Number of vacancies published by this run. */
    public function execute(): int
    {
        $due = Vacancy::query()
            ->whereIn('ownership_type', ['COMPANY', 'CAMPUS'])
            ->where('current_status', VacancyStatus::Scheduled->value)
            ->whereNotNull('open_at')
            ->where('open_at', '<=', now())
            ->orderBy('id')
            ->pluck('id');

        $published = 0;

        foreach ($due as $id) {
            $published += DB::transaction(function () use ($id): int {
                /** @var Vacancy|null $locked */
                $locked = Vacancy::query()->whereKey($id)->lockForUpdate()->first();

                // Re-checked under the lock: a concurrent run, an approval or a
                // suspension may have moved this row already.
                if ($locked === null
                    || $locked->current_status !== VacancyStatus::Scheduled
                    || $locked->open_at === null
                    || $locked->open_at > now()) {
                    return 0;
                }

                // System-driven: there is no acting user, and none is invented.
                $this->publisher->execute($locked, null);

                return 1;
            });
        }

        return $published;
    }
}
