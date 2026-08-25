<?php

declare(strict_types=1);

namespace App\Domains\Vacancy\Support;

use App\Domains\Identity\Models\User;
use App\Domains\Vacancy\Models\Vacancy;
use App\Domains\Vacancy\Models\VacancyVersion;

/**
 * Allocates the next `version_number` and appends the snapshot (FR-VAC-007).
 *
 * The caller must already hold the vacancy row lock inside its transaction:
 * that lock is what serialises allocation. `uq_vacancy_versions_vacancy_version`
 * is the backstop — a duplicate number cannot be committed even if a future
 * caller forgets the lock.
 */
final class VacancyVersionWriter
{
    public function append(Vacancy $vacancy, User $actor, ?string $changeReason = null): VacancyVersion
    {
        $next = ((int) VacancyVersion::query()
            ->where('vacancy_id', $vacancy->getKey())
            ->max('version_number')) + 1;

        return VacancyVersion::query()->create([
            'vacancy_id' => $vacancy->getKey(),
            'version_number' => $next,
            'snapshot' => VacancySnapshot::of($vacancy->refresh()),
            'created_by' => $actor->getKey(),
            'change_reason' => $changeReason,
            'created_at' => now(),
        ]);
    }
}
