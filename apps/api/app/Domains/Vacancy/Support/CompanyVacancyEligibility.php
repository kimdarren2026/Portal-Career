<?php

declare(strict_types=1);

namespace App\Domains\Vacancy\Support;

use App\Domains\Company\Models\Company;

final class CompanyVacancyEligibility
{
    public static function allowed(Company $company): bool
    {
        return $company->isVerified();
    }
}
