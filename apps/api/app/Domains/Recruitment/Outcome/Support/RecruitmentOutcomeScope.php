<?php

declare(strict_types=1);

namespace App\Domains\Recruitment\Outcome\Support;

use App\Domains\Identity\Enums\RoleCode;
use App\Domains\Identity\Models\User;
use App\Domains\Recruitment\Outcome\Models\RecruitmentOutcome;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * COMPANY_SCOPE / SUPER_ADMIN / AUDITOR (read-only) query scoping for
 * Recruitment Outcome Foundation v1 — `INTERNAL_APPLICATION` only, query
 * scoped, never filtered after fetch, mirroring
 * `SelectionScheduleScope`/`EvaluationScope`'s established pattern. Reaches
 * only outcomes on COMPANY-owned vacancies of a company where the actor
 * holds an active (non-revoked) `company_members` row, or every
 * `INTERNAL_APPLICATION`/`COMPANY` outcome for SUPER_ADMIN/AUDITOR.
 * `CAMPUS_SCOPE` is not implemented — no Campus vacancy runtime exists.
 * Career Center has no branch here — its alumni/reporting grant (RC-2)
 * remains deferred/inactive for this milestone. `SELECTOR` and `CANDIDATE`
 * have no branch — both are plain `DENY` in the frozen matrix.
 */
final class RecruitmentOutcomeScope
{
    public static function isRecruiterOrAdmin(User $user): bool
    {
        return $user->hasActiveRole(RoleCode::CompanyRecruiter) || $user->hasActiveRole(RoleCode::CompanyAdmin);
    }

    public static function isSuperAdmin(User $user): bool
    {
        return $user->hasActiveRole(RoleCode::SuperAdmin);
    }

    public static function isAuditor(User $user): bool
    {
        return $user->hasActiveRole(RoleCode::Auditor);
    }

    /** Recruiter/Admin (COMPANY_SCOPE), SUPER_ADMIN and AUDITOR (both unrestricted by company). */
    public static function operationalQueryFor(User $user): Builder
    {
        if (self::isSuperAdmin($user) || self::isAuditor($user)) {
            return RecruitmentOutcome::query()
                ->where('source_type', 'INTERNAL_APPLICATION')
                ->whereHas('application.vacancy', fn (Builder $q) => $q->where('ownership_type', 'COMPANY'));
        }

        return RecruitmentOutcome::query()
            ->where('source_type', 'INTERNAL_APPLICATION')
            ->whereHas('application.vacancy', fn (Builder $q) => $q->where('ownership_type', 'COMPANY')
                ->whereIn('company_id', self::activeCompanyIds($user)));
    }

    public static function findFor(User $user, int $outcomeId): ?RecruitmentOutcome
    {
        return self::operationalQueryFor($user)->whereKey($outcomeId)->first();
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
