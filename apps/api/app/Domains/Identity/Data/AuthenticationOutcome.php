<?php

declare(strict_types=1);

namespace App\Domains\Identity\Data;

/**
 * Stable domain-level authentication results, suitable for later HTTP error
 * mapping without the Action knowing anything about HTTP.
 *
 * Values align with the frozen error catalogue (ERROR_CODES.md §4). Note that
 * InvalidCredentials is returned for both an unknown account and a wrong
 * password — the caller must not be able to distinguish them (FSD §9.3).
 */
enum AuthenticationOutcome: string
{
    case Succeeded = 'SUCCEEDED';
    case InvalidCredentials = 'AUTH_INVALID_CREDENTIALS';
    case AccountSuspended = 'AUTH_ACCOUNT_SUSPENDED';
    case AccountDisabled = 'AUTH_ACCOUNT_DISABLED';

    public function isSuccess(): bool
    {
        return $this === self::Succeeded;
    }
}
