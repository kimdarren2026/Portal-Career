<?php

declare(strict_types=1);

namespace App\Domains\Vacancy\Support;

use App\Domains\Identity\Enums\RoleCode;
use App\Domains\Identity\Models\User;
use App\Domains\Vacancy\Models\SelectionStageAssignment;
use Illuminate\Database\Eloquent\Builder;

/**
 * `ASSIGNED_STAGE` primitive (INV-037, OL-2). Access derives ONLY from a row
 * with `revoked_at` null for the exact `recruitment_stages` row — never from
 * the SELECTOR role alone, never from another stage of the same vacancy, and
 * never from another vacancy. Every list surface joins the assignment in the
 * query; a post-filter is insufficient.
 */
final class SelectorAssignmentScope
{
    /** Does the actor hold an active SELECTOR role at all? A precondition, not a grant. */
    public static function isSelectorActor(User $user): bool
    {
        return $user->hasActiveRole(RoleCode::Selector);
    }

    /** True only when an active assignment links this actor to this exact stage. */
    public static function hasActiveAssignment(User $user, int $stageId): bool
    {
        if (! self::isSelectorActor($user)) {
            return false;
        }

        return SelectionStageAssignment::query()
            ->where('recruitment_stage_id', $stageId)
            ->where('selector_user_id', $user->getKey())
            ->whereNull('revoked_at')
            ->exists();
    }

    /**
     * The recruitment-stage ids this actor may currently reach. Empty for a
     * SELECTOR with no active assignment, and empty for a non-SELECTOR.
     *
     * @return list<int>
     */
    public static function assignedStageIds(User $user): array
    {
        if (! self::isSelectorActor($user)) {
            return [];
        }

        return SelectionStageAssignment::query()
            ->where('selector_user_id', $user->getKey())
            ->whereNull('revoked_at')
            ->pluck('recruitment_stage_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }

    /** Full roster for one stage, revoked history included (contract: list "including revoked history"). */
    public static function rosterForStage(int $stageId): Builder
    {
        return SelectionStageAssignment::query()
            ->where('recruitment_stage_id', $stageId)
            ->orderBy('id');
    }
}
