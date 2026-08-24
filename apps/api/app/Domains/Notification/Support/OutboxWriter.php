<?php

declare(strict_types=1);

namespace App\Domains\Notification\Support;

use Illuminate\Support\Facades\DB;

/**
 * Minimal transactional-outbox writer (INV-015, FR-NOTIF-001).
 *
 * The row is written INSIDE the caller's business transaction. Delivery happens
 * later, in a worker, after that transaction commits — so SMTP failure can
 * never roll back business state, and a worker can never pick up a message
 * whose business rows do not yet exist.
 *
 * This phase writes outbox rows only. The delivery worker, retry/backoff
 * handling and dead-letter alerting belong to the Notification phase.
 *
 * The payload must never contain a raw verification or reset token, a password,
 * or any credential — INV-035 and INV-021. Callers pass only what a template
 * needs to render, and the raw token travels to the mailer separately.
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
        return (int) DB::table('email_outbox')->insertGetId([
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
    }
}
