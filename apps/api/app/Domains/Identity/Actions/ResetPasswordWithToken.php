<?php

declare(strict_types=1);

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Exceptions\InvalidTokenException;
use App\Domains\Identity\Models\PasswordCredential;
use App\Domains\Identity\Models\PasswordResetToken;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\TokenHasher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Consume a reset token and replace the password hash (FR-AUTH-007, INV-021).
 *
 * The new hash is written to `password_credentials` — never to `users`, which
 * has no password column and must not gain one.
 *
 * On success every other outstanding reset token for that user is revoked: a
 * reset is the remedy for a compromised account, so any link the attacker also
 * holds must stop working.
 */
final class ResetPasswordWithToken
{
    public function execute(string $rawToken, string $newPassword): User
    {
        if (! TokenHasher::isWellFormed($rawToken)) {
            throw InvalidTokenException::invalid();
        }

        return DB::transaction(function () use ($rawToken, $newPassword): User {
            $token = PasswordResetToken::query()
                ->where('token_hash', TokenHasher::hash($rawToken))
                ->lockForUpdate()
                ->first();

            if ($token === null) {
                throw InvalidTokenException::invalid();
            }

            if ($token->revoked_at !== null) {
                throw InvalidTokenException::revoked();
            }

            if ($token->used_at !== null) {
                throw InvalidTokenException::alreadyUsed();
            }

            if ($token->expires_at->isPast()) {
                throw InvalidTokenException::expired();
            }

            /** @var User $user */
            $user = User::query()->whereKey($token->user_id)->lockForUpdate()->firstOrFail();

            $credential = PasswordCredential::query()
                ->where('user_id', $user->getKey())
                ->lockForUpdate()
                ->first();

            $hash = Hash::make($newPassword);

            if ($credential === null) {
                $credential = new PasswordCredential();
                $credential->forceFill([
                    'user_id' => $user->getKey(),
                    'created_at' => now(),
                ]);
            }

            $credential->forceFill([
                'password_hash' => $hash,
                'password_changed_at' => now(),
                'updated_at' => now(),
            ])->save();

            $token->forceFill(['used_at' => now()])->save();

            PasswordResetToken::query()
                ->where('user_id', $user->getKey())
                ->whereKeyNot($token->getKey())
                ->whereNull('used_at')
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);

            return $user->refresh();
        });
    }
}
