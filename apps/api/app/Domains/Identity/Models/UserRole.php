<?php

declare(strict_types=1);

namespace App\Domains\Identity\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Role assignment HISTORY (INV-025).
 *
 * Keyed by a surrogate id, never by (user_id, role_id): a revoked assignment
 * stays, and the same role may later be re-issued to the same user. Revocation
 * sets revoked_at/revoked_by and never deletes the row, so a
 * FINAL_YEAR_STUDENT → ALUMNI transition keeps its full history.
 *
 * At most one active assignment per (user, role) is guaranteed by the partial
 * unique index uq_user_roles_user_role_active — that constraint, not this
 * class, is the race guard.
 */
class UserRole extends Model
{
    protected $table = 'user_roles';

    /** No timestamps columns on this table; assigned_at/revoked_at are explicit. */
    public $timestamps = false;

    /**
     * Lifecycle attributes (`revoked_at`, `revoked_by`) are deliberately not
     * mass assignable — revocation happens through an Action.
     */
    protected $fillable = [
        'user_id',
        'role_id',
        'assigned_at',
        'assigned_by',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at');
    }

    public function scopeRevoked(Builder $query): Builder
    {
        return $query->whereNotNull('revoked_at');
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null;
    }
}
