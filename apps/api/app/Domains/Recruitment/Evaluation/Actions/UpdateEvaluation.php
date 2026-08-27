<?php

declare(strict_types=1);

namespace App\Domains\Recruitment\Evaluation\Actions;

use App\Domains\Application\Exceptions\ApplicationTerminal;
use App\Domains\Application\Models\Application;
use App\Domains\Application\Support\ApplicationProcessingGate;
use App\Domains\Company\Models\Company;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\AuditWriter;
use App\Domains\Recruitment\Evaluation\Exceptions\EvaluationAlreadySubmitted;
use App\Domains\Recruitment\Evaluation\Exceptions\EvaluationNotOwned;
use App\Domains\Recruitment\Evaluation\Exceptions\EvaluationStageTargetInactive;
use App\Domains\Recruitment\Evaluation\Models\Evaluation;
use App\Domains\Vacancy\Exceptions\RecruitmentStageNotInVacancy;
use App\Domains\Vacancy\Models\RecruitmentStage;
use App\Domains\Vacancy\Models\Vacancy;
use Illuminate\Support\Facades\DB;

/**
 * `PATCH /evaluations/{evaluation}` — Evaluation / Scoring Foundation v1
 * (EV-1, EV-2, RC-1, all approved and CLOSED).
 *
 * The evaluation row is the primary contended resource for this write (a
 * concurrent submit could finalize it mid-edit), so it is locked first —
 * mirroring `RescheduleSelectionSchedule`'s own schedule-first pattern for a
 * write against an existing row. Application → vacancy → stage → company
 * follow in the same relative order `CreateEvaluation` already established.
 *
 * Scalar fields only in Foundation v1 (`recommendation`, `comments`,
 * `total_score`, and `recruitment_stage_id` per EV-2) — item-set mutation is
 * out of scope (see the frozen contract's PATCH-scope amendment note).
 */
final class UpdateEvaluation
{
    private const TERMINAL_STATUSES = ['HIRED', 'REJECTED', 'WITHDRAWN', 'NO_SHOW'];

    public function __construct(private readonly AuditWriter $audit) {}

    /** @param array<string, mixed> $attributes */
    public function execute(User $actor, Evaluation $evaluation, array $attributes): Evaluation
    {
        return DB::transaction(function () use ($actor, $evaluation, $attributes): Evaluation {
            /** @var Evaluation $locked */
            $locked = Evaluation::query()->whereKey($evaluation->getKey())->lockForUpdate()->firstOrFail();

            if ((int) $locked->evaluator_user_id !== (int) $actor->getKey()) {
                throw new EvaluationNotOwned();
            }

            if ($locked->submitted_at !== null) {
                throw new EvaluationAlreadySubmitted();
            }

            /** @var Application $lockedApplication */
            $lockedApplication = Application::query()->whereKey($locked->application_id)->lockForUpdate()->firstOrFail();

            if (in_array($lockedApplication->current_status?->value, self::TERMINAL_STATUSES, true)) {
                throw new ApplicationTerminal();
            }

            /** @var Vacancy $vacancy */
            $vacancy = Vacancy::query()->whereKey($lockedApplication->vacancy_id)->lockForUpdate()->firstOrFail();

            $stageId = array_key_exists('recruitment_stage_id', $attributes)
                ? (int) $attributes['recruitment_stage_id']
                : (int) $locked->recruitment_stage_id;

            /** @var RecruitmentStage|null $stage */
            $stage = RecruitmentStage::query()->where('vacancy_id', $vacancy->getKey())
                ->whereKey($stageId)->lockForUpdate()->first();
            if ($stage === null) {
                throw new RecruitmentStageNotInVacancy();
            }

            /** @var Company $company */
            $company = Company::query()->whereKey($vacancy->company_id)->lockForUpdate()->firstOrFail();

            ApplicationProcessingGate::assertProcessable($company, $vacancy);

            if (! $stage->active) {
                throw new EvaluationStageTargetInactive();
            }

            $locked->forceFill([
                'recruitment_stage_id' => $stage->getKey(),
                'recommendation' => array_key_exists('recommendation', $attributes) ? $attributes['recommendation'] : $locked->recommendation,
                'comments' => array_key_exists('comments', $attributes) ? $attributes['comments'] : $locked->comments,
                'total_score' => array_key_exists('total_score', $attributes) ? $attributes['total_score'] : $locked->total_score,
                'updated_at' => now(),
            ])->save();

            $this->audit->record('evaluation_updated', $actor, 'evaluation', (int) $locked->getKey(), [
                'recruitment_stage_id' => $stage->getKey(),
            ]);

            return $locked->refresh()->load('items');
        });
    }
}
