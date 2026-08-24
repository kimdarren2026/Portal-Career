<?php

declare(strict_types=1);

namespace App\Domains\Identity\Support;

/** Frozen FR-AUTH-006 baseline, shared by transport and token-based Actions. */
final class PasswordPolicy
{
    public static function passes(string $password, string $email): bool
    {
        return strlen($password) >= 8
            && preg_match('/[A-Z]/', $password) === 1
            && preg_match('/[a-z]/', $password) === 1
            && preg_match('/\d/', $password) === 1
            && mb_strtolower($password, 'UTF-8') !== EmailNormalizer::normalize($email);
    }
}
