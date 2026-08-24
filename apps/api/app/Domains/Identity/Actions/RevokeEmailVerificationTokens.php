<?php

declare(strict_types=1);

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Models\EmailVerificationToken;
use App\Domains\Identity\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Revoke every outstanding verification token for a user without consuming any
 * of them. Revocation never deletes a row (INV-021).
 */
final class RevokeEmailVerificationTokens
{
    public function execute(User $user): int
    {
        return DB::transaction(fn (): int => EmailVerificationToken::query()
            ->where('user_id', $user->getKey())
            ->whereNull('used_at')
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]));
    }
}
