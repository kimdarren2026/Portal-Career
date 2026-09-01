<?php

declare(strict_types=1);

namespace App\Domains\Recruitment\Offering\Support;

use App\Domains\Candidate\Models\CandidateProfile;
use App\Domains\Identity\Enums\RoleCode;
use App\Domains\Identity\Models\User;
use App\Domains\Recruitment\Offering\Models\Offer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * COMPANY_SCOPE / SUPER_ADMIN query scoping for recruiter-side Offering
 * writes, plus candidate OWN scoping for accept/reject — mirroring
 * `EvaluationScope`/`SelectionScheduleScope`'s established pattern.
 * `AUDITOR` deliberately has no branch here — recruiter-side Offering
 * capabilities are plain `DENY` for Auditor in the frozen matrix.
 */
final class OfferScope
{
    public static function isRecruiterOrAdmin(User $user): bool
    {
        return $user->hasActiveRole(RoleCode::CompanyRecruiter) || $user->hasActiveRole(RoleCode::CompanyAdmin);
    }

    public static function isSuperAdmin(User $user): bool
    {
        return $user->hasActiveRole(RoleCode::SuperAdmin);
    }

    public static function operationalQueryFor(User $user): Builder
    {
        if (self::isSuperAdmin($user)) {
            return Offer::query()->whereHas('application.vacancy', fn (Builder $q) => $q->where('ownership_type', 'COMPANY'));
        }

        // CAMPUS_SCOPE (HR_ADMIN) — campus-owned vacancies only (PO / SPEC-DOC).
        if (\App\Domains\Vacancy\Support\CampusScope::isCampusAdmin($user)) {
            return \App\Domains\Vacancy\Support\CampusScope::throughVacancy(Offer::query(), 'application.vacancy');
        }

        return Offer::query()
            ->whereHas('application.vacancy', fn (Builder $q) => $q->where('ownership_type', 'COMPANY')
                ->whereIn('company_id', self::activeCompanyIds($user)));
    }

    public static function findFor(User $user, int $offerId): ?Offer
    {
        return self::operationalQueryFor($user)->whereKey($offerId)->first();
    }

    public static function candidateFindFor(CandidateProfile $profile, int $offerId): ?Offer
    {
        return Offer::query()
            ->whereHas('application', fn (Builder $q) => $q->where('candidate_profile_id', $profile->getKey()))
            ->whereKey($offerId)->first();
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
