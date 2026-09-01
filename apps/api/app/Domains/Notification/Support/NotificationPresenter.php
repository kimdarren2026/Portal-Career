<?php

declare(strict_types=1);

namespace App\Domains\Notification\Support;

use App\Domains\Notification\Models\Notification;

/**
 * Frozen read model for `GET /notifications` (API_CONTRACT.md Part IX):
 * "Returns title, type, body reference, related object type and id, and
 * `read_at`. The related object type is a stable logical name, never an
 * implementation class path (INV-033)."
 *
 * Nothing from `email_outbox` is ever exposed — no `PENDING` / `PROCESSING`
 * / `FAILED` / `DEAD_LETTER` transport state, no attempt count, no delivery
 * error, no SMTP header, no recipient address, no storage reference, no
 * secret or token. `body_reference` is a template key, not rendered content.
 */
final class NotificationPresenter
{
    /** @return array<string, mixed> */
    public static function summary(Notification $notification): array
    {
        return [
            'id' => (int) $notification->getKey(),
            'type' => $notification->type,
            'title' => $notification->title,
            'body_reference' => $notification->body_reference,
            'related_object_type' => $notification->related_object_type,
            'related_object_id' => $notification->related_object_id === null
                ? null
                : (int) $notification->related_object_id,
            'read_at' => $notification->read_at?->toIso8601String(),
            'created_at' => $notification->created_at?->toIso8601String(),
        ];
    }
}
