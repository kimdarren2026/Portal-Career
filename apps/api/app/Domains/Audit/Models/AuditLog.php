<?php

declare(strict_types=1);

namespace App\Domains\Audit\Models;

use App\Domains\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row of the append-only security trail (FSD §5.13 FR-AUD-001,
 * API_CONTRACT.md `GET /api/v1/audit-logs`).
 *
 * `audit_logs` is physically append-only — the runtime database role holds
 * `SELECT, INSERT` only, with `UPDATE, DELETE` revoked (migration
 * `2026_08_24_000701`), so this model is read-only by construction. Writes go
 * exclusively through `Identity\Support\AuditWriter`, which redacts
 * `change_summary` at write time (INV-035): it never contains a password,
 * hash, token, SMTP credential, API secret, session id, storage key or file
 * content — an SMTP change records `credential_changed: true`, never a value.
 *
 * `ip_address` and `user_agent_device_metadata` exist on the table but are
 * written `null` while H-4 (device-metadata collection policy) is unresolved,
 * and are deliberately NOT exposed by the read contract.
 */
final class AuditLog extends Model
{
    protected $table = 'audit_logs';

    public $timestamps = false;

    /** @var list<string> Append-only; nothing is writable through the model. */
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'change_summary' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
