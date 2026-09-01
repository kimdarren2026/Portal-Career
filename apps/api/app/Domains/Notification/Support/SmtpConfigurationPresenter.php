<?php

declare(strict_types=1);

namespace App\Domains\Notification\Support;

use App\Domains\Notification\Models\SmtpConfiguration;

/**
 * The only sanctioned read shape for an SMTP configuration. Returns the
 * non-secret metadata the frozen `GET /admin/smtp-configuration` contract
 * enumerates plus the boolean `secret_configured`.
 *
 * INV-035: `encrypted_password` is never referenced here — not masked, not
 * truncated, not length-hinted. `secret_configured` conveys only whether a
 * credential exists, never the value, its length, or any derivative.
 */
final class SmtpConfigurationPresenter
{
    /** @return array<string, mixed> */
    public static function safe(SmtpConfiguration $c): array
    {
        return [
            'host' => $c->host,
            'port' => $c->port,
            'encryption_mode' => $c->encryption_mode,
            'username' => $c->username,
            'from_address' => $c->from_address,
            'from_name' => $c->from_name,
            'reply_to_address' => $c->reply_to_address,
            'timeout_seconds' => $c->timeout_seconds,
            'max_attempts' => $c->max_attempts,
            'retry_backoff_seconds' => $c->retry_backoff_seconds,
            'is_active' => (bool) $c->is_active,
            'last_tested_at' => $c->last_tested_at?->toIso8601String(),
            'last_test_result' => $c->last_test_result,
            'updated_by_user_id' => $c->updated_by_user_id === null ? null : (int) $c->updated_by_user_id,
            'updated_at' => $c->updated_at?->toIso8601String(),
            'secret_configured' => $c->hasSecret(),
        ];
    }
}
