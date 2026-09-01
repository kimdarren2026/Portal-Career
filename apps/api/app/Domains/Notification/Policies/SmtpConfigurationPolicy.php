<?php

declare(strict_types=1);

namespace App\Domains\Notification\Policies;

use App\Domains\Identity\Enums\RoleCode;
use App\Domains\Identity\Models\User;

/**
 * SMTP configuration is `SUPER_ADMIN` only for read, update and test
 * (AUTHORIZATION_MATRIX.md §4.9). It is system configuration, not audit data
 * — **Auditor is explicitly denied**, footnote 33 — and every other persona
 * is denied. This is not a broad "admin-like" check: it is the exact
 * `SUPER_ADMIN` role, and nothing else.
 */
final class SmtpConfigurationPolicy
{
    public function view(User $user): bool
    {
        return $this->superAdmin($user);
    }

    public function update(User $user): bool
    {
        return $this->superAdmin($user);
    }

    public function test(User $user): bool
    {
        return $this->superAdmin($user);
    }

    private function superAdmin(User $user): bool
    {
        return $user->hasActiveRole(RoleCode::SuperAdmin);
    }
}
