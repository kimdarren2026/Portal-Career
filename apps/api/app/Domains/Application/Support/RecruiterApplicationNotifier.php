<?php

declare(strict_types=1);

namespace App\Domains\Application\Support;

use App\Domains\Application\Models\Application;
use App\Domains\Notification\Support\OutboxWriter;
use App\Domains\Vacancy\Models\RecruitmentStage;
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

    /**
     * Move-stage's candidate-facing content is the target stage's own
     * pre-authored `candidate_visible_label`, never a per-move custom note
     * (the frozen contract deliberately carries no `candidate_visible_note`
     * field for this operation). No recruiter self-notification, same
     * precedent as `transitioned()` above.
     */
    public function stageMoved(Application $application, RecruitmentStage $targetStage): void
    {
        $candidateUser = $application->candidateProfile?->user;
        if ($candidateUser === null) {
            return;
        }

        $payload = [
            'application_id' => (int) $application->getKey(),
            'application_code' => $application->application_code,
            'vacancy_id' => (int) $application->vacancy_id,
            'stage_label' => $targetStage->candidate_visible_label,
        ];

        $this->outbox->queue((string) $candidateUser->email, 'application.stage_moved.candidate', $payload, 'application', (int) $application->getKey());
        DB::table('notifications')->insert([
            'user_id' => $candidateUser->getKey(),
            'type' => 'APPLICATION_STAGE_CHANGED',
            'title' => 'APPLICATION_STAGE_CHANGED',
            'body_reference' => 'application.stage_moved.candidate',
            'related_object_type' => 'application',
            'related_object_id' => $application->getKey(),
            'created_at' => now(),
        ]);
    }
}
