<?php

declare(strict_types=1);

namespace App\Domains\Audit\Support;

use App\Domains\Audit\Models\AuditLog;

/**
 * Read model for the audit-log list. Returns exactly the fields the frozen
 * contract enumerates — actor, action, object type and id, the (write-time
 * redacted) change summary, correlation id, timestamp — and nothing more.
 *
 * `ip_address` and `user_agent_device_metadata` are deliberately absent: the
 * contract exposes them "only where policy permits collection (H-4,
 * unresolved)", and no such policy exists, so they are never surfaced.
 * `actor_name` is joined in by the caller for display only.
 */
final class AuditLogPresenter
{
    /** @return array<string, mixed> */
    public static function summary(AuditLog $row): array
    {
        return [
            'id' => (int) $row->id,
            'created_at' => $row->created_at?->toIso8601String(),
            'actor_user_id' => $row->actor_user_id === null ? null : (int) $row->actor_user_id,
            'action' => $row->action,
            'object_type' => $row->object_type,
            'object_id' => $row->object_id === null ? null : (int) $row->object_id,
            'correlation_id' => $row->correlation_id,
            'change_summary' => $row->change_summary,
        ];
    }
}
