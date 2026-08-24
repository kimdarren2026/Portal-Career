<?php

declare(strict_types=1);

namespace App\Domains\Identity\Support;

use Illuminate\Support\Str;

/**
 * One-time token generation and hashing for email verification and password
 * reset (INV-021, ADR-011).
 *
 * Only the hash is ever persisted. The raw value is returned once, to the
 * immediate delivery flow, and is never stored, logged, or placed in an audit
 * or outbox payload.
 */
final class TokenHasher
{
    /** 64 hex characters of cryptographically secure randomness. */
    public static function generateRawToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * SHA-256. Deliberately not bcrypt/argon: these are high-entropy random
     * tokens, not user-chosen passwords, so there is nothing to slow down a
     * dictionary attack against — and lookup must be an indexed equality
     * match on token_hash, which an adaptive hash with a per-row salt cannot do.
     */
    public static function hash(string $rawToken): string
    {
        return hash('sha256', $rawToken);
    }

    /** Constant-time comparison for any direct hash equality check. */
    public static function matches(string $rawToken, string $storedHash): bool
    {
        return hash_equals($storedHash, self::hash($rawToken));
    }

    public static function isWellFormed(string $rawToken): bool
    {
        return Str::length($rawToken) === 64 && ctype_xdigit($rawToken);
    }
}
