<?php

declare(strict_types=1);

namespace App\Domains\Application\Actions;

use App\Domains\Application\Enums\ApplicationEventType;
use App\Domains\Application\Enums\ApplicationStatus;
use App\Domains\Application\Exceptions\ApplicationStaleVersion;
use App\Domains\Application\Models\Application;
use App\Domains\Application\Support\ApplicationProcessingGate;
use App\Domains\Application\Support\ApplicationTransitionGraph;
use App\Domains\Application\Support\RecruiterApplicationNotifier;
use App\Domains\Company\Models\Company;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\AuditWriter;
use App\Domains\Vacancy\Models\Vacancy;
use Illuminate\Support\Facades\DB;

/**
 * `POST /applications/{application}/transition` — Recruiter Applicant
 * Management Foundation v1 (RA-1, RA-2, both approved and CLOSED).
 *
 * Lock order is deterministic and matches `SubmitApplication`'s established
 * pattern extended by one row: application, then vacancy, then company — so
 * this action can never deadlock against a concurrent submit (which locks
 * vacancy then company) or another concurrent transition on the same
 * application. RA-1's edge legality and RA-2's processing gate are both
 * rechecked against freshly locked rows, never against data a caller read
 * before this transaction opened.
 */
final class TransitionApplication
{
    public function __construct(
        private readonly AuditWriter $audit,
        private readonly RecruiterApplicationNotifier $notifier,
    ) {}

    public function execute(
        User $actor,
        Application $application,
        ApplicationStatus $toStatus,
        string $candidateVisibility,
        ?string $candidateVisibleNote,
        ?string $reason,
        ?int $expectedVersion,
    ): Application {
        return DB::transaction(function () use ($actor, $application, $toStatus, $candidateVisibility, $candidateVisibleNote, $reason, $expectedVersion): Application {
            /** @var Application $locked */
            $locked = Application::query()->whereKey($application->getKey())->lockForUpdate()->firstOrFail();

            $currentVersion = (int) $locked->statusHistories()->count();
            if ($expectedVersion !== null && $expectedVersion !== $currentVersion) {
                throw new ApplicationStaleVersion();
            }

            /** @var Vacancy $vacancy */
            $vacancy = Vacancy::query()->whereKey($locked->vacancy_id)->lockForUpdate()->firstOrFail();
            /** @var Company $company */
            $company = Company::query()->whereKey($vacancy->company_id)->lockForUpdate()->firstOrFail();

            $from = $locked->current_status;
            ApplicationTransitionGraph::assertLegal($from, $toStatus);
            ApplicationProcessingGate::assertProcessable($company, $vacancy);

            $now = now();
            $locked->current_status = $toStatus;
            $locked->updated_at = $now;
            $locked->save();

            $eventType = $toStatus === ApplicationStatus::Rejected
                ? ApplicationEventType::Rejected
                : ApplicationEventType::StatusChanged;

            $locked->statusHistories()->forceCreate([
                'from_status' => $from->value,
                'to_status' => $toStatus->value,
                'event_type' => $eventType->value,
                'actor_user_id' => $actor->getKey(),
                'reason' => $reason,
                'candidate_visibility' => $candidateVisibility,
                'candidate_visible_note' => $candidateVisibleNote,
                'occurred_at' => $now,
            ]);

            $this->audit->record('application_status_changed', $actor, 'application', (int) $locked->getKey(), [
                'from_status' => $from->value,
                'to_status' => $toStatus->value,
            ]);

            if ($candidateVisibility === 'VISIBLE') {
                $this->notifier->transitioned($locked, $candidateVisibleNote);
            }

            return $locked->refresh();
        });
    }
}
