<?php

declare(strict_types=1);

namespace App\Domains\Identity\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Current password credential metadata, one row per user.
 *
 * Deliberately separate from `users` so the hash can be rotated, timestamped
 * and audited without touching the identity row. No temporary-password
 * onboarding record exists or may be added (ADR-005).
 */
class PasswordCredential extends Model
{
    protected $table = 'password_credentials';

    /**
     * The hash is never mass assignable. It is written only by Actions that
     * have already run it through the Hash service.
     */
    protected $fillable = [
        'user_id',
    ];

    /** Never serialized into a response, log line, or queue payload. */
    protected $hidden = [
        'password_hash',
    ];

    protected function casts(): array
    {
        return [
            'password_changed_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
