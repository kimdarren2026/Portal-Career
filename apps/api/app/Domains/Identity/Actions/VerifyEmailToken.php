<?php

declare(strict_types=1);

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Exceptions\InvalidTokenException;
use App\Domains\Identity\Models\EmailVerificationToken;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\AuditWriter;
use App\Domains\Identity\Support\TokenHasher;
use Illuminate\Support\Facades\DB;

/**
 * Consume a verification token and activate the account (FR-AUTH-003).
 *
 * FSD §8.1: PENDING_EMAIL_VERIFICATION --verify--> ACTIVE. A user already in
 * another status keeps it; verification records email_verified_at either way
 * rather than resurrecting a SUSPENDED or DISABLED account.
 *
 * Everything below commits atomically, with the token and user rows locked.
 */
final class VerifyEmailToken
{
    public function __construct(private readonly AuditWriter $audit) {}

    public function execute(string $rawToken): User
    {
        if (! TokenHasher::isWellFormed($rawToken)) {
            throw InvalidTokenException::invalid();
        }

        return DB::transaction(function () use ($rawToken): User {
            $token = EmailVerificationToken::query()
                ->where('token_hash', TokenHasher::hash($rawToken))
                ->lockForUpdate()
                ->first();

            if ($token === null) {
                throw InvalidTokenException::invalid();
            }

            // Order matters for the caller's error code: a consumed token is a
            // different signal from an expired one (ERROR_CODES.md §4).
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

            $token->forceFill(['used_at' => now()])->save();

            // Any sibling token still outstanding is invalidated — one
            // verification, one usable link (INV-021).
            EmailVerificationToken::query()
                ->where('user_id', $user->getKey())
                ->whereKeyNot($token->getKey())
                ->whereNull('used_at')
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);

            $attributes = ['email_verified_at' => $user->email_verified_at ?? now()];

            if ($user->status === UserStatus::PendingEmailVerification) {
                $attributes['status'] = UserStatus::Active;
            }

            $user->forceFill($attributes)->save();

            $this->audit->record('email_verification', $user, 'user', (int) $user->getKey());

            return $user->refresh();
        });
    }
}
