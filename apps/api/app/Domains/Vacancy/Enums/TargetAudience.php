<?php

declare(strict_types=1);

namespace App\Domains\Vacancy\Enums;

/** Exactly four values (INV-006, FR-VAC-002). No ACTIVE_STUDENT, no FRESH_GRADUATE. */
enum TargetAudience: string
{
    case Public = 'PUBLIC';
    case AlumniOnly = 'ALUMNI_ONLY';
    case FinalYearAndAlumni = 'FINAL_YEAR_AND_ALUMNI';
    case Internal = 'INTERNAL';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
