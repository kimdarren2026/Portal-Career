<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\DeliverEmailOutboxMessage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * `outbox:sweep` (PGC-V1 / PD-B). Re-drives every `PENDING` / `FAILED_RETRYABLE`
 * row whose `next_attempt_at` has elapsed by dispatching one
 * `DeliverEmailOutboxMessage` per row. Covers rows written before the worker
 * existed, retries whose backoff has passed, and any dispatch missed because
 * a worker was down. Scheduled once a minute (see `routes/console.php`).
 */
final class SweepEmailOutboxCommand extends Command
{
    protected $signature = 'outbox:sweep {--limit=500 : Maximum rows to dispatch per run}';

    protected $description = 'Dispatch delivery jobs for due transactional email-outbox rows.';

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));

        $ids = DB::table('email_outbox')
            ->whereIn('status', ['PENDING', 'FAILED_RETRYABLE'])
            ->where(function ($q): void {
                $q->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now());
            })
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id');

        foreach ($ids as $id) {
            DeliverEmailOutboxMessage::dispatch((int) $id);
        }

        $this->info(sprintf('Dispatched %d outbox delivery job(s).', $ids->count()));

        return self::SUCCESS;
    }
}
