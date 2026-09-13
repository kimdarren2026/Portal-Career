<?php

declare(strict_types=1);

namespace App\Domains\Notification\Actions;

use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\AuditWriter;
use App\Domains\Notification\Exceptions\EmailOutboxMessageNotFound;
use App\Jobs\DeliverEmailOutboxMessage;
use Illuminate\Support\Facades\DB;

/**
 * `POST /admin/email-outbox/{message}/requeue` (PGC-V1 / PD-B, FR-NOTIF-003
 * "admin dapat resend"). Returns a `FAILED_RETRYABLE` or `DEAD_LETTER` row to
 * `PENDING` with a fresh attempt budget and dispatches a delivery job. A
 * `SENT` or `PROCESSING` row is not requeued. Audited; no credential/secret in
 * the payload.
 */
final class RequeueEmailOutboxMessage
{
    public function __construct(private readonly AuditWriter $audit) {}

    public function execute(User $actor, int $messageId): void
    {
        DB::transaction(function () use ($actor, $messageId): void {
            $row = DB::table('email_outbox')->where('id', $messageId)->lockForUpdate()->first();
            if ($row === null) {
                throw new EmailOutboxMessageNotFound();
            }

            if (! in_array($row->status, ['FAILED_RETRYABLE', 'DEAD_LETTER'], true)) {
                throw new EmailOutboxMessageNotFound();
            }

            DB::table('email_outbox')->where('id', $messageId)->update([
                'status' => 'PENDING',
                'attempt_count' => 0,
                'next_attempt_at' => null,
                'last_error_summary' => null,
            ]);

            $this->audit->record('email_outbox_requeued', $actor, 'email_outbox', $messageId, [
                'previous_status' => $row->status,
            ]);
        });

        DeliverEmailOutboxMessage::dispatch($messageId)->afterCommit();
    }
}
