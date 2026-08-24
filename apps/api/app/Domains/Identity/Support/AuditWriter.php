<?php

declare(strict_types=1);

namespace App\Domains\Identity\Support;

use App\Domains\Identity\Models\User;
use Illuminate\Support\Facades\DB;

/** Writes the minimal append-only security trail required for Identity flows. */
final class AuditWriter
{
    /** @param array<string, scalar|array|null> $summary */
    public function record(string $action, ?User $actor, string $objectType, ?int $objectId, array $summary = []): void
    {
        $request = app()->bound('request') ? request() : null;

        DB::table('audit_logs')->insert([
            'actor_user_id' => $actor?->getKey(),
            'action' => $action,
            'object_type' => $objectType,
            'object_id' => $objectId,
            'change_summary' => $summary === [] ? null : json_encode($summary, JSON_THROW_ON_ERROR),
            'correlation_id' => $request?->attributes->get('correlation_id'),
            // H-4 is unresolved: do not collect IP or device metadata by default.
            'ip_address' => null,
            'user_agent_device_metadata' => null,
            'created_at' => now(),
        ]);
    }
}
