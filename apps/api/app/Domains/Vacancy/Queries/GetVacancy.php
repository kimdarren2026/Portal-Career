<?php

declare(strict_types=1);

namespace App\Domains\Vacancy\Queries;

use App\Domains\Identity\Models\User;
use App\Domains\Vacancy\Models\Vacancy;
use App\Domains\Vacancy\Support\VacancyPresenter;
use App\Domains\Vacancy\Support\VacancyScope;

/** GET /vacancies/{vacancy} with allow-listed `?include=` expansion. */
final class GetVacancy
{
    /** Only the includes this phase implements; stages and moderation_history are later phases. */
    public const INCLUDES = ['requirements', 'screening_questions', 'versions'];

    /** @param list<string> $include @return array<string, mixed> */
    public function execute(User $user, Vacancy $vacancy, array $include = []): array
    {
        return VacancyPresenter::detail($vacancy, $include, VacancyScope::maySeeInternalNotes($user));
    }
}
