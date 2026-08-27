<?php

declare(strict_types=1);

namespace App\Domains\Recruitment\Outcome\Actions;

use App\Domains\Application\Models\Application;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\AuditWriter;
use App\Domains\Recruitment\Outcome\Exceptions\OutcomeAlreadyRecorded;
use App\Domains\Recruitment\Outcome\Models\RecruitmentOutcome;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * `POST /recruitment-outcomes` — Recruitment Outcome Foundation v1
 * (OC-1, RC-2, approved and CLOSED). `INTERNAL_APPLICATION` only.
 *
 * The application row is locked first (the same deterministic
 * application-first order every other Recruitment writer in this codebase
 * uses), which serializes two concurrent creates for the same application —
 * the second observes the first's committed row and raises
 * `OutcomeAlreadyRecorded` itself, never relying on the database exception
 * alone. `uq_recruitment_outcomes_application` remains the backstop: a
 * `23505` from the insert is still caught and mapped, never surfaced as a
 * raw SQLSTATE.
 *
 * No terminal-application gate, no RA-2 processing gate, and no offer
 * dependency — outcome recording is explicit reporting, not a lifecycle or
 * offer side effect (see the create contract's amendment notes).
 * `applications.current_status`/`current_stage_id` are never read or
 * written here.
 */
final class CreateRecruitmentOutcome
{
    public function __construct(private readonly AuditWriter $audit) {}

    /** @param array<string, mixed> $attributes */
    public function execute(User $actor, Application $application, array $attributes): RecruitmentOutcome
    {
        return DB::transaction(function () use ($actor, $application, $attributes): RecruitmentOutcome {
            /** @var Application $lockedApplication */
            $lockedApplication = Application::query()->whereKey($application->getKey())->lockForUpdate()->firstOrFail();

            $exists = RecruitmentOutcome::query()->where('application_id', $lockedApplication->getKey())->exists();
            if ($exists) {
                throw new OutcomeAlreadyRecorded();
            }

            $now = now();

            try {
                $outcome = new RecruitmentOutcome();
                $outcome->forceFill([
                    'source_type' => 'INTERNAL_APPLICATION',
                    'application_id' => $lockedApplication->getKey(),
                    'external_apply_event_id' => null,
                    'outcome' => $attributes['outcome'],
                    'reported_by_source' => $attributes['reported_by_source'],
                    'confirmed_by' => $actor->getKey(),
                    'confirmed_at' => $now,
                    'notes' => $attributes['notes'] ?? null,
                    'created_at' => $now,
                ])->save();
            } catch (QueryException $exception) {
                // uq_recruitment_outcomes_application (INV-022 backstop). SQLSTATE
                // 23505 must surface as 409, never as a 500.
                if ($exception->getCode() === '23505') {
                    throw new OutcomeAlreadyRecorded();
                }

                throw $exception;
            }

            $this->audit->record('recruitment_outcome_recorded', $actor, 'recruitment_outcome', (int) $outcome->getKey(), [
                'application_id' => (int) $lockedApplication->getKey(),
                'outcome' => $outcome->outcome,
            ]);

            return $outcome->refresh();
        });
    }
}
