<?php

declare(strict_types=1);

namespace App\Domains\Company\Queries;

use App\Domains\Company\Exceptions\CompanyNotPublic;
use App\Domains\Company\Models\Company;
use App\Domains\Partnership\Support\PartnershipStatus;

/**
 * GET /api/v1/public/companies/{slug} (PD-2). Public company summary only —
 * never documents, never members, never applicants (API_CONTRACT.md).
 *
 * Visible only when `verification_status = VERIFIED` — the same trust gate
 * PD-1 already applies to that company's vacancies (PR-COMP-01: a company is
 * never publicly presented before Career Center verification). A non-VERIFIED
 * company and a nonexistent slug are indistinguishable to the caller, exactly
 * as VacancyNotPublic already behaves for vacancies.
 */
final class GetPublicCompany
{
    /** @return array<string, mixed> */
    public function execute(string $slug): array
    {
        $company = Company::query()
            ->where('slug', $slug)
            ->where('verification_status', 'VERIFIED')
            ->first();

        if ($company === null) {
            throw new CompanyNotPublic();
        }

        return [
            'name' => $company->name,
            // The public logo-serving mechanism is undecided — see
            // PublicVacancyPresenter. The private storage key is never exposed.
            'logo_url' => null,
            'industry_id' => $company->industry_id === null ? null : (int) $company->industry_id,
            'city_geographic_area_id' => $company->city_geographic_area_id === null ? null : (int) $company->city_geographic_area_id,
            'mitra_kampus_active' => PartnershipStatus::isActiveFor((int) $company->getKey()),
        ];
    }
}
