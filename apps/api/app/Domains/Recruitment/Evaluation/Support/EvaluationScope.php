<?php

declare(strict_types=1);

namespace App\Domains\Recruitment\Evaluation\Support;

use App\Domains\Identity\Enums\RoleCode;
use App\Domains\Identity\Models\User;
use App\Domains\Recruitment\Evaluation\Models\Evaluation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * COMPANY_SCOPE / SUPER_ADMIN query scoping for Evaluation / Scoring
 * Foundation v1 — query-scoped, never filtered after fetch, mirroring
 * `RecruiterApplicationScope`/`SelectionScheduleScope`'s established pattern.
 * Reaches only evaluations on COMPANY-owned vacancies of a company where the
 * actor holds an active (non-revoked) `company_members` row (INV-017), or
 * every COMPANY-owned evaluation for SUPER_ADMIN. `AUDITOR` deliberately has
 * no branch here — the Evaluation matrix rows carry no `READ_ONLY`
 * carve-out, unlike Selection Schedule. `SELECTOR`'s `ASSIGNED_STAGE` grant
 * is inert here — company-side selector assignment remains deferred, so no
 * branch is offered for it.
 */
final class EvaluationScope
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
            return Evaluation::query()->whereHas('application.vacancy', fn (Builder $q) => $q->where('ownership_type', 'COMPANY'));
        }

        return Evaluation::query()
            ->whereHas('application.vacancy', fn (Builder $q) => $q->where('ownership_type', 'COMPANY')
                ->whereIn('company_id', self::activeCompanyIds($user)));
    }

    public static function findFor(User $user, int $evaluationId): ?Evaluation
    {
        return self::operationalQueryFor($user)->whereKey($evaluationId)->first();
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
