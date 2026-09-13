<?php

declare(strict_types=1);

namespace App\Domains\Vacancy\Actions;

use App\Domains\Identity\Enums\RoleCode;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\AuditWriter;
use App\Domains\Vacancy\Exceptions\RecruitmentStageNotFound;
use App\Domains\Vacancy\Exceptions\SelectorAssignmentAlreadyActive;
use App\Domains\Vacancy\Exceptions\SelectorAssignmentUserNotFound;
use App\Domains\Vacancy\Exceptions\SelectorRoleRequired;
use App\Domains\Vacancy\Models\RecruitmentStage;
use App\Domains\Vacancy\Models\SelectionStageAssignment;
use App\Domains\Vacancy\Support\SelectorAssignmentNotifier;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * `POST /stages/{stage}/selector-assignments` (FR-HR-006, ADR-016, INV-037).
 *
 * Selector assignment is a campus-recruitment capability only (AUTHORIZATION_
 * MATRIX.md §4.8 footnote 23): the stage must belong to a `CAMPUS` vacancy.
 * A company stage — or a stage that does not exist — is the same "not found"
 * fact to the caller. The actor gate (`HR_ADMIN` / `SUPER_ADMIN`) is applied
 * by the controller before this Action is reached.
 *
 * The target user must hold an **active** SELECTOR role; that is a
 * precondition for being assigned and grants nothing on its own. At most one
 * active assignment may exist per (stage, selector) — the partial unique
 * index `uq_selection_stage_assignments_stage_selector_active` is the final
 * race authority; a pre-check gives the clean 409 for the common case.
 */
final class AssignSelectorToStage
{
    public function __construct(
        private readonly AuditWriter $audit,
        private readonly SelectorAssignmentNotifier $notifier,
    ) {}

    public function execute(User $actor, int $stageId, int $selectorUserId): SelectionStageAssignment
    {
        return DB::transaction(function () use ($actor, $stageId, $selectorUserId): SelectionStageAssignment {
            $stage = $this->lockCampusStage($stageId);

            /** @var User|null $target */
            $target = User::query()->whereKey($selectorUserId)->first();
            if ($target === null) {
                throw new SelectorAssignmentUserNotFound();
            }

            if (! $target->hasActiveRole(RoleCode::Selector)) {
                throw new SelectorRoleRequired();
            }

            $duplicate = SelectionStageAssignment::query()
                ->where('recruitment_stage_id', $stageId)
                ->where('selector_user_id', $selectorUserId)
                ->whereNull('revoked_at')
                ->lockForUpdate()
                ->exists();
            if ($duplicate) {
                throw new SelectorAssignmentAlreadyActive();
            }

            $now = now();
            $assignment = new SelectionStageAssignment();
            $assignment->forceFill([
                'recruitment_stage_id' => $stageId,
                'selector_user_id' => $selectorUserId,
                'assigned_by_user_id' => $actor->getKey(),
                'assigned_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            try {
                $assignment->save();
            } catch (UniqueConstraintViolationException) {
                // A concurrent winner committed the active assignment first.
                throw new SelectorAssignmentAlreadyActive();
            }

            $this->audit->record('selector_assigned', $actor, 'selection_stage_assignment', (int) $assignment->getKey(), [
                'recruitment_stage_id' => $stageId,
                'selector_user_id' => $selectorUserId,
                'vacancy_id' => (int) $stage->vacancy_id,
            ]);

            $this->notifier->assigned($target, $assignment, (int) $stage->vacancy_id);

            return $assignment->refresh();
        });
    }

    private function lockCampusStage(int $stageId): RecruitmentStage
    {
        /** @var RecruitmentStage|null $stage */
        $stage = RecruitmentStage::query()->whereKey($stageId)->lockForUpdate()->first();

        if ($stage === null) {
            throw new RecruitmentStageNotFound();
        }

        $ownershipType = $stage->vacancy()->value('ownership_type');
        if ($ownershipType !== 'CAMPUS') {
            // Selector assignment is not defined for company vacancies; the
            // caller may not know this campus-only stage would have existed.
            throw new RecruitmentStageNotFound();
        }

        return $stage;
    }
}
