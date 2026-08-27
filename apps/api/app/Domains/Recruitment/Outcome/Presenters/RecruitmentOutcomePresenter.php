<?php

declare(strict_types=1);

namespace App\Domains\Recruitment\Outcome\Presenters;

use App\Domains\Application\Models\Application;
use App\Domains\Recruitment\Outcome\Models\RecruitmentOutcome;

/**
 * Explicit allow-list, the same precedent as `OfferPresenter`/
 * `SelectionSchedulePresenter`. No candidate private documents, application
 * private answers, raw audit payload, or internal storage reference is
 * exposed — there is no candidate-facing route for this domain.
 */
final class RecruitmentOutcomePresenter
{
    /** @return array<string, mixed> */
    public static function operational(RecruitmentOutcome $outcome): array
    {
        return [
            'id' => (int) $outcome->getKey(),
            'source_type' => $outcome->source_type,
            'application_id' => $outcome->application_id !== null ? (int) $outcome->application_id : null,
            'external_apply_event_id' => $outcome->external_apply_event_id !== null ? (int) $outcome->external_apply_event_id : null,
            'outcome' => $outcome->outcome,
            'reported_by_source' => $outcome->reported_by_source,
            'confirmed_by' => $outcome->confirmed_by !== null ? (int) $outcome->confirmed_by : null,
            'confirmed_at' => $outcome->confirmed_at?->toIso8601String(),
            'notes' => $outcome->notes,
            'created_at' => $outcome->created_at?->toIso8601String(),
        ];
    }

    /**
     * H-5: identifies a terminal `INTERNAL_APPLICATION` with no recorded
     * outcome. No candidate/screening/document fields — only what is needed
     * to locate and record the missing outcome.
     *
     * @return array<string, mixed>
     */
    public static function incomplete(Application $application): array
    {
        return [
            'source_type' => 'INTERNAL_APPLICATION',
            'application_id' => (int) $application->getKey(),
            'vacancy_id' => (int) $application->vacancy_id,
            'current_status' => $application->current_status?->value,
        ];
    }
}
