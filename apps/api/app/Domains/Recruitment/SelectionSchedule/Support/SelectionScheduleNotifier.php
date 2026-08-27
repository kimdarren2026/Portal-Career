<?php

declare(strict_types=1);

namespace App\Domains\Recruitment\SelectionSchedule\Support;

use App\Domains\Notification\Support\OutboxWriter;
use App\Domains\Recruitment\SelectionSchedule\Models\SelectionSchedule;
use Illuminate\Support\Facades\DB;

/**
 * Queues candidate and PIC notifications INSIDE the caller's business
 * transaction (INV-015), per FR-NOTIF-002 ("Jadwal dibuat/diubah/dibatalkan
 * → Kandidat dan petugas terkait"). `pic_user_id` is a plain optional user
 * reference — a `null` PIC produces no PIC notification, and no assumption
 * is made that the PIC holds any particular role (never SELECTOR).
 */
final class SelectionScheduleNotifier
{
    public function __construct(private readonly OutboxWriter $outbox) {}

    public function created(SelectionSchedule $schedule): void
    {
        $this->notify($schedule, 'created', 'SELECTION_SCHEDULE_CREATED');
    }

    public function rescheduled(SelectionSchedule $schedule): void
    {
        $this->notify($schedule, 'rescheduled', 'SELECTION_SCHEDULE_RESCHEDULED');
    }

    public function cancelled(SelectionSchedule $schedule): void
    {
        $this->notify($schedule, 'cancelled', 'SELECTION_SCHEDULE_CANCELLED');
    }

    private function notify(SelectionSchedule $schedule, string $eventSlug, string $inAppType): void
    {
        $payload = [
            'schedule_id' => (int) $schedule->getKey(),
            'application_id' => (int) $schedule->application_id,
            'selection_type' => $schedule->selection_type,
            'starts_at' => $schedule->starts_at?->toIso8601String(),
            'ends_at' => $schedule->ends_at?->toIso8601String(),
            'timezone' => $schedule->timezone,
            'method' => $schedule->method,
            'location' => $schedule->location,
            'meeting_url' => $schedule->meeting_url,
        ];

        $candidateUser = $schedule->application?->candidateProfile?->user;
        if ($candidateUser !== null) {
            $this->queue($candidateUser->getKey(), (string) $candidateUser->email, "schedule.{$eventSlug}.candidate", $inAppType, $payload, $schedule);
        }

        if ($schedule->pic_user_id !== null && $schedule->pic !== null) {
            $this->queue((int) $schedule->pic_user_id, (string) $schedule->pic->email, "schedule.{$eventSlug}.pic", $inAppType, $payload, $schedule);
        }
    }

    /** @param array<string, mixed> $payload */
    private function queue(int $userId, string $recipientEmail, string $templateReference, string $inAppType, array $payload, SelectionSchedule $schedule): void
    {
        $this->outbox->queue($recipientEmail, $templateReference, $payload, 'selection_schedule', (int) $schedule->getKey());
        DB::table('notifications')->insert([
            'user_id' => $userId,
            'type' => $inAppType,
            'title' => $inAppType,
            'body_reference' => $templateReference,
            'related_object_type' => 'selection_schedule',
            'related_object_id' => $schedule->getKey(),
            'created_at' => now(),
        ]);
    }
}
