<?php

declare(strict_types=1);

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Models\EmailVerificationToken;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\TokenHasher;
use App\Domains\Notification\Support\OutboxWriter;
use Illuminate\Support\Facades\DB;

/**
 * Issue a one-time email verification token (FR-AUTH-003/004, INV-021).
 *
 * Returns the RAW token exactly once, to the immediate delivery flow. It is
 * never persisted, logged, or written into the outbox payload — only its
 * SHA-256 hash reaches the database.
 */
final class IssueEmailVerificationToken
{
    public function __construct(private readonly OutboxWriter $outbox) {}

    /**
     * @return string the raw token, for immediate delivery only
     */
    public function execute(User $user, int $ttlMinutes = 60, bool $queueEmail = true): string
    {
        $rawToken = TokenHasher::generateRawToken();

        DB::transaction(function () use ($user, $rawToken, $ttlMinutes, $queueEmail): void {
            // Lock the identity row so a concurrent issue/verify cannot
            // interleave and leave two usable tokens.
            User::query()->whereKey($user->getKey())->lockForUpdate()->first();

            // Re-issuing invalidates any outstanding token for this user, so a
            // link from an older email cannot still verify (INV-021).
            EmailVerificationToken::query()
                ->where('user_id', $user->getKey())
                ->whereNull('used_at')
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);

            // forceFill: token_hash and created_at are deliberately NOT mass
            // assignable, so only an Action can write them.
            (new EmailVerificationToken())->forceFill([
                'user_id' => $user->getKey(),
                'token_hash' => TokenHasher::hash($rawToken),
                'expires_at' => now()->addMinutes($ttlMinutes),
                'created_at' => now(),
            ])->save();

            if ($queueEmail) {
                // Outbox row inside the transaction; delivery happens later in
                // a worker (INV-015). The payload deliberately carries no token.
                $this->outbox->queue(
                    recipient: $user->email,
                    templateReference: 'identity.email-verification',
                    payload: ['user_name' => $user->name],
                    relatedObjectType: 'user',
                    relatedObjectId: (int) $user->getKey(),
                );
            }
        });

        return $rawToken;
    }
}
