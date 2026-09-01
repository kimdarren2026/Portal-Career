<?php

declare(strict_types=1);

namespace App\Domains\Notification\Actions;

use App\Domains\Identity\Models\User;
use App\Domains\Notification\Support\NotificationScope;
use Illuminate\Support\Facades\DB;

/**
 * `POST /notifications/read-all` (API_CONTRACT.md Part IX).
 *
 * Affects ONLY the authenticated actor's own unread notifications — never
 * company-wide, role-wide or system-wide. Idempotent: with nothing unread it
 * updates zero rows. No audit (not an FR-AUD-001 event).
 *
 * @return int rows marked read
 */
final class MarkAllNotificationsRead
{
    public function execute(User $actor): int
    {
        return DB::transaction(
            static fn (): int => NotificationScope::queryFor($actor)
                ->whereNull('read_at')
                ->update(['read_at' => now()]),
        );
    }
}
