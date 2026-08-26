<?php

declare(strict_types=1);

namespace App\Domains\Vacancy\Queries;

use App\Domains\Vacancy\Exceptions\VacancyNotPublic;
use App\Domains\Vacancy\Support\PublicVacancyPresenter;
use App\Domains\Vacancy\Support\PublicVacancyScope;

/**
 * GET /api/v1/public/vacancies/{slug}. Uses the identical predicate as the
 * listing query (PublicVacancyScope) — the row is looked up WITHIN the
 * public-visible scope, never fetched first and then judged. A slug that
 * exists but fails any one condition and a slug that does not exist at all
 * are indistinguishable to the caller (API_CONTRACT.md §10).
 */
final class GetPublicVacancy
{
    /** @return array<string, mixed> */
    public function execute(string $slug): array
    {
        $vacancy = PublicVacancyScope::query()
            ->with('company')
            ->where('slug', $slug)
            ->first();

        if ($vacancy === null) {
            throw new VacancyNotPublic();
        }

        return PublicVacancyPresenter::detail($vacancy);
    }
}
