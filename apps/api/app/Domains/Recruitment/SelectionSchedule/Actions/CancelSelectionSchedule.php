<?php

declare(strict_types=1);

namespace App\Domains\Recruitment\SelectionSchedule\Actions;

use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\AuditWriter;
use App\Domains\Recruitment\SelectionSchedule\Enums\SelectionScheduleEventType;
use App\Domains\Recruitment\SelectionSchedule\Enums\SelectionScheduleStatus;
use App\Domains\Recruitment\SelectionSchedule\Exceptions\ScheduleInvalidTransition;
use App\Domains\Recruitment\SelectionSchedule\Models\SelectionSchedule;
use App\Domains\Recruitment\SelectionSchedule\Support\SelectionScheduleNotifier;
use Illuminate\Support\Facades\DB;

/**
 * `POST /schedules/{schedule}/cancel` — Selection Schedule Foundation v1
 * (SS-1, SS-3, SS-8, all approved and CLOSED).
 *
 * SS-1: permitted even when the parent application is terminal — deliberate
 * administrative cleanup, so a stale `SCHEDULED` record is never stranded.
 * SS-8: RA-2 does not apply — `ApplicationProcessingGate` is never called.
 * SS-3: permitted even when the referenced stage is inactive.
 *
 * Only the schedule row itself is locked — no application/vacancy/company
 * state is read or required, by design (SS-1, SS-3, SS-8 all narrow this
 * operation to schedule-row-only concerns).
 */
final class CancelSelectionSchedule
{
    public function __construct(
        private readonly AuditWriter $audit,
        private readonly SelectionScheduleNotifier $notifier,
    ) {}

    public function execute(User $actor, SelectionSchedule $schedule, ?string $reason): SelectionSchedule
    {
        return DB::transaction(function () use ($actor, $schedule, $reason): SelectionSchedule {
            /** @var SelectionSchedule $locked */
            $locked = SelectionSchedule::query()->whereKey($schedule->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status !== SelectionScheduleStatus::Scheduled) {
                throw new ScheduleInvalidTransition();
            }

            $now = now();
            $locked->status = SelectionScheduleStatus::Cancelled->value;
            $locked->updated_at = $now;
            $locked->save();

            $locked->histories()->forceCreate([
                'event_type' => SelectionScheduleEventType::Cancelled->value,
                'previous_snapshot' => null,
                'resulting_revision_number' => (int) $locked->revision_number,
                'actor_user_id' => $actor->getKey(),
                'reason' => $reason,
                'occurred_at' => $now,
            ]);

            $this->audit->record('schedule_cancelled', $actor, 'selection_schedule', (int) $locked->getKey(), []);

            $locked->refresh();
            $this->notifier->cancelled($locked);

            return $locked;
        });
    }
}
