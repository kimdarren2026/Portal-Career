<?php

declare(strict_types=1);

namespace App\Domains\Notification\Support;

use App\Jobs\DeliverEmailOutboxMessage;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Transactional-outbox writer (INV-015, FR-NOTIF-001, PGC-V1 / PD-B).
 *
 * The row is written INSIDE the caller's business transaction. A
 * `DeliverEmailOutboxMessage` job is dispatched only AFTER that transaction
 * commits (`afterCommit`) — so SMTP failure can never roll back business
 * state, and a worker can never pick up a message whose business rows do not
 * yet exist. If the dispatch is missed (worker down), the scheduled
 * `outbox:sweep` re-drives the row.
 *
 * The payload must never contain a raw verification or reset token, a
 * password, or any credential (INV-021, INV-035). Every `template_reference`
 * must have a renderer in `EmailTemplateCatalog` — an unknown reference is
 * rejected here so a new notifier cannot ship a message the worker cannot
 * render.
 */
final class OutboxWriter
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function queue(
        string $recipient,
        string $templateReference,
        array $payload = [],
        ?string $relatedObjectType = null,
        ?int $relatedObjectId = null,
    ): int {
        if (! EmailTemplateCatalog::has($templateReference)) {
            throw new RuntimeException("No email template renderer for reference [{$templateReference}].");
        }

        $id = (int) DB::table('email_outbox')->insertGetId([
            'recipient' => $recipient,
            'template_reference' => $templateReference,
            'payload_reference' => json_encode($payload, JSON_THROW_ON_ERROR),
            'related_object_type' => $relatedObjectType,
            'related_object_id' => $relatedObjectId,
            'status' => 'PENDING',
            'attempt_count' => 0,
            'next_attempt_at' => null,
            'created_at' => now(),
        ]);

        DeliverEmailOutboxMessage::dispatch($id)->afterCommit();

        return $id;
    }
}
