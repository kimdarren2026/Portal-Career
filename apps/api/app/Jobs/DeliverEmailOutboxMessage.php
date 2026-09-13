<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domains\Notification\Support\EmailTemplateCatalog;
use App\Domains\Notification\Support\OutboxDeliveryPolicy;
use App\Domains\Notification\Support\OutboxMailSender;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Delivers one `email_outbox` row (PGC-V1 / PD-B).
 *
 * State machine: `PENDING` / `FAILED_RETRYABLE` -> `PROCESSING` -> `SENT`;
 * on a transport failure the attempt count is incremented and the row goes to
 * `FAILED_RETRYABLE` with `next_attempt_at = now + backoff`, or to
 * `DEAD_LETTER` once `attempt_count >= max_attempts`. `SENT`, `PROCESSING`,
 * and `DEAD_LETTER` rows are skipped (concurrency- and duplicate-safe: the row
 * is locked and its status re-checked inside a transaction before any send).
 *
 * The business transaction that wrote the outbox row has already committed —
 * this job only ever touches `email_outbox`, so a delivery failure can never
 * roll back business state (INV-015). `--tries=1`: our own state machine owns
 * retries, not the queue.
 */
final class DeliverEmailOutboxMessage implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public function __construct(public readonly int $outboxId)
    {
        $this->onQueue('mail');
    }

    public function handle(OutboxMailSender $sender, OutboxDeliveryPolicy $policy, EmailTemplateCatalog $catalog): void
    {
        $row = DB::transaction(function () {
            $locked = DB::table('email_outbox')->where('id', $this->outboxId)->lockForUpdate()->first();
            if ($locked === null) {
                return null;
            }

            if (! in_array($locked->status, ['PENDING', 'FAILED_RETRYABLE'], true)) {
                return null;
            }
            if ($locked->next_attempt_at !== null && now()->lt($locked->next_attempt_at)) {
                return null;
            }

            DB::table('email_outbox')->where('id', $this->outboxId)->update([
                'status' => 'PROCESSING',
            ]);

            return $locked;
        });

        if ($row === null) {
            return;
        }

        try {
            /** @var array<string, mixed> $payload */
            $payload = $row->payload_reference === null ? [] : (array) json_decode((string) $row->payload_reference, true);
            $rendered = $catalog->render((string) $row->template_reference, $payload);

            $sender->send((string) $row->recipient, $rendered['subject'], $rendered['body']);

            DB::table('email_outbox')->where('id', $this->outboxId)->update([
                'status' => 'SENT',
                'sent_at' => now(),
                'last_error_summary' => null,
            ]);
        } catch (Throwable $exception) {
            $this->recordFailure($policy, (int) $row->attempt_count + 1, $exception);
        }
    }

    private function recordFailure(OutboxDeliveryPolicy $policy, int $attemptCount, Throwable $exception): void
    {
        $dead = $attemptCount >= $policy->maxAttempts();

        DB::table('email_outbox')->where('id', $this->outboxId)->update([
            'status' => $dead ? 'DEAD_LETTER' : 'FAILED_RETRYABLE',
            'attempt_count' => $attemptCount,
            'next_attempt_at' => $dead ? null : now()->addSeconds($policy->backoffSeconds($attemptCount)),
            'last_error_summary' => $policy->sanitizeFailure($exception),
        ]);
    }

    /**
     * If the queue itself throws before/around `handle` (serialization, worker
     * kill), release the PROCESSING lock back to FAILED_RETRYABLE so a sweep
     * can re-drive it rather than stranding the row.
     */
    public function failed(?Throwable $exception): void
    {
        DB::table('email_outbox')
            ->where('id', $this->outboxId)
            ->where('status', 'PROCESSING')
            ->update([
                'status' => 'FAILED_RETRYABLE',
                'next_attempt_at' => now(),
            ]);
    }
}
