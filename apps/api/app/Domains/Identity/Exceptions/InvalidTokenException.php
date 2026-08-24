<?php

declare(strict_types=1);

namespace App\Domains\Identity\Exceptions;

use RuntimeException;

/**
 * Domain exception carrying a frozen error code (ERROR_CODES.md §4), so the
 * later HTTP layer maps it without re-deriving the reason.
 *
 * The message deliberately never contains the token, the hash, or the account
 * it belonged to.
 */
final class InvalidTokenException extends RuntimeException
{
    private function __construct(public readonly string $errorCode, string $message)
    {
        parent::__construct($message);
    }

    public static function invalid(): self
    {
        return new self('AUTH_TOKEN_INVALID', 'The token is not valid.');
    }

    public static function expired(): self
    {
        return new self('AUTH_TOKEN_EXPIRED', 'The token has expired.');
    }

    public static function alreadyUsed(): self
    {
        return new self('AUTH_TOKEN_ALREADY_USED', 'The token has already been used.');
    }

    public static function revoked(): self
    {
        return new self('AUTH_TOKEN_REVOKED', 'The token has been revoked.');
    }
}
