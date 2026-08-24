<?php

declare(strict_types=1);

namespace App\Domains\Identity\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One-time email verification token metadata (FR-AUTH-003/004, INV-021).
 *
 * Only `token_hash` is persisted; the raw value is emailed once and is
 * unrecoverable afterwards. Laravel's stock signed-URL verification stores no
 * token at all and is deliberately not used (ADR-011) — reverting to it would
 * silently drop single-use and revocation semantics.
 */
class EmailVerificationToken extends Model
{
    protected $table = 'email_verification_tokens';

    public $timestamps = false;

    /**
     * `used_at` and `revoked_at` are consumed/revoked through Actions only.
     * `token_hash` is written once at issue time by the issuing Action.
     */
    protected $fillable = [
        'user_id',
        'expires_at',
    ];

    /** Never serialized anywhere. */
    protected $hidden = [
        'token_hash',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'immutable_datetime',
            'used_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Not yet consumed, not revoked, not expired. */
    public function scopeUsable(Builder $query): Builder
    {
        return $query
            ->whereNull('used_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', Carbon::now());
    }

    public function isUsable(): bool
    {
        return $this->used_at === null
            && $this->revoked_at === null
            && $this->expires_at->isFuture();
    }
}
