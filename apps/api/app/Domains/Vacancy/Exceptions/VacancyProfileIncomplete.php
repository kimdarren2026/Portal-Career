<?php

declare(strict_types=1);

namespace App\Domains\Vacancy\Exceptions;

use RuntimeException;

/** B-5: carries the stable missing-category identifiers for `details.missing`. */
final class VacancyProfileIncomplete extends RuntimeException
{
    /** @param list<string> $missing */
    public function __construct(public readonly array $missing)
    {
        parent::__construct('VACANCY_PROFILE_INCOMPLETE');
    }
}
