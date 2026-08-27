<?php

declare(strict_types=1);

namespace App\Domains\Recruitment\SelectionSchedule\Support;

use App\Domains\Candidate\Models\CandidateProfile;
use App\Domains\Identity\Enums\RoleCode;
use App\Domains\Identity\Models\User;
use App\Domains\Recruitment\SelectionSchedule\Models\SelectionSchedule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * COMPANY_SCOPE / SUPER_ADMIN / AUDITOR (read-only) query scoping for
 * Selection Schedule Foundation v1 — query-scoped, never filtered after
 * fetch, mirroring `RecruiterApplicationScope`'s established pattern.
 * Reaches only schedules on COMPANY-owned vacancies of a company where the
 * actor holds an active (non-revoked) `company_members` row (INV-017), or
 * every COMPANY-owned schedule for SUPER_ADMIN/AUDITOR. CAMPUS_SCOPE is not
 * implemented — no Campus vacancy runtime exists. SELECTOR's `ASSIGNED_STAGE`
 * grant is inert here — company-side selector assignment remains deferred,
 * so no branch is offered for it.
 */
final class SelectionScheduleScope
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

    /** Recruiter/Admin (COMPANY_SCOPE), SUPER_ADMIN and AUDITOR (both ALLOW/READ_ONLY, unrestricted by company). */
    public static function operationalQueryFor(User $user): Builder
    {
        if (self::isSuperAdmin($user) || self::isAuditor($user)) {
            return SelectionSchedule::query()->whereHas('application.vacancy', fn (Builder $q) => $q->where('ownership_type', 'COMPANY'));
        }

        return SelectionSchedule::query()
            ->whereHas('application.vacancy', fn (Builder $q) => $q->where('ownership_type', 'COMPANY')
                ->whereIn('company_id', self::activeCompanyIds($user)));
    }

    public static function findFor(User $user, int $scheduleId): ?SelectionSchedule
    {
        return self::operationalQueryFor($user)->whereKey($scheduleId)->first();
    }

    public static function candidateQueryFor(CandidateProfile $profile): Builder
    {
        return SelectionSchedule::query()
            ->whereHas('application', fn (Builder $q) => $q->where('candidate_profile_id', $profile->getKey()));
    }

    public static function candidateFindFor(CandidateProfile $profile, int $scheduleId): ?SelectionSchedule
    {
        return self::candidateQueryFor($profile)->whereKey($scheduleId)->first();
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
