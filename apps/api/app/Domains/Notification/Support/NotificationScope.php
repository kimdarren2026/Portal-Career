<?php

declare(strict_types=1);

namespace App\Domains\Notification\Support;

use App\Domains\Identity\Models\User;
use App\Domains\Notification\Models\Notification;
use Illuminate\Database\Eloquent\Builder;

/**
 * `OWN` scope for the in-app notification centre (API_CONTRACT.md Part IX,
 * AUTHORIZATION_MATRIX.md §4.9). Query-scoped, never filtered after fetch.
 *
 * The scope is `notifications.user_id = actor` and nothing else. Company
 * membership NEVER widens it: a Company Admin does not see another member's
 * notifications, and two recruiters in one company never see each other's.
 * There is no global-reader, Super Admin, or Auditor branch — every persona
 * sees only their own rows.
 */
final class NotificationScope
{
    public static function queryFor(User $user): Builder
    {
        return Notification::query()->where('user_id', $user->getKey());
    }

    /** A row outside the actor's own set is absent here → enumeration-safe 404 on direct read. */
    public static function findFor(User $user, int $notificationId): ?Notification
    {
        return self::queryFor($user)->whereKey($notificationId)->first();
    }

    public static function unreadCount(User $user): int
    {
        return self::queryFor($user)->whereNull('read_at')->count();
    }
}
