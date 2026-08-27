<?php

declare(strict_types=1);

namespace App\Domains\Recruitment\Evaluation\Actions;

use App\Domains\Application\Exceptions\ApplicationTerminal;
use App\Domains\Application\Models\Application;
use App\Domains\Application\Support\ApplicationProcessingGate;
use App\Domains\Company\Models\Company;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\AuditWriter;
use App\Domains\Recruitment\Evaluation\Exceptions\EvaluationStageTargetInactive;
use App\Domains\Recruitment\Evaluation\Models\Evaluation;
use App\Domains\Vacancy\Exceptions\RecruitmentStageNotInVacancy;
use App\Domains\Vacancy\Models\RecruitmentStage;
use App\Domains\Vacancy\Models\Vacancy;
use Illuminate\Support\Facades\DB;

/**
 * `POST /applications/{application}/evaluations` — Evaluation / Scoring
 * Foundation v1 (EV-1, EV-2, RC-1, all approved and CLOSED).
 *
 * Lock order matches `CreateSelectionSchedule`'s proven
 * application → vacancy → stage → company chain, extended by inserting the
 * new evaluation (and its items) last. This can never deadlock against a
 * concurrent transition, move-stage, schedule write, or stage-authoring
 * write, since every writer touching more than one of these row types locks
 * them in this same relative order.
 *
 * Status- and stage-position-independent by design (EV-2): `current_status`
 * and `current_stage_id` are never read or written here. No scoring formula
 * is computed — `total_score`, `weight`, and `score` are persisted exactly
 * as supplied, or left `null`.
 */
final class CreateEvaluation
{
    private const TERMINAL_STATUSES = ['HIRED', 'REJECTED', 'WITHDRAWN', 'NO_SHOW'];

    public function __construct(private readonly AuditWriter $audit) {}

    /** @param array<string, mixed> $attributes */
    public function execute(User $actor, Application $application, array $attributes): Evaluation
    {
        return DB::transaction(function () use ($actor, $application, $attributes): Evaluation {
            /** @var Application $lockedApplication */
            $lockedApplication = Application::query()->whereKey($application->getKey())->lockForUpdate()->firstOrFail();

            if (in_array($lockedApplication->current_status?->value, self::TERMINAL_STATUSES, true)) {
                throw new ApplicationTerminal();
            }

            /** @var Vacancy $vacancy */
            $vacancy = Vacancy::query()->whereKey($lockedApplication->vacancy_id)->lockForUpdate()->firstOrFail();

            /** @var RecruitmentStage|null $stage */
            $stage = RecruitmentStage::query()->where('vacancy_id', $vacancy->getKey())
                ->whereKey((int) $attributes['recruitment_stage_id'])->lockForUpdate()->first();
            if ($stage === null) {
                throw new RecruitmentStageNotInVacancy();
            }

            /** @var Company $company */
            $company = Company::query()->whereKey($vacancy->company_id)->lockForUpdate()->firstOrFail();

            ApplicationProcessingGate::assertProcessable($company, $vacancy);

            if (! $stage->active) {
                throw new EvaluationStageTargetInactive();
            }

            $now = now();
            $evaluation = new Evaluation();
            $evaluation->forceFill([
                'application_id' => $lockedApplication->getKey(),
                'recruitment_stage_id' => $stage->getKey(),
                'evaluator_user_id' => $actor->getKey(),
                'recommendation' => $attributes['recommendation'] ?? null,
                'comments' => $attributes['comments'] ?? null,
                'total_score' => $attributes['total_score'] ?? null,
                'submitted_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ])->save();

            foreach ($attributes['items'] ?? [] as $item) {
                $evaluation->items()->create([
                    'criterion' => $item['criterion'],
                    'weight' => $item['weight'] ?? null,
                    'score' => $item['score'] ?? null,
                    'comment' => $item['comment'] ?? null,
                    'sort_order' => $item['sort_order'],
                ]);
            }

            $this->audit->record('evaluation_created', $actor, 'evaluation', (int) $evaluation->getKey(), [
                'application_id' => (int) $lockedApplication->getKey(),
                'recruitment_stage_id' => (int) $stage->getKey(),
            ]);

            return $evaluation->refresh()->load('items');
        });
    }
}
