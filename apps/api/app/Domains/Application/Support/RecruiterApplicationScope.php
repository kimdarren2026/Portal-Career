<?php

declare(strict_types=1);

namespace App\Domains\Application\Support;

use App\Domains\Application\Models\Application;
use App\Domains\Identity\Enums\RoleCode;
use App\Domains\Identity\Models\User;
use App\Domains\Vacancy\Support\CampusScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * COMPANY_SCOPE / SUPER_ADMIN's ALLOW for Recruiter Applicant Management
 * Foundation v1 — query-scoped, never filtered after fetch, mirroring
 * `VacancyScope`'s established pattern. Reaches only applications on
 * COMPANY-owned vacancies of a company where the actor holds an active
 * (non-revoked) `company_members` row. CAMPUS_SCOPE and ASSIGNED_STAGE are
 * not implemented here — a caller that does not qualify for either branch
 * gets an empty scope, never a fallback grant.
 */
final class RecruiterApplicationScope
{
    public static function isRecruiterOrAdmin(User $user): bool
    {
        return $user->hasActiveRole(RoleCode::CompanyRecruiter) || $user->hasActiveRole(RoleCode::CompanyAdmin);
    }

    public static function isSuperAdmin(User $user): bool
    {
        return $user->hasActiveRole(RoleCode::SuperAdmin);
    }

    public static function queryFor(User $user): Builder
    {
        if (self::isSuperAdmin($user)) {
            return Application::query()->whereHas('vacancy', fn (Builder $q) => $q->where('ownership_type', 'COMPANY'));
        }

        // CAMPUS_SCOPE (HR_ADMIN / Admin Kepegawaian) — applications on
        // campus-owned vacancies only, never a company vacancy. Activated by
        // the approved PO / SPEC-DOC decision; FR-HR-005.
        if (CampusScope::isCampusAdmin($user)) {
            return CampusScope::throughVacancy(Application::query(), 'vacancy');
        }

        return Application::query()
            ->whereHas('vacancy', fn (Builder $q) => $q->where('ownership_type', 'COMPANY')
                ->whereIn('company_id', self::activeCompanyIds($user)));
    }

    public static function findFor(User $user, int $applicationId): ?Application
    {
        return self::queryFor($user)->whereKey($applicationId)->first();
    }

    /** @return \Illuminate\Database\Query\Builder */
    private static function activeCompanyIds(User $user)
    {
        return DB::table('company_members')
            ->select('company_id')
            ->where('user_id', $user->getKey())
            ->where('status', 'ACTIVE')
            ->whereNull('revoked_at');
    }
}
