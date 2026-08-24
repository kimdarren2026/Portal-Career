<?php

declare(strict_types=1);

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Exceptions\CurrentPasswordInvalidException;
use App\Domains\Identity\Exceptions\PasswordPolicyException;
use App\Domains\Identity\Models\PasswordCredential;
use App\Domains\Identity\Models\PasswordResetToken;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\AuditWriter;
use App\Domains\Identity\Support\PasswordPolicy;
use App\Domains\Notification\Support\OutboxWriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/** Replaces the actor's credential and evicts every other session. */
final class ChangeOwnPassword
{
    public function __construct(private readonly AuditWriter $audit, private readonly OutboxWriter $outbox) {}

    public function execute(User $actor, string $currentPassword, string $newPassword): User
    {
        $currentSessionId = app()->bound('session') && app('session')->isStarted()
            ? app('session')->getId()
            : null;

        return DB::transaction(function () use ($actor, $currentPassword, $newPassword, $currentSessionId): User {
            /** @var User $user */
            $user = User::query()->whereKey($actor->getKey())->lockForUpdate()->firstOrFail();
            /** @var PasswordCredential|null $credential */
            $credential = PasswordCredential::query()->where('user_id', $user->getKey())->lockForUpdate()->first();

            if ($credential === null || ! Hash::check($currentPassword, $credential->password_hash)) {
                throw new CurrentPasswordInvalidException();
            }
            if (! PasswordPolicy::passes($newPassword, $user->email)) {
                throw new PasswordPolicyException();
            }

            $credential->forceFill([
                'password_hash' => Hash::make($newPassword),
                'password_changed_at' => now(),
                'updated_at' => now(),
            ])->save();

            PasswordResetToken::query()->where('user_id', $user->getKey())
                ->whereNull('used_at')->whereNull('revoked_at')->update(['revoked_at' => now()]);

            $sessions = DB::table('sessions')->where('user_id', $user->getKey());
            if (is_string($currentSessionId) && $currentSessionId !== '') {
                $sessions->where('id', '<>', $currentSessionId);
            }
            $sessions->delete();

            $this->outbox->queue(
                recipient: $user->email,
                templateReference: 'identity.password-changed',
                payload: ['user_name' => $user->name],
                relatedObjectType: 'user',
                relatedObjectId: (int) $user->getKey(),
            );
            $this->audit->record('password_changed', $user, 'user', (int) $user->getKey());

            return $user->refresh();
        });
    }
}
