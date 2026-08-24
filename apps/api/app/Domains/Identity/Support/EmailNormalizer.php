<?php

declare(strict_types=1);

namespace App\Domains\Identity\Support;

/**
 * The single email normalization algorithm for the whole application (INV-001).
 *
 * users.email_normalized is the sole authoritative uniqueness key; users.email
 * is the display form and carries no uniqueness constraint. Every identity
 * lookup, every uniqueness validation, and every write path must normalize
 * through this class — duplicating the algorithm anywhere else reintroduces the
 * case-variant duplicate account this invariant exists to prevent.
 */
final class EmailNormalizer
{
    /**
     * Trim surrounding whitespace and lower-case. Deliberately conservative:
     * no dot-stripping, no plus-addressing removal. Those are provider-specific
     * conventions, and applying them would silently merge addresses the business
     * has not agreed are the same person.
     */
    public static function normalize(string $email): string
    {
        return mb_strtolower(trim($email), 'UTF-8');
    }
}
