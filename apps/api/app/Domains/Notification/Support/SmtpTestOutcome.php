<?php

declare(strict_types=1);

namespace App\Domains\Notification\Support;

/**
 * Result of a Super Admin SMTP test send. `safeMessage` is already sanitized
 * for display and storage — it contains no host, username, recipient,
 * credential, DSN, stack trace or provider exception text (INV-035).
 */
final class SmtpTestOutcome
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $safeMessage = null,
    ) {}

    public static function success(): self
    {
        return new self(true, null);
    }

    public static function failure(string $safeMessage): self
    {
        return new self(false, $safeMessage);
    }
}
