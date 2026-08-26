<?php

declare(strict_types=1);

namespace App\Domains\Application\Support;

use App\Domains\Application\Models\Application;
use App\Domains\Notification\Support\OutboxWriter;
use Illuminate\Support\Facades\DB;

/**
 * Queues the transition's candidate notification INSIDE the caller's
 * business transaction (INV-015). Only the candidate is notified — the
 * frozen contract text for `/transition` names candidate notification
 * "according to candidate_visibility" and never names a recruiter/owner
 * self-notification, unlike submit/withdraw which explicitly name both
 * parties. No self-notification is invented here.
 */
final class RecruiterApplicationNotifier
{
    public function __construct(private readonly OutboxWriter $outbox) {}

    public function transitioned(Application $application, ?string $candidateVisibleNote): void
    {
        $candidateUser = $application->candidateProfile?->user;
        if ($candidateUser === null) {
            return;
        }

        $payload = [
            'application_id' => (int) $application->getKey(),
            'application_code' => $application->application_code,
            'vacancy_id' => (int) $application->vacancy_id,
            'current_status' => $application->current_status?->value,
            'candidate_visible_note' => $candidateVisibleNote,
        ];

        $this->outbox->queue((string) $candidateUser->email, 'application.transitioned.candidate', $payload, 'application', (int) $application->getKey());
        DB::table('notifications')->insert([
            'user_id' => $candidateUser->getKey(),
            'type' => 'APPLICATION_STATUS_CHANGED',
            'title' => 'APPLICATION_STATUS_CHANGED',
            'body_reference' => 'application.transitioned.candidate',
            'related_object_type' => 'application',
            'related_object_id' => $application->getKey(),
            'created_at' => now(),
        ]);
    }
}
