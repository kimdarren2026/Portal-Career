<?php

declare(strict_types=1);

namespace App\Domains\Recruitment\SelectionSchedule\Actions;

use App\Domains\Application\Exceptions\ApplicationTerminal;
use App\Domains\Application\Models\Application;
use App\Domains\Application\Support\ApplicationProcessingGate;
use App\Domains\Company\Models\Company;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\AuditWriter;
use App\Domains\Recruitment\SelectionSchedule\Enums\SelectionScheduleEventType;
use App\Domains\Recruitment\SelectionSchedule\Enums\SelectionScheduleStatus;
use App\Domains\Recruitment\SelectionSchedule\Exceptions\ScheduleInvalidTransition;
use App\Domains\Recruitment\SelectionSchedule\Exceptions\ScheduleMethodDetailRequired;
use App\Domains\Recruitment\SelectionSchedule\Exceptions\ScheduleTargetStageInactive;
use App\Domains\Recruitment\SelectionSchedule\Exceptions\ScheduleTimeInvalid;
use App\Domains\Recruitment\SelectionSchedule\Exceptions\SelectionScheduleStaleVersion;
use App\Domains\Recruitment\SelectionSchedule\Models\SelectionSchedule;
use App\Domains\Recruitment\SelectionSchedule\Support\SelectionScheduleNotifier;
use App\Domains\Vacancy\Models\RecruitmentStage;
use App\Domains\Vacancy\Models\Vacancy;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * `PATCH /schedules/{schedule}` — Selection Schedule Foundation v1
 * (SS-1, SS-3, SS-5, SS-8, all approved and CLOSED).
 *
 * The schedule row is the primary contended resource for this write (many
 * concurrent reschedule attempts target the SAME schedule), so it is locked
 * first — mirroring `revision_number`/`If-Match` being checked immediately
 * after that lock, the same relative position `TransitionApplication` uses
 * for its own version check. Application → vacancy → stage → company follow
 * in the same relative order `MoveApplicationStage`/`CreateSelectionSchedule`
 * already established, so this can never deadlock against them.
 *
 * `recruitment_stage_id` is never accepted here — reschedule changes time
 * and logistics only, never the schedule's stage (its own frozen contract).
 */
final class RescheduleSelectionSchedule
{
    private const TERMINAL_STATUSES = ['HIRED', 'REJECTED', 'WITHDRAWN', 'NO_SHOW'];

    public function __construct(
        private readonly AuditWriter $audit,
        private readonly SelectionScheduleNotifier $notifier,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function execute(User $actor, SelectionSchedule $schedule, array $attributes, ?int $expectedRevision): SelectionSchedule
    {
        return DB::transaction(function () use ($actor, $schedule, $attributes, $expectedRevision): SelectionSchedule {
            /** @var SelectionSchedule $locked */
            $locked = SelectionSchedule::query()->whereKey($schedule->getKey())->lockForUpdate()->firstOrFail();

            if ($expectedRevision !== null && $expectedRevision !== (int) $locked->revision_number) {
                throw new SelectionScheduleStaleVersion();
            }

            if ($locked->status !== SelectionScheduleStatus::Scheduled) {
                throw new ScheduleInvalidTransition();
            }

            /** @var Application $lockedApplication */
            $lockedApplication = Application::query()->whereKey($locked->application_id)->lockForUpdate()->firstOrFail();

            if (in_array($lockedApplication->current_status?->value, self::TERMINAL_STATUSES, true)) {
                throw new ApplicationTerminal();
            }

            /** @var Vacancy $vacancy */
            $vacancy = Vacancy::query()->whereKey($lockedApplication->vacancy_id)->lockForUpdate()->firstOrFail();

            /** @var RecruitmentStage $stage */
            $stage = RecruitmentStage::query()->whereKey($locked->recruitment_stage_id)->lockForUpdate()->firstOrFail();

            /** @var ?Company $company */
            $company = $vacancy->company_id === null ? null : Company::query()->whereKey($vacancy->company_id)->lockForUpdate()->firstOrFail(); // CAMPUS vacancies have no company (FR-HR-001)

            ApplicationProcessingGate::assertProcessable($company, $vacancy);

            if (! $stage->active) {
                throw new ScheduleTargetStageInactive();
            }

            $now = now();
            $startsAt = isset($attributes['starts_at']) ? Carbon::parse($attributes['starts_at']) : $locked->starts_at;
            $endsAt = array_key_exists('ends_at', $attributes)
                ? ($attributes['ends_at'] !== null ? Carbon::parse($attributes['ends_at']) : null)
                : $locked->ends_at;

            if ($startsAt->lessThanOrEqualTo($now)) {
                throw new ScheduleTimeInvalid();
            }
            if ($endsAt !== null && $endsAt->lessThanOrEqualTo($startsAt)) {
                throw new ScheduleTimeInvalid();
            }

            $method = $attributes['method'] ?? $locked->method;
            $location = array_key_exists('location', $attributes) ? $attributes['location'] : $locked->location;
            $meetingUrl = array_key_exists('meeting_url', $attributes) ? $attributes['meeting_url'] : $locked->meeting_url;
            if ($method === 'ONLINE' && ($meetingUrl === null || $meetingUrl === '')) {
                throw new ScheduleMethodDetailRequired();
            }
            if ($method === 'ON_SITE' && ($location === null || $location === '')) {
                throw new ScheduleMethodDetailRequired();
            }

            $previousSnapshot = [
                'starts_at' => $locked->starts_at?->toIso8601String(),
                'ends_at' => $locked->ends_at?->toIso8601String(),
                'timezone' => $locked->timezone,
                'method' => $locked->method,
                'location' => $locked->location,
                'meeting_url' => $locked->meeting_url,
                'pic_user_id' => $locked->pic_user_id,
                'instructions' => $locked->instructions,
            ];

            $newRevision = (int) $locked->revision_number + 1;
            $locked->forceFill([
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'timezone' => $attributes['timezone'] ?? $locked->timezone,
                'method' => $method,
                'location' => $location,
                'meeting_url' => $meetingUrl,
                'pic_user_id' => array_key_exists('pic_user_id', $attributes) ? $attributes['pic_user_id'] : $locked->pic_user_id,
                'instructions' => array_key_exists('instructions', $attributes) ? $attributes['instructions'] : $locked->instructions,
                'revision_number' => $newRevision,
                'updated_at' => $now,
            ])->save();

            $locked->histories()->forceCreate([
                'event_type' => SelectionScheduleEventType::Rescheduled->value,
                'previous_snapshot' => $previousSnapshot,
                'resulting_revision_number' => $newRevision,
                'actor_user_id' => $actor->getKey(),
                'reason' => $attributes['reason'] ?? null,
                'occurred_at' => $now,
            ]);

            $this->audit->record('schedule_updated', $actor, 'selection_schedule', (int) $locked->getKey(), [
                'revision_number' => $newRevision,
            ]);

            $locked->refresh();
            $this->notifier->rescheduled($locked);

            return $locked;
        });
    }
}
