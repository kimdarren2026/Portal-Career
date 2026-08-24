<?php

declare(strict_types=1);

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Models\PasswordResetToken;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\TokenHasher;
use App\Domains\Notification\Support\OutboxWriter;
use Illuminate\Support\Facades\DB;

/**
 * Issue a one-time password reset token (FR-AUTH-007, INV-021).
 *
 * Account enumeration is prevented by returning null for an unknown address
 * rather than throwing: the caller emits an identical response either way
 * (FSD §9.3). The differentiated outcome is delivered by email.
 */
final class IssuePasswordResetToken
{
    public function __construct(private readonly OutboxWriter $outbox) {}

    /**
     * @return string|null the raw token for immediate delivery, or null when no
     *                     account matches — the caller must not distinguish
     */
    public function execute(string $email, int $ttlMinutes = 60, bool $queueEmail = true): ?string
    {
        /** @var User|null $user */
        $user = User::query()->byEmail($email)->first();

        if ($user === null) {
            return null;
        }

        $rawToken = TokenHasher::generateRawToken();

        DB::transaction(function () use ($user, $rawToken, $ttlMinutes, $queueEmail): void {
            User::query()->whereKey($user->getKey())->lockForUpdate()->first();

            PasswordResetToken::query()
                ->where('user_id', $user->getKey())
                ->whereNull('used_at')
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);

            (new PasswordResetToken())->forceFill([
                'user_id' => $user->getKey(),
                'token_hash' => TokenHasher::hash($rawToken),
                'expires_at' => now()->addMinutes($ttlMinutes),
                'created_at' => now(),
            ])->save();

            if ($queueEmail) {
                $this->outbox->queue(
                    recipient: $user->email,
                    templateReference: 'identity.password-reset',
                    payload: ['user_name' => $user->name],
                    relatedObjectType: 'user',
                    relatedObjectId: (int) $user->getKey(),
                );
            }
        });

        return $rawToken;
    }
}
