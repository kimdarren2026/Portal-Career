<?php

declare(strict_types=1);

namespace App\Domains\Recruitment\SelectionSchedule\Presenters;

use App\Domains\Recruitment\SelectionSchedule\Models\SelectionSchedule;
use App\Domains\Recruitment\SelectionSchedule\Models\SelectionScheduleHistory;

/**
 * Two deliberate allow-lists, separate so neither can regress the other
 * (same precedent as `RecruiterApplicationPresenter`/`ApplicationPresenter`).
 * `attachment_storage_reference` is never returned by either — no download
 * path exists for it in Foundation v1 (mirrors RA-3's own "metadata only,
 * never a raw storage reference" precedent). Candidate never receives
 * `pic_user_id`, `revision_number`, or the internal `recruitment_stage_id` —
 * only the target stage's own pre-authored `candidate_visible_label`, where
 * set.
 */
final class SelectionSchedulePresenter
{
    /** @return array<string, mixed> */
    public static function operational(SelectionSchedule $schedule): array
    {
        return [
            'id' => (int) $schedule->getKey(),
            'application_id' => (int) $schedule->application_id,
            'recruitment_stage_id' => (int) $schedule->recruitment_stage_id,
            'selection_type' => $schedule->selection_type,
            'starts_at' => $schedule->starts_at?->toIso8601String(),
            'ends_at' => $schedule->ends_at?->toIso8601String(),
            'timezone' => $schedule->timezone,
            'method' => $schedule->method,
            'location' => $schedule->location,
            'meeting_url' => $schedule->meeting_url,
            'pic_user_id' => $schedule->pic_user_id,
            'instructions' => $schedule->instructions,
            'status' => $schedule->status?->value,
            'revision_number' => $schedule->revision_number,
            'created_at' => $schedule->created_at?->toIso8601String(),
            'updated_at' => $schedule->updated_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public static function candidate(SelectionSchedule $schedule): array
    {
        return [
            'id' => (int) $schedule->getKey(),
            'application_id' => (int) $schedule->application_id,
            'selection_type' => $schedule->selection_type,
            'starts_at' => $schedule->starts_at?->toIso8601String(),
            'ends_at' => $schedule->ends_at?->toIso8601String(),
            'timezone' => $schedule->timezone,
            'method' => $schedule->method,
            'location' => $schedule->location,
            'meeting_url' => $schedule->meeting_url,
            'instructions' => $schedule->instructions,
            'status' => $schedule->status?->value,
            'stage_label' => $schedule->recruitmentStage?->candidate_visible_label,
        ];
    }

    /** @return array<string, mixed> */
    public static function historyOperational(SelectionScheduleHistory $event): array
    {
        return [
            'event_type' => $event->event_type?->value,
            'previous_snapshot' => $event->previous_snapshot,
            'resulting_revision_number' => $event->resulting_revision_number,
            'actor_user_id' => $event->actor_user_id,
            'reason' => $event->reason,
            'occurred_at' => $event->occurred_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public static function historyCandidate(SelectionScheduleHistory $event): array
    {
        return [
            'event_type' => $event->event_type?->value,
            'occurred_at' => $event->occurred_at?->toIso8601String(),
        ];
    }
}
