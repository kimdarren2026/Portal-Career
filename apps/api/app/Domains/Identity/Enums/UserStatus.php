<?php

declare(strict_types=1);

namespace App\Domains\Identity\Enums;

/**
 * Mirrors chk_users_status exactly (FSD §8.1, DATABASE_SCHEMA.md §5).
 * No value may be added here that PostgreSQL would reject.
 */
enum UserStatus: string
{
    case PendingEmailVerification = 'PENDING_EMAIL_VERIFICATION';
    case Active = 'ACTIVE';
    case Suspended = 'SUSPENDED';
    case Disabled = 'DISABLED';

    /** May this account establish an authenticated session? */
    public function canAuthenticate(): bool
    {
        // FR-AUTH-005: email verification is NOT required to sign in; it is
        // required to act. SUSPENDED and DISABLED are refused outright.
        return $this === self::PendingEmailVerification || $this === self::Active;
    }

    /** May this account perform actions that require a verified email? */
    public function canAct(): bool
    {
        return $this === self::Active;
    }
}
