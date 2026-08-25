<?php

declare(strict_types=1);

namespace App\Domains\Vacancy\Policies;

use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\CompanyMember;
use App\Domains\Identity\Enums\RoleCode;
use App\Domains\Identity\Models\User;
use App\Domains\Vacancy\Models\Vacancy;

/**
 * Company vacancy authoring authorization.
 *
 * Career Center is DENY on authoring and editing — "it moderates vacancy
 * content, it never authors or edits it" (final ruling, 24 August 2026;
 * AUTHORIZATION_MATRIX.md §4.5 footnote 5). Auditor is READ_ONLY: no Policy
 * grants it any write ability anywhere.
 *
 * Moderation abilities are deliberately absent from this class: the moderation
 * actor set is unreconciled between AUTHORIZATION_MATRIX.md §4.5 and the
 * API_CONTRACT.md review sections, and is not decided here.
 */
final class VacancyPolicy
{
    /** Authoring on a company: an ACTIVE membership of that company (INV-017). */
    public function create(User $user, Company $company): bool
    {
        return $this->activeMemberOf($user, (int) $company->getKey());
    }

    public function update(User $user, Vacancy $vacancy): bool
    {
        return $vacancy->isCompanyOwned()
            && $this->activeMemberOf($user, (int) $vacancy->company_id);
    }

    /** Read is governed by the query scope; this is the per-object backstop. */
    public function view(User $user, Vacancy $vacancy): bool
    {
        return $this->isReader($user) || $this->update($user, $vacancy);
    }

    public function manageScreeningQuestions(User $user, Vacancy $vacancy): bool
    {
        return $this->update($user, $vacancy);
    }

    private function activeMemberOf(User $user, int $companyId): bool
    {
        return CompanyMember::query()->active()
            ->where('company_id', $companyId)
            ->where('user_id', $user->getKey())
            ->exists();
    }

    private function isReader(User $user): bool
    {
        foreach ([RoleCode::CareerCenterStaff, RoleCode::CareerCenterManager, RoleCode::Auditor, RoleCode::SuperAdmin] as $role) {
            if ($user->hasActiveRole($role)) {
                return true;
            }
        }

        return false;
    }
}
