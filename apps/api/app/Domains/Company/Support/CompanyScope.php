<?php

declare(strict_types=1);

namespace App\Domains\Company\Support;

use App\Domains\Company\Models\Company;
use App\Domains\Identity\Enums\RoleCode;
use App\Domains\Identity\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Query-layer COMPANY_SCOPE (AUTHORIZATION_MATRIX.md §1 layer 3, OL-1).
 *
 * Out-of-scope companies are absent from the query, so a direct read returns
 * 404 rather than 403 — a 403 would confirm the row exists to an actor who
 * must not know it does.
 */
final class CompanyScope
{
    public static function queryFor(User $user): Builder
    {
        if (self::isGlobalReader($user)) {
            return Company::query();
        }

        // OL-1: an ACTIVE membership is required. Neither a revoked nor a
        // deactivated row grants any reach, and membership is re-checked per
        // request — never cached across requests (ADR-006).
        return Company::query()->whereHas('members', fn (Builder $query) => $query
            ->where('user_id', $user->getKey())
            ->where('status', 'ACTIVE')
            ->whereNull('revoked_at'));
    }

    public static function findFor(User $user, int $companyId): ?Company
    {
        return self::queryFor($user)->whereKey($companyId)->first();
    }

    private static function isGlobalReader(User $user): bool
    {
        foreach ([RoleCode::CareerCenterStaff, RoleCode::CareerCenterManager, RoleCode::Auditor, RoleCode::SuperAdmin] as $role) {
            if ($user->hasActiveRole($role)) {
                return true;
            }
        }

        return false;
    }
}
