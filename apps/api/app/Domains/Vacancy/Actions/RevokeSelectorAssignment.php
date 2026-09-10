<?php

declare(strict_types=1);

namespace App\Domains\Vacancy\Actions;

use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\AuditWriter;
use App\Domains\Vacancy\Exceptions\SelectionStageAssignmentNotFound;
use App\Domains\Vacancy\Models\SelectionStageAssignment;
use App\Domains\Vacancy\Support\SelectorAssignmentNotifier;
use Illuminate\Support\Facades\DB;

/**
 * `POST /selector-assignments/{assignment}/revoke` (INV-037).
 *
 * Sets `revoked_at` and `revoked_by_user_id`; it NEVER deletes the row and
 * never overwrites `assigned_at` / `assigned_by_user_id`. Revocation takes
 * effect immediately — the next request from that selector sees no scope.
 * Re-revoking an already-revoked assignment is a no-op (still effective),
 * not an error. The actor gate (`HR_ADMIN` / `SUPER_ADMIN`) and the
 * campus-only rule are applied by the controller / the stage lookup here.
 */
final class RevokeSelectorAssignment
{
    public function __construct(
        private readonly AuditWriter $audit,
        private readonly SelectorAssignmentNotifier $notifier,
    ) {}

    public function execute(User $actor, int $assignmentId): SelectionStageAssignment
    {
        return DB::transaction(function () use ($actor, $assignmentId): SelectionStageAssignment {
            /** @var SelectionStageAssignment|null $assignment */
            $assignment = SelectionStageAssignment::query()->whereKey($assignmentId)->lockForUpdate()->first();
            if ($assignment === null) {
                throw new SelectionStageAssignmentNotFound();
            }

            $stageVacancy = DB::table('recruitment_stages')
                ->join('vacancies', 'vacancies.id', '=', 'recruitment_stages.vacancy_id')
                ->where('recruitment_stages.id', $assignment->recruitment_stage_id)
                ->first(['vacancies.id as vacancy_id', 'vacancies.ownership_type']);

            if ($stageVacancy === null || $stageVacancy->ownership_type !== 'CAMPUS') {
                throw new SelectionStageAssignmentNotFound();
            }

            $vacancyId = (int) $stageVacancy->vacancy_id;

            if ($assignment->revoked_at !== null) {
                return $assignment;
            }

            $now = now();
            $assignment->forceFill([
                'revoked_at' => $now,
                'revoked_by_user_id' => $actor->getKey(),
                'updated_at' => $now,
            ])->save();

            $this->audit->record('selector_assignment_revoked', $actor, 'selection_stage_assignment', (int) $assignment->getKey(), [
                'recruitment_stage_id' => (int) $assignment->recruitment_stage_id,
                'selector_user_id' => (int) $assignment->selector_user_id,
                'vacancy_id' => $vacancyId,
            ]);

            /** @var User|null $selector */
            $selector = User::query()->whereKey($assignment->selector_user_id)->first();
            if ($selector !== null) {
                $this->notifier->revoked($selector, $assignment, $vacancyId);
            }

            return $assignment->refresh();
        });
    }
}
