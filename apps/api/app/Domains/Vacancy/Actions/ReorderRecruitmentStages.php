<?php

declare(strict_types=1);

namespace App\Domains\Vacancy\Actions;

use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\AuditWriter;
use App\Domains\Vacancy\Exceptions\RecruitmentStageNotInVacancy;
use App\Domains\Vacancy\Models\RecruitmentStage;
use App\Domains\Vacancy\Models\Vacancy;
use Illuminate\Support\Facades\DB;

/**
 * `POST /vacancies/{vacancy}/stages/reorder` — a whole-set atomic operation
 * (`API_SIZE_REVIEW.md` Q-4): the client submits the complete new ordering in
 * one request, never N individual `sort_order` edits, so no transient
 * duplicate position or partial-failure state is ever observable.
 *
 * "Reorder locks the vacancy's stage set" (API_CONTRACT.md) — the vacancy row
 * is locked for the whole transaction, serialising two concurrent reorders on
 * the same vacancy. `sort_order` changes only; `applications.current_stage_id`
 * is never rewritten (RS-2).
 */
final class ReorderRecruitmentStages
{
    public function __construct(private readonly AuditWriter $audit) {}

    /** @param list<int> $orderedStageIds */
    public function execute(User $actor, Vacancy $vacancy, array $orderedStageIds): void
    {
        DB::transaction(function () use ($actor, $vacancy, $orderedStageIds): void {
            /** @var Vacancy $locked */
            $locked = Vacancy::query()->whereKey($vacancy->getKey())->lockForUpdate()->firstOrFail();

            $existingIds = RecruitmentStage::query()->where('vacancy_id', $locked->getKey())
                ->lockForUpdate()->pluck('id')->map(fn ($id): int => (int) $id)->all();

            // The submitted set must be exactly this vacancy's current stage
            // set — no foreign id, none missing, none duplicated. A partial or
            // foreign submission is rejected wholesale rather than silently
            // reordering a subset (INV-019).
            sort($existingIds);
            $submitted = $orderedStageIds;
            sort($submitted);
            if ($submitted !== $existingIds || count($orderedStageIds) !== count(array_unique($orderedStageIds))) {
                throw new RecruitmentStageNotInVacancy();
            }

            foreach (array_values($orderedStageIds) as $position => $stageId) {
                RecruitmentStage::query()->whereKey($stageId)->update(['sort_order' => $position]);
            }

            $this->audit->record('vacancy_stage_changed', $actor, 'vacancy', (int) $locked->getKey(), [
                'change' => 'reordered',
                'stage_ids' => $orderedStageIds,
            ]);
        });
    }
}
