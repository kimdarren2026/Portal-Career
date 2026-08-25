<?php

declare(strict_types=1);

namespace App\Domains\Company\Policies;

use App\Domains\Company\Models\Company;
use App\Domains\Identity\Enums\RoleCode;
use App\Domains\Identity\Models\User;

final class CompanyPolicy
{
    public function view(User $user, Company $company): bool
    {
        return $this->globalReader($user) || $this->member($user, $company);
    }

    public function update(User $user, Company $company): bool
    {
        return $this->superAdmin($user) || $this->member($user, $company);
    }

    public function submit(User $user, Company $company): bool
    {
        return $this->superAdmin($user) || $this->member($user, $company);
    }

    public function review(User $user, Company $company): bool
    {
        // Career Center staff or manager, or Super Admin (approved reconciliation
        // 25 August 2026; AUTHORIZATION_MATRIX.md §4.4 and every API_CONTRACT.md
        // review section now agree).
        if (! $this->isCareerCenter($user) && ! $this->superAdmin($user)) {
            return false;
        }

        // Approved 25 August 2026: an active member of a company may never review
        // that company, whatever their role. The prohibition follows the reviewer,
        // not the role code.
        return ! $this->member($user, $company);
    }

    /**
     * Member management is a Company Admin capability (AUTHORIZATION_MATRIX
     * §4.3). Open decision 6 is closed: the creator is COMPANY_ADMIN, and an
     * ordinary COMPANY_RECRUITER does not manage members.
     */
    public function manageMembers(User $user, Company $company): bool
    {
        return $this->superAdmin($user) || $company->members()->active()
            ->where('user_id', $user->getKey())
            ->where('company_role', 'COMPANY_ADMIN')
            ->exists();
    }

    public function create(User $user): bool
    {
        return $this->superAdmin($user) || $user->hasActiveRole(RoleCode::CompanyRecruiter);
    }

    public function isCareerCenter(User $user): bool
    {
        return $user->hasActiveRole(RoleCode::CareerCenterStaff) || $user->hasActiveRole(RoleCode::CareerCenterManager);
    }

    public function member(User $user, Company $company): bool
    {
        return $company->members()->active()->where('user_id', $user->getKey())->exists();
    }

    private function globalReader(User $user): bool
    {
        return $this->superAdmin($user) || $user->hasActiveRole(RoleCode::CareerCenterStaff)
            || $user->hasActiveRole(RoleCode::CareerCenterManager) || $user->hasActiveRole(RoleCode::Auditor);
    }

    private function superAdmin(User $user): bool { return $user->hasActiveRole(RoleCode::SuperAdmin); }
}
