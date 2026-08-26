<?php

declare(strict_types=1);

namespace App\Domains\Application\Actions;

use App\Domains\Application\Enums\ApplicationEventType;
use App\Domains\Application\Exceptions\ApplicationStageAlreadyCurrent;
use App\Domains\Application\Exceptions\ApplicationStageTargetInactive;
use App\Domains\Application\Exceptions\ApplicationTerminal;
use App\Domains\Application\Models\Application;
use App\Domains\Application\Support\ApplicationProcessingGate;
use App\Domains\Application\Support\RecruiterApplicationNotifier;
use App\Domains\Company\Models\Company;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\AuditWriter;
use App\Domains\Vacancy\Exceptions\RecruitmentStageNotInVacancy;
use App\Domains\Vacancy\Models\RecruitmentStage;
use App\Domains\Vacancy\Models\Vacancy;
use Illuminate\Support\Facades\DB;

/**
 * `POST /applications/{application}/move-stage` — Application Stage
 * Movement Foundation v1 (MS-3, MS-4, both approved and CLOSED).
 *
 * Lock order extends `TransitionApplication`'s established
 * application → vacancy → company chain by one row, inserted where
 * `SaveRecruitmentStage`/`ReorderRecruitmentStages` already lock a stage:
 * application → vacancy → stage → company. This can never deadlock against
 * a concurrent transition, submit, or stage-authoring write, since every
 * writer that touches more than one of these four row types locks them in
 * this same relative order.
 *
 * Status-independent by design (FR-APP-004, FR-HR-007): `current_status` is
 * never read or written here. Terminal eligibility is checked against the
 * full FSD §8.5 terminal set, not `ApplicationTransitionGraph`'s narrower
 * Foundation-v1-reachable subset — a HIRED or NO_SHOW application (even
 * though neither is reachable via `/transition` yet) must not be movable.
 */
final class MoveApplicationStage
{
    private const TERMINAL_STATUSES = ['HIRED', 'REJECTED', 'WITHDRAWN', 'NO_SHOW'];

    public function __construct(
        private readonly AuditWriter $audit,
        private readonly RecruiterApplicationNotifier $notifier,
    ) {}

    public function execute(
        User $actor,
        Application $application,
        int $toStageId,
        string $candidateVisibility,
        ?string $reason,
    ): Application {
        return DB::transaction(function () use ($actor, $application, $toStageId, $candidateVisibility, $reason): Application {
            /** @var Application $locked */
            $locked = Application::query()->whereKey($application->getKey())->lockForUpdate()->firstOrFail();

            if (in_array($locked->current_status?->value, self::TERMINAL_STATUSES, true)) {
                throw new ApplicationTerminal();
            }

            /** @var Vacancy $vacancy */
            $vacancy = Vacancy::query()->whereKey($locked->vacancy_id)->lockForUpdate()->firstOrFail();

            /** @var RecruitmentStage|null $target */
            $target = RecruitmentStage::query()->where('vacancy_id', $vacancy->getKey())
                ->whereKey($toStageId)->lockForUpdate()->first();
            if ($target === null) {
                throw new RecruitmentStageNotInVacancy();
            }

            /** @var Company $company */
            $company = Company::query()->whereKey($vacancy->company_id)->lockForUpdate()->firstOrFail();

            ApplicationProcessingGate::assertProcessable($company, $vacancy);

            if (! $target->active) {
                throw new ApplicationStageTargetInactive();
            }

            $fromStageId = $locked->current_stage_id;
            if ($fromStageId !== null && (int) $fromStageId === (int) $target->getKey()) {
                throw new ApplicationStageAlreadyCurrent();
            }

            $now = now();
            $locked->current_stage_id = $target->getKey();
            $locked->updated_at = $now;
            $locked->save();

            $locked->statusHistories()->forceCreate([
                'from_stage_id' => $fromStageId,
                'to_stage_id' => $target->getKey(),
                'event_type' => ApplicationEventType::StageChanged->value,
                'actor_user_id' => $actor->getKey(),
                'reason' => $reason,
                'candidate_visibility' => $candidateVisibility,
                'occurred_at' => $now,
            ]);

            $this->audit->record('application_stage_changed', $actor, 'application', (int) $locked->getKey(), [
                'from_stage_id' => $fromStageId,
                'to_stage_id' => (int) $target->getKey(),
            ]);

            if ($candidateVisibility === 'VISIBLE') {
                $this->notifier->stageMoved($locked, $target);
            }

            return $locked->refresh();
        });
    }
}
