<?php

declare(strict_types=1);

namespace App\Domains\Application\Support;

use App\Domains\Application\Models\Application;
use App\Domains\Identity\Models\User;
use App\Domains\Vacancy\Support\SelectorAssignmentScope;
use Illuminate\Database\Eloquent\Builder;

/**
 * `ASSIGNED_STAGE` read scope for `GET /applications(/{application})`
 * (AUTHORIZATION_MATRIX.md §4.6 — SELECTOR `S`). Query-scoped, never
 * filtered after fetch: an application is reachable only while it currently
 * sits on a stage the actor holds an active `selection_stage_assignments`
 * row for (INV-037). A SELECTOR with no active assignment — or a
 * non-SELECTOR — resolves to an empty set, never a fallback grant.
 *
 * Scope is per stage and per current position: moving the application off
 * that stage removes it from the selector's view immediately, and revoking
 * the assignment does the same. It never extends to another stage of the
 * same vacancy, nor to another vacancy.
 */
final class SelectorApplicationScope
{
    public static function isSelectorActor(User $user): bool
    {
        return SelectorAssignmentScope::isSelectorActor($user);
    }

    public static function queryFor(User $user): Builder
    {
        $stageIds = SelectorAssignmentScope::assignedStageIds($user);

        if ($stageIds === []) {
            return Application::query()->whereRaw('1 = 0');
        }

        return Application::query()->whereIn('current_stage_id', $stageIds);
    }

    public static function findFor(User $user, int $applicationId): ?Application
    {
        return self::queryFor($user)->whereKey($applicationId)->first();
    }
}
