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
use App\Domains\Recruitment\Evaluation\Models\Evaluation;
use App\Domains\Recruitment\Evaluation\Support\EvaluationNotifier;
use App\Domains\Vacancy\Models\Vacancy;
use Illuminate\Support\Facades\DB;

/**
 * `POST /evaluations/{evaluation}/submit` — Evaluation / Scoring
 * Foundation v1 (EV-1, EV-2, RC-1, all approved and CLOSED).
 *
 * The evaluation row is locked first (the primary contended resource for a
 * double-submit race), then application → vacancy → company follow in the
 * same relative order every other Action in this domain uses. **No stage
 * lock or active-state recheck** — EV-2 explicitly exempts submit from the
 * active-stage requirement, so the referenced stage's state is irrelevant
 * here and locking it would serve no purpose.
 */
final class SubmitEvaluation
{
    private const TERMINAL_STATUSES = ['HIRED', 'REJECTED', 'WITHDRAWN', 'NO_SHOW'];

    public function __construct(
        private readonly AuditWriter $audit,
        private readonly EvaluationNotifier $notifier,
    ) {}

    public function execute(User $actor, Evaluation $evaluation): Evaluation
    {
        return DB::transaction(function () use ($actor, $evaluation): Evaluation {
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

            /** @var ?Company $company */
            $company = $vacancy->company_id === null ? null : Company::query()->whereKey($vacancy->company_id)->lockForUpdate()->firstOrFail(); // CAMPUS vacancies have no company (FR-HR-001)

            ApplicationProcessingGate::assertProcessable($company, $vacancy);

            $locked->submitted_at = now();
            $locked->updated_at = now();
            $locked->save();

            $this->audit->record('evaluation_submitted', $actor, 'evaluation', (int) $locked->getKey(), []);

            $locked->refresh();
            // A CAMPUS vacancy has no owning company — the owner-side
            // evaluation notification has no company member set to reach; the
            // candidate is not an evaluation recipient by contract either.
            $this->notifier->submitted($locked, $company !== null ? (int) $company->getKey() : 0);

            return $locked->load('items');
        });
    }
}
