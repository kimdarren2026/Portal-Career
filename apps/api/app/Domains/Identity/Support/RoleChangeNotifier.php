<?php

declare(strict_types=1);

namespace App\Domains\Identity\Support;

use App\Domains\Identity\Models\User;
use App\Domains\Notification\Support\OutboxWriter;
use Illuminate\Support\Facades\DB;

/**
 * "Affected user notified" for `POST /admin/users/{user}/roles` and its revoke
 * pair (API_CONTRACT.md). Runs inside the caller's transaction: an in-app
 * `notifications` row plus a transactional-outbox email row (FR-NOTIF-001).
 *
 * `type` is the opaque stable code `ROLE_CHANGED`, consistent with the
 * existing notification runtime — Part X item 59 leaves the notification-type
 * vocabulary and its localized labels open, so no Indonesian per-type label is
 * fabricated here. The payload carries only the role code and the direction,
 * never a credential.
 */
final class RoleChangeNotifier
{
    public function __construct(private readonly OutboxWriter $outbox) {}

    public function assigned(User $affected, string $roleCode): void
    {
        $this->write($affected, $roleCode, 'ASSIGNED');
    }

    public function revoked(User $affected, string $roleCode): void
    {
        $this->write($affected, $roleCode, 'REVOKED');
    }

    private function write(User $affected, string $roleCode, string $direction): void
    {
        DB::table('notifications')->insert([
            'user_id' => $affected->getKey(),
            'type' => 'ROLE_CHANGED',
            'title' => 'ROLE_'.$direction,
            'body_reference' => 'role.'.strtolower($direction),
            'related_object_type' => 'user',
            'related_object_id' => $affected->getKey(),
            'created_at' => now(),
        ]);

        $this->outbox->queue(
            (string) $affected->email,
            'identity.role.'.strtolower($direction),
            ['role_code' => $roleCode, 'direction' => $direction],
            'user',
            (int) $affected->getKey(),
        );
    }
}
