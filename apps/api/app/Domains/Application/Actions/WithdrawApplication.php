<?php

declare(strict_types=1);

namespace App\Domains\Application\Actions;

use App\Domains\Application\Enums\ApplicationEventType;
use App\Domains\Application\Enums\ApplicationStatus;
use App\Domains\Application\Exceptions\ApplicationAlreadyWithdrawn;
use App\Domains\Application\Models\Application;
use App\Domains\Application\Support\ApplicationNotifier;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\AuditWriter;
use Illuminate\Support\Facades\DB;

/**
 * `POST /applications/{application}/withdraw` (FR-APP-006). Candidate's own
 * action only. Nothing is deleted (INV-009) — the application, its history,
 * consent, document shares/snapshots, and answers all remain exactly as they
 * were; only `current_status`, `withdrawn_at`, and `withdrawal_reason` change.
 */
final class WithdrawApplication
{
    public function __construct(
        private readonly AuditWriter $audit,
        private readonly ApplicationNotifier $notifier,
    ) {}

    public function execute(User $actor, Application $application, ?string $reason): Application
    {
        return DB::transaction(function () use ($actor, $application, $reason): Application {
            /** @var Application $locked */
            $locked = Application::query()->whereKey($application->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->current_status === ApplicationStatus::Withdrawn) {
                throw new ApplicationAlreadyWithdrawn();
            }

            $now = now();
            $from = $locked->current_status;

            $locked->current_status = ApplicationStatus::Withdrawn;
            $locked->withdrawn_at = $now;
            $locked->withdrawal_reason = $reason;
            $locked->updated_at = $now;
            $locked->save();

            $locked->statusHistories()->forceCreate([
                'from_status' => $from?->value,
                'to_status' => ApplicationStatus::Withdrawn->value,
                'event_type' => ApplicationEventType::Withdrawn->value,
                'actor_user_id' => $actor->getKey(),
                'reason' => $reason,
                'candidate_visibility' => 'VISIBLE',
                'occurred_at' => $now,
            ]);

            $this->audit->record('application_withdrawn', $actor, 'application', (int) $locked->getKey(), [
                'from_status' => $from?->value,
            ]);

            $vacancyRow = $locked->vacancy()->first(['company_id', 'created_by']);
            $companyId = $vacancyRow?->company_id === null ? null : (int) $vacancyRow->company_id;
            $this->notifier->withdrawn(
                $locked,
                $actor,
                $companyId,
                $companyId === null ? (int) ($vacancyRow?->created_by) : null,
            );

            return $locked->refresh();
        });
    }
}
