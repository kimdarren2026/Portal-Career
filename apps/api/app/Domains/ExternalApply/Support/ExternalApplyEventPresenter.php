<?php

declare(strict_types=1);

namespace App\Domains\ExternalApply\Support;

use App\Domains\ExternalApply\Models\ExternalApplyEvent;

/**
 * Candidate-facing read model for *Aktivitas Lamaran Eksternal* — a separate
 * surface from *Lamaran Saya*. Never presented as an application; carries no
 * application id or status.
 */
final class ExternalApplyEventPresenter
{
    /** @return array<string, mixed> */
    public static function summary(ExternalApplyEvent $event): array
    {
        return [
            'id' => (int) $event->getKey(),
            'vacancy_id' => (int) $event->vacancy_id,
            'event_type' => $event->event_type,
            'destination_url' => $event->destination_url_reference,
            'started_at' => $event->started_at?->toIso8601String(),
            'confirmation_status' => $event->confirmation_status,
            'confirmation_source' => $event->confirmation_source,
            'confirmed_at' => $event->confirmed_at?->toIso8601String(),
        ];
    }
}
