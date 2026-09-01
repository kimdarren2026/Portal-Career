<?php

declare(strict_types=1);

namespace App\Domains\Audit\Support;

use App\Domains\Audit\Models\AuditLog;
use App\Domains\Identity\Enums\RoleCode;
use App\Domains\Identity\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Read scope for `GET /audit-logs` (AUTHORIZATION_MATRIX.md §4.9 — "Read audit
 * logs": `A` for SUPER_ADMIN, `R` for AUDITOR, `D` for every other role;
 * "Mutate audit logs": `D` for everyone including Super Admin, INV-016).
 *
 * Both authorised roles read the full trail: the contract narrows Auditor only
 * "by policy" for IP/device fields (H-4, unresolved) — never by row — and no
 * such narrowing policy exists, so there is one query for both. Query-scoped:
 * a caller who is neither role must be refused before reaching this class; the
 * `1 = 0` guard is defence in depth, never the primary gate.
 */
final class AuditLogScope
{
    public static function isAuditReader(User $user): bool
    {
        return $user->hasActiveRole(RoleCode::SuperAdmin)
            || $user->hasActiveRole(RoleCode::Auditor);
    }

    public static function queryFor(User $user): Builder
    {
        if (! self::isAuditReader($user)) {
            return AuditLog::query()->whereRaw('1 = 0');
        }

        return AuditLog::query();
    }
}
