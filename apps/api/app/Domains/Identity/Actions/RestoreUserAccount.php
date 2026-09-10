<?php

declare(strict_types=1);

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Exceptions\UserAccountInvalidTransition;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\AuditWriter;
use Illuminate\Support\Facades\DB;

/**
 * `POST /admin/users/{user}/restore` (PGC-V1 / PD-F). Returns a `SUSPENDED`
 * account to `ACTIVE`. `reason` is optional. Audited as `user_restored`.
 */
final class RestoreUserAccount
{
    public function __construct(private readonly AuditWriter $audit) {}

    public function execute(User $actor, User $target, ?string $reason): User
    {
        return DB::transaction(function () use ($actor, $target, $reason): User {
            /** @var User $locked */
            $locked = User::query()->whereKey($target->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status !== UserStatus::Suspended) {
                throw new UserAccountInvalidTransition();
            }

            $locked->forceFill(['status' => UserStatus::Active, 'updated_at' => now()])->save();

            $this->audit->record('user_restored', $actor, 'user', (int) $locked->getKey(), array_filter([
                'reason' => $reason,
            ], static fn ($v): bool => $v !== null && $v !== ''));

            return $locked->refresh();
        });
    }
}
