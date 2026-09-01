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
use App\Domains\Recruitment\SelectionSchedule\Exceptions\ScheduleMethodDetailRequired;
use App\Domains\Recruitment\SelectionSchedule\Exceptions\ScheduleTargetStageInactive;
use App\Domains\Recruitment\SelectionSchedule\Exceptions\ScheduleTimeInvalid;
use App\Domains\Recruitment\SelectionSchedule\Models\SelectionSchedule;
use App\Domains\Recruitment\SelectionSchedule\Support\SelectionScheduleNotifier;
use App\Domains\Vacancy\Exceptions\RecruitmentStageNotInVacancy;
use App\Domains\Vacancy\Models\RecruitmentStage;
use App\Domains\Vacancy\Models\Vacancy;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * `POST /applications/{application}/schedules` — Selection Schedule
 * Foundation v1 (SS-1, SS-2, SS-3, SS-5, SS-8, all approved and CLOSED).
 *
 * Lock order matches `MoveApplicationStage`'s proven
 * application → vacancy → stage → company chain, extended by inserting the
 * new schedule row last (it does not exist to lock until created). This can
 * never deadlock against a concurrent transition, move-stage, submit, or
 * stage-authoring write, since every writer touching more than one of these
 * row types locks them in this same relative order.
 *
 * Status- and stage-position-independent by design (SS-2): `current_status`
 * and `current_stage_id` are never read or written here.
 */
final class CreateSelectionSchedule
{
    private const TERMINAL_STATUSES = ['HIRED', 'REJECTED', 'WITHDRAWN', 'NO_SHOW'];

    public function __construct(
        private readonly AuditWriter $audit,
        private readonly SelectionScheduleNotifier $notifier,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function execute(User $actor, Application $application, array $attributes): SelectionSchedule
    {
        return DB::transaction(function () use ($actor, $application, $attributes): SelectionSchedule {
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

            /** @var ?Company $company */
            $company = $vacancy->company_id === null ? null : Company::query()->whereKey($vacancy->company_id)->lockForUpdate()->firstOrFail(); // CAMPUS vacancies have no company (FR-HR-001)

            ApplicationProcessingGate::assertProcessable($company, $vacancy);

            if (! $stage->active) {
                throw new ScheduleTargetStageInactive();
            }

            $now = now();
            $startsAt = Carbon::parse($attributes['starts_at']);
            $endsAt = isset($attributes['ends_at']) && $attributes['ends_at'] !== null ? Carbon::parse($attributes['ends_at']) : null;

            if ($startsAt->lessThanOrEqualTo($now)) {
                throw new ScheduleTimeInvalid();
            }
            if ($endsAt !== null && $endsAt->lessThanOrEqualTo($startsAt)) {
                throw new ScheduleTimeInvalid();
            }

            $method = $attributes['method'];
            $location = $attributes['location'] ?? null;
            $meetingUrl = $attributes['meeting_url'] ?? null;
            if ($method === 'ONLINE' && ($meetingUrl === null || $meetingUrl === '')) {
                throw new ScheduleMethodDetailRequired();
            }
            if ($method === 'ON_SITE' && ($location === null || $location === '')) {
                throw new ScheduleMethodDetailRequired();
            }

            $schedule = new SelectionSchedule();
            $schedule->forceFill([
                'application_id' => $lockedApplication->getKey(),
                'recruitment_stage_id' => $stage->getKey(),
                'selection_type' => $attributes['selection_type'],
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'timezone' => $attributes['timezone'],
                'method' => $attributes['method'],
                'location' => $attributes['location'] ?? null,
                'meeting_url' => $attributes['meeting_url'] ?? null,
                'pic_user_id' => $attributes['pic_user_id'] ?? null,
                'instructions' => $attributes['instructions'] ?? null,
                'attachment_storage_reference' => null,
                'status' => SelectionScheduleStatus::Scheduled->value,
                'revision_number' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ])->save();

            $schedule->histories()->forceCreate([
                'event_type' => SelectionScheduleEventType::Created->value,
                'previous_snapshot' => null,
                'resulting_revision_number' => 0,
                'actor_user_id' => $actor->getKey(),
                'reason' => null,
                'occurred_at' => $now,
            ]);

            $this->audit->record('schedule_created', $actor, 'selection_schedule', (int) $schedule->getKey(), [
                'application_id' => (int) $lockedApplication->getKey(),
                'recruitment_stage_id' => (int) $stage->getKey(),
            ]);

            $schedule->refresh();
            $this->notifier->created($schedule);

            return $schedule;
        });
    }
}
