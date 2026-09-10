<?php

declare(strict_types=1);

namespace App\Domains\Notification\Support;

use App\Domains\Notification\Models\SmtpConfiguration;

/**
 * Resolves the retry policy for the outbox delivery worker (PGC-V1 / PD-B):
 * the active runtime `smtp_configurations` row when present, else deployment
 * configuration defaults. Also sanitizes a transport failure into a
 * `last_error_summary` that never carries a credential, host, DSN, or stack
 * trace (INV-015 / INV-035).
 */
final class OutboxDeliveryPolicy
{
    public function maxAttempts(): int
    {
        $config = SmtpConfiguration::query()->where('is_active', true)->value('max_attempts');

        return (int) ($config ?? config('outbox.max_attempts', 5));
    }

    public function backoffSeconds(int $attemptCount): int
    {
        $config = SmtpConfiguration::query()->where('is_active', true)->value('retry_backoff_seconds');
        $base = (int) ($config ?? config('outbox.retry_backoff_seconds', 300));

        // Linear backoff by attempt, capped at one day — a technical measure,
        // not a business rule.
        return min($base * max(1, $attemptCount), 86_400);
    }

    /** A fixed-shape, credential-free summary of a delivery failure. */
    public function sanitizeFailure(\Throwable $exception): string
    {
        $class = (new \ReflectionClass($exception))->getShortName();

        return sprintf(
            'Pengiriman gagal (%s). Periksa konfigurasi SMTP dan alamat penerima.',
            preg_replace('/[^A-Za-z]/', '', $class) ?: 'Error',
        );
    }
}
