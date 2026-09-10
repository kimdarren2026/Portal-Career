<?php

declare(strict_types=1);

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Exceptions\UserAccountInvalidTransition;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\AuditWriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `POST /admin/users/{user}/suspend` (PGC-V1 / PD-F).
 *
 * MVP lifecycle is `ACTIVE ↔ SUSPENDED` only. `reason` is required. The
 * suspension is immediate: `users.status` becomes `SUSPENDED` (re-read per
 * request by `EnsureAccountStatus`, OL-10), and the user's server-side
 * session rows are deleted so it does not depend on the next login. No
 * email/in-app notification is sent — abuse response may need silent
 * containment. The administrative act is audited (`user_suspended`) with the
 * reason and no credential/session secret.
 */
final class SuspendUserAccount
{
    public function __construct(private readonly AuditWriter $audit) {}

    public function execute(User $actor, User $target, string $reason): User
    {
        return DB::transaction(function () use ($actor, $target, $reason): User {
            /** @var User $locked */
            $locked = User::query()->whereKey($target->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status !== UserStatus::Active) {
                throw new UserAccountInvalidTransition();
            }

            $locked->forceFill(['status' => UserStatus::Suspended, 'updated_at' => now()])->save();

            $this->purgeSessions((int) $locked->getKey());

            $this->audit->record('user_suspended', $actor, 'user', (int) $locked->getKey(), [
                'reason' => $reason,
            ]);

            return $locked->refresh();
        });
    }

    private function purgeSessions(int $userId): void
    {
        // Session store is `database` in production; a no-op elsewhere.
        if (Schema::hasTable('sessions')) {
            DB::table('sessions')->where('user_id', $userId)->delete();
        }
    }
}
