<?php

declare(strict_types=1);

namespace App\Domains\Vacancy\Support;

use App\Domains\Identity\Models\User;
use App\Domains\Notification\Support\OutboxWriter;
use App\Domains\Vacancy\Models\SelectionStageAssignment;
use Illuminate\Support\Facades\DB;

/**
 * "Selector notified of assignment and revocation" (API_CONTRACT.md Part VIII).
 * Runs inside the caller's transaction: an in-app `notifications` row plus a
 * transactional-outbox email row (FR-NOTIF-001). Mirrors `RoleChangeNotifier`
 * — `type` is the opaque stable code, the payload carries only stage/vacancy
 * ids, never anything sensitive, and no per-type Indonesian label is invented
 * (the notification-type vocabulary stays open, Part X item 59).
 */
final class SelectorAssignmentNotifier
{
    public function __construct(private readonly OutboxWriter $outbox) {}

    public function assigned(User $selector, SelectionStageAssignment $assignment, int $vacancyId): void
    {
        $this->write($selector, $assignment, $vacancyId, 'ASSIGNED');
    }

    public function revoked(User $selector, SelectionStageAssignment $assignment, int $vacancyId): void
    {
        $this->write($selector, $assignment, $vacancyId, 'REVOKED');
    }

    private function write(User $selector, SelectionStageAssignment $assignment, int $vacancyId, string $direction): void
    {
        DB::table('notifications')->insert([
            'user_id' => $selector->getKey(),
            'type' => 'SELECTOR_ASSIGNMENT_CHANGED',
            'title' => 'SELECTOR_ASSIGNMENT_'.$direction,
            'body_reference' => 'selector.assignment.'.strtolower($direction),
            'related_object_type' => 'selection_stage_assignment',
            'related_object_id' => (int) $assignment->getKey(),
            'created_at' => now(),
        ]);

        $this->outbox->queue(
            (string) $selector->email,
            'selection.selector.assignment.'.strtolower($direction),
            [
                'recruitment_stage_id' => (int) $assignment->recruitment_stage_id,
                'vacancy_id' => $vacancyId,
                'direction' => $direction,
            ],
            'selection_stage_assignment',
            (int) $assignment->getKey(),
        );
    }
}
