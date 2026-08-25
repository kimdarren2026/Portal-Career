<?php

declare(strict_types=1);

namespace App\Domains\Vacancy\Queries;

use App\Domains\Vacancy\Models\Vacancy;
use App\Domains\Vacancy\Models\VacancyVersion;

/** GET /vacancies/{vacancy}/versions — append-only snapshots, oldest first (FR-VAC-007). */
final class ListVacancyVersions
{
    /** @return list<array<string, mixed>> */
    public function execute(Vacancy $vacancy): array
    {
        return VacancyVersion::query()
            ->where('vacancy_id', $vacancy->getKey())
            ->orderBy('version_number')
            ->get()
            ->map(static fn (VacancyVersion $version): array => [
                'version_number' => $version->version_number,
                'created_at' => $version->created_at?->toIso8601String(),
                'created_by' => (int) $version->created_by,
                'change_reason' => $version->change_reason,
                'snapshot' => $version->snapshot,
            ])->values()->all();
    }
}
