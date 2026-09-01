<?php

declare(strict_types=1);

namespace App\Domains\Vacancy\Support;

use App\Domains\Identity\Enums\RoleCode;
use App\Domains\Identity\Models\User;
use App\Domains\Vacancy\Models\Vacancy;
use Illuminate\Database\Eloquent\Builder;

/**
 * Query-layer vacancy scope — "**Query-scoped, never merely Policy-checked**"
 * (GET /api/v1/vacancies). A vacancy outside scope is absent from results and
 * returns 404 on direct read.
 *
 * Recruiter reach requires an ACTIVE company membership (OL-1, INV-017);
 * neither a revoked nor a deactivated row grants anything.
 */
final class VacancyScope
{
    public static function queryFor(User $user): Builder
    {
        if (self::hasAnyRole($user, [RoleCode::SuperAdmin])) {
            return Vacancy::query();
        }

        // CAMPUS_SCOPE (HR_ADMIN / Admin Kepegawaian) — campus-owned vacancies
        // only, never a company vacancy (mirrors how Career Center below gets
        // company-only). Activated by the approved PO / SPEC-DOC decision.
        if (self::hasAnyRole($user, [RoleCode::HrAdmin])) {
            return Vacancy::query()->where('ownership_type', 'CAMPUS');
        }

        // Career Center reads company vacancies for moderation; Auditor reads
        // read-only within its permitted scope. Neither reaches campus vacancies
        // through this phase's surface.
        if (self::hasAnyRole($user, [RoleCode::CareerCenterStaff, RoleCode::CareerCenterManager, RoleCode::Auditor])) {
            return Vacancy::query()->where('ownership_type', 'COMPANY');
        }

        return Vacancy::query()
            ->where('ownership_type', 'COMPANY')
            ->whereIn('company_id', self::activeCompanyIds($user));
    }

    public static function findFor(User $user, int $vacancyId): ?Vacancy
    {
        return self::queryFor($user)->whereKey($vacancyId)->first();
    }

    /** Career Center, Auditor and Super Admin may see moderation internal notes; recruiters never do. */
    public static function maySeeInternalNotes(User $user): bool
    {
        return self::hasAnyRole($user, [
            RoleCode::CareerCenterStaff, RoleCode::CareerCenterManager,
            RoleCode::Auditor, RoleCode::SuperAdmin,
        ]);
    }

    /** @return \Illuminate\Database\Query\Builder */
    private static function activeCompanyIds(User $user)
    {
        return \Illuminate\Support\Facades\DB::table('company_members')
            ->select('company_id')
            ->where('user_id', $user->getKey())
            ->where('status', 'ACTIVE')
            ->whereNull('revoked_at');
    }

    /** @param list<RoleCode> $roles */
    private static function hasAnyRole(User $user, array $roles): bool
    {
        foreach ($roles as $role) {
            if ($user->hasActiveRole($role)) {
                return true;
            }
        }

        return false;
    }
}
