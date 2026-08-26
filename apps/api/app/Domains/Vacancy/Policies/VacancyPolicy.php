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
 * Moderation (B-3, approved 25 August 2026) belongs to CAREER_CENTER_STAFF,
 * CAREER_CENTER_MANAGER and SUPER_ADMIN, and is barred for any of them who
 * holds an ACTIVE membership of the owning company — a moderator must not
 * review their own company's submission. Recruiters never moderate; Auditor
 * stays read-only.
 *
 * VA-4 is unchanged: moderation authority grants no authoring capability, and
 * the global SUPER_ADMIN role still confers no company authoring.
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

    /**
     * RS-6, approved and CLOSED: unlike screening questions and vacancy
     * editing (VA-4), Super Admin holds an unconditional grant here — no
     * active company membership is required. The frozen matrix's "Manage
     * recruitment stages · reorder" row carries no VA-4 footnote, and RS-6
     * confirms this is a deliberate, separate grant rather than an omission.
     * RS-2 adds no vacancy-status or company-verification gate.
     */
    public function manageStages(User $user, Vacancy $vacancy): bool
    {
        return $this->update($user, $vacancy) || $user->hasActiveRole(RoleCode::SuperAdmin);
    }

    /** Submit is an owner capability, not moderation (FR-VAC-004). */
    public function submitForReview(User $user, Vacancy $vacancy): bool
    {
        return $this->update($user, $vacancy);
    }

    /** B-3: moderator role, company vacancy, and no membership of the owning company. */
    public function moderate(User $user, Vacancy $vacancy): bool
    {
        return $vacancy->isCompanyOwned()
            && $this->isModerator($user)
            && ! $this->activeMemberOf($user, (int) $vacancy->company_id);
    }

    /**
     * Close has two independent paths (B-3): the owner closing their own
     * published vacancy — an ownership capability the conflict rule does not
     * touch — or a moderator closing it under the conflict rule.
     */
    public function close(User $user, Vacancy $vacancy): bool
    {
        return $this->update($user, $vacancy) || $this->moderate($user, $vacancy);
    }

    private function isModerator(User $user): bool
    {
        foreach ([RoleCode::CareerCenterStaff, RoleCode::CareerCenterManager, RoleCode::SuperAdmin] as $role) {
            if ($user->hasActiveRole($role)) {
                return true;
            }
        }

        return false;
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
