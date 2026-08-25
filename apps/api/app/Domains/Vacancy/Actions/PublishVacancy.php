<?php

declare(strict_types=1);

namespace App\Domains\Vacancy\Actions;

use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\AuditWriter;
use App\Domains\Vacancy\Enums\VacancyStatus;
use App\Domains\Vacancy\Models\Vacancy;
use App\Domains\Vacancy\Support\VacancyLifecycleNotifier;

/**
 * The single authoritative publication behaviour, shared by approval-in-window
 * and by the scheduler (B-4). It is NOT a user-facing operation and carries no
 * authorization of its own: the caller has already established authority, and
 * no company browser route reaches it.
 *
 * The caller supplies the transaction and must already hold the vacancy row
 * lock — that lock is what makes publication happen exactly once.
 *
 * INV-013: `published_at` is the Time-to-Fill anchor and is written exactly
 * once. An already-published vacancy is returned untouched, so a repeated
 * scheduler pass can never move it.
 */
final class PublishVacancy
{
    public function __construct(
        private readonly AuditWriter $audit,
        private readonly VacancyLifecycleNotifier $notifier,
    ) {}

    public function execute(Vacancy $lockedVacancy, ?User $actor): Vacancy
    {
        if ($lockedVacancy->current_status === VacancyStatus::Published) {
            return $lockedVacancy;
        }

        $lockedVacancy->current_status = VacancyStatus::Published;
        if ($lockedVacancy->published_at === null) {
            $lockedVacancy->published_at = now();
        }
        $lockedVacancy->updated_at = now();
        $lockedVacancy->save();

        $this->audit->record('vacancy_published', $actor, 'vacancy', (int) $lockedVacancy->getKey(), [
            'published_at' => $lockedVacancy->published_at?->toIso8601String(),
        ]);
        $this->notifier->queue($lockedVacancy, 'PUBLISH');

        return $lockedVacancy;
    }
}
