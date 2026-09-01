<?php

declare(strict_types=1);

namespace App\Domains\Notification\Support;

use App\Domains\Notification\Models\SmtpConfiguration;

/**
 * Performs a single diagnostic SMTP send using a runtime-built transport from
 * the stored configuration. Implementations MUST:
 * - decrypt the credential only in memory, only for this call, and never
 *   persist, log or echo it (INV-035);
 * - build a request-scoped transport — never mutate global mail config or
 *   `.env` in a way that leaks across concurrent requests;
 * - return {@see SmtpTestOutcome}, mapping any failure to a sanitized message
 *   with no host / username / recipient / credential / DSN / stack trace /
 *   raw provider exception.
 *
 * The test bypasses `email_outbox` entirely (it is a diagnostic, not a
 * business message) and never creates a notification.
 */
interface SmtpTestSender
{
    public function send(SmtpConfiguration $config, string $recipient): SmtpTestOutcome;
}
