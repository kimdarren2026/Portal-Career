<?php

declare(strict_types=1);

namespace App\Domains\Vacancy\Enums;

/** FR-VAC-001. `CAMPUS_EMPLOYMENT` belongs to the campus flow (INV-018). */
enum VacancyType: string
{
    case CampusEmployment = 'CAMPUS_EMPLOYMENT';
    case CompanyEmployment = 'COMPANY_EMPLOYMENT';
    case Internship = 'INTERNSHIP';

    /** The two types a company may author (POST /companies/{company}/vacancies). */
    public static function companyAuthorable(): array
    {
        return [self::CompanyEmployment->value, self::Internship->value];
    }
}
