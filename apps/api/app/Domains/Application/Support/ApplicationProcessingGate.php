<?php

declare(strict_types=1);

namespace App\Domains\Application\Support;

use App\Domains\Company\Enums\CompanyStatus;
use App\Domains\Company\Models\Company;
use App\Domains\Vacancy\Exceptions\VacancyCompanyNotVerified;
use App\Domains\Vacancy\Exceptions\VacancyNotProcessable;
use App\Domains\Vacancy\Models\Vacancy;

/**
 * RA-2, approved and CLOSED: beyond COMPANY_SCOPE object authorization, a
 * write action against an existing application requires the owning company
 * to be VERIFIED and the vacancy to be in a processing-permitted state.
 * Object authorization and this business-legality gate are separate checks —
 * applied identically regardless of actor, including SUPER_ADMIN, because no
 * source exempts it.
 *
 * CLOSED/EXPIRED stop new intake but do not block processing candidates who
 * applied while intake was valid; SUSPENDED and any other state do. Reads are
 * never subject to this gate.
 */
final class ApplicationProcessingGate
{
    private const PROCESSABLE_VACANCY_STATUSES = ['PUBLISHED', 'CLOSED', 'EXPIRED'];

    /**
     * @param  ?Company  $company  the owning company for a COMPANY vacancy;
     *                             `null` for a CAMPUS vacancy, which has none
     *                             (FR-HR-001). For campus the RA-2 gate reduces
     *                             to the vacancy-status check only — there is no
     *                             company-verification component.
     *
     * @throws VacancyCompanyNotVerified|VacancyNotProcessable
     */
    public static function assertProcessable(?Company $company, Vacancy $vacancy): void
    {
        if ($vacancy->ownership_type === 'COMPANY'
            && ($company === null || $company->verification_status !== CompanyStatus::Verified)) {
            throw new VacancyCompanyNotVerified();
        }

        if (! in_array($vacancy->current_status?->value, self::PROCESSABLE_VACANCY_STATUSES, true)) {
            throw new VacancyNotProcessable();
        }
    }
}
