<?php

declare(strict_types=1);

namespace App\Domains\Identity\Services;

use App\Domains\Identity\Enums\RoleCode;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Models\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The authorization primitive later Policies build on: which roles does this
 * user hold *right now*?
 *
 * Deliberately narrow. Three things this class must never become:
 *
 *  1. A cache. Role membership is re-read per request. A stale role or
 *     membership cache is a privilege-escalation bug, and no invalidation
 *     strategy justifies that risk (ADR-006).
 *  2. An eligibility oracle. Target-audience eligibility reads
 *     candidate_verifications, never a role code (INV-028). Holding
 *     CANDIDATE_ALUMNI is not proof of alumni verification.
 *  3. A selector authorizer. SELECTOR alone grants no candidate access.
 *     Stage access requires an active selection_stage_assignments row plus a
 *     Policy and an ownership-scoped query (INV-037) — see
 *     grantsStageAccess() below, which always answers false by design.
 */
final class RoleResolver
{
    /** Active assignments only — revoked_at IS NULL (INV-025). */
    public function hasRole(User $user, RoleCode $code): bool
    {
        return $this->activeQuery($user)
            ->whereHas('role', fn (Builder $q) => $q->where('code', $code->value))
            ->exists();
    }

    /**
     * @param  array<int, RoleCode>  $codes
     */
    public function hasAnyRole(User $user, array $codes): bool
    {
        if ($codes === []) {
            return false;
        }

        return $this->activeQuery($user)
            ->whereHas('role', fn (Builder $q) => $q->whereIn(
                'code',
                array_map(static fn (RoleCode $c): string => $c->value, $codes),
            ))
            ->exists();
    }

    /**
     * @return Collection<int, RoleCode>
     */
    public function activeRoles(User $user): Collection
    {
        return $this->activeQuery($user)
            ->with('role')
            ->get()
            ->map(fn (UserRole $assignment): RoleCode => $assignment->role->code)
            ->values();
    }

    /**
     * Every assignment ever made, newest first — including revoked rows, which
     * are never deleted.
     *
     * @return Collection<int, UserRole>
     */
    public function assignmentHistory(User $user): Collection
    {
        return $user->userRoles()
            ->with('role')
            ->orderByDesc('assigned_at')
            ->get();
    }

    /**
     * Always false, on purpose.
     *
     * A role can never answer "may this selector see these candidates?".
     * INV-037 requires an active assignment to the specific recruitment stage,
     * enforced by a Policy and an ownership-scoped query. This method exists so
     * that a future caller reaching for a role-based shortcut finds an explicit
     * refusal instead of writing one.
     */
    public function grantsStageAccess(User $user, RoleCode $code): bool
    {
        return false;
    }

    private function activeQuery(User $user): Builder
    {
        return $user->userRoles()->getQuery()->whereNull('revoked_at');
    }
}
