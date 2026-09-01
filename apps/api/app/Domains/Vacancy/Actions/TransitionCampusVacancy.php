<?php

declare(strict_types=1);

namespace App\Domains\Vacancy\Actions;

use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\AuditWriter;
use App\Domains\Vacancy\Enums\CampusVacancyAction;
use App\Domains\Vacancy\Enums\VacancyStatus;
use App\Domains\Vacancy\Exceptions\VacancyDatesRequired;
use App\Domains\Vacancy\Exceptions\VacancyInvalidTransition;
use App\Domains\Vacancy\Exceptions\VacancyModerationNotApplicable;
use App\Domains\Vacancy\Models\Vacancy;
use Illuminate\Support\Facades\DB;

/**
 * The campus vacancy lifecycle (FSD §8.4, FR-HR-004), each in one transaction
 * under the vacancy row lock: ownership check → source-status check →
 * transition → audit.
 *
 * Campus vacancies are NEVER moderated — there is no `vacancy_moderation_reviews`
 * row and no Career Center involvement. The legal transitions are exactly:
 *   - publish:  DRAFT / SCHEDULED → PUBLISHED   (dates required; sets published_at once)
 *   - schedule: DRAFT             → SCHEDULED    (dates required)
 *   - close:    PUBLISHED         → CLOSED       (sets closed_at)
 *   - suspend:  PUBLISHED         → SUSPENDED    (sets suspended_at)
 *   - restore:  SUSPENDED         → PUBLISHED, or → CLOSED when now >= close_at
 *               (mirrors the frozen B-2 restore resolution; published_at kept — INV-013)
 *
 * `published_at` is the Time-to-Fill anchor and is written exactly once
 * (INV-013). No notification is queued: FR-NOTIF-002 does not list campus
 * vacancy lifecycle as a trigger, and `VacancyLifecycleNotifier` resolves its
 * recipients through `company_members`, which a campus vacancy has none of.
 */
final class TransitionCampusVacancy
{
    public function __construct(private readonly AuditWriter $audit) {}

    public function execute(User $actor, Vacancy $vacancy, CampusVacancyAction $action): Vacancy
    {
        return DB::transaction(function () use ($actor, $vacancy, $action): Vacancy {
            /** @var Vacancy $locked */
            $locked = Vacancy::query()->whereKey($vacancy->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->ownership_type !== 'CAMPUS') {
                throw new VacancyModerationNotApplicable('VACANCY_MODERATION_NOT_APPLICABLE');
            }

            $from = $locked->current_status;
            $to = $this->target($locked, $action);

            $locked->current_status = $to;
            if ($to === VacancyStatus::Published && $locked->published_at === null) {
                $locked->published_at = now();
            }
            if ($to === VacancyStatus::Closed) {
                $locked->closed_at = now();
            }
            if ($to === VacancyStatus::Suspended) {
                $locked->suspended_at = now();
            }
            if ($action === CampusVacancyAction::Restore) {
                $locked->suspended_at = null;
            }
            $locked->updated_at = now();
            $locked->save();

            $this->audit->record($action->auditAction(), $actor, 'vacancy', (int) $locked->getKey(), [
                'from_status' => $from->value,
                'to_status' => $to->value,
            ]);

            return $locked->refresh();
        });
    }

    private function target(Vacancy $vacancy, CampusVacancyAction $action): VacancyStatus
    {
        $from = $vacancy->current_status;

        return match ($action) {
            CampusVacancyAction::Publish => $this->publishTarget($vacancy, $from),
            CampusVacancyAction::Schedule => $this->scheduleTarget($vacancy, $from),
            CampusVacancyAction::Close => $this->requireFrom($from, [VacancyStatus::Published], VacancyStatus::Closed),
            CampusVacancyAction::Suspend => $this->requireFrom($from, [VacancyStatus::Published], VacancyStatus::Suspended),
            CampusVacancyAction::Restore => $this->restoreTarget($vacancy, $from),
        };
    }

    private function publishTarget(Vacancy $vacancy, VacancyStatus $from): VacancyStatus
    {
        if (! in_array($from, [VacancyStatus::Draft, VacancyStatus::Scheduled], true)) {
            throw new VacancyInvalidTransition('VACANCY_INVALID_TRANSITION');
        }
        if ($vacancy->open_at === null || $vacancy->close_at === null) {
            throw new VacancyDatesRequired('VACANCY_DATES_REQUIRED');
        }

        return VacancyStatus::Published;
    }

    private function scheduleTarget(Vacancy $vacancy, VacancyStatus $from): VacancyStatus
    {
        if ($from !== VacancyStatus::Draft) {
            throw new VacancyInvalidTransition('VACANCY_INVALID_TRANSITION');
        }
        if ($vacancy->open_at === null || $vacancy->close_at === null) {
            throw new VacancyDatesRequired('VACANCY_DATES_REQUIRED');
        }

        return VacancyStatus::Scheduled;
    }

    private function restoreTarget(Vacancy $vacancy, VacancyStatus $from): VacancyStatus
    {
        if ($from !== VacancyStatus::Suspended) {
            throw new VacancyInvalidTransition('VACANCY_INVALID_TRANSITION');
        }

        return ($vacancy->close_at !== null && now() >= $vacancy->close_at)
            ? VacancyStatus::Closed
            : VacancyStatus::Published;
    }

    /** @param list<VacancyStatus> $allowed */
    private function requireFrom(VacancyStatus $from, array $allowed, VacancyStatus $to): VacancyStatus
    {
        if (! in_array($from, $allowed, true)) {
            throw new VacancyInvalidTransition('VACANCY_INVALID_TRANSITION');
        }

        return $to;
    }
}
