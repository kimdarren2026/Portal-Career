<?php

declare(strict_types=1);

namespace App\Domains\Notification\Models;

use App\Domains\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Runtime-managed SMTP delivery configuration (FR-NOTIF-005, ADR-015).
 *
 * `encrypted_password` is the only credential in the logical model and is
 * governed absolutely by INV-035: encrypted by the application before it
 * reaches the database with a key held outside the database, **write-only**,
 * never returned by any read / response / export / log / audit payload.
 *
 * Defence in depth for "never returned":
 * - `$hidden` keeps `encrypted_password` out of `toArray()` / JSON / any
 *   Inertia serialization;
 * - no accessor decrypts it — decryption happens only inside the test-send
 *   service, which reads the raw attribute explicitly;
 * - `SmtpConfigurationPresenter` is the only sanctioned read shape and never
 *   references the column.
 *
 * INV-036: at most one row may have `is_active = true` (DB partial unique
 * index `uq_smtp_configurations_active`); zero active rows is a valid state
 * (delivery falls back to deployment configuration).
 */
final class SmtpConfiguration extends Model
{
    protected $table = 'smtp_configurations';

    /** @var list<string> Never client-writable; every write goes through UpdateSmtpConfiguration. */
    protected $fillable = [];

    /** @var list<string> The credential never leaves the model through serialization. */
    protected $hidden = ['encrypted_password'];

    protected function casts(): array
    {
        return [
            'port' => 'integer',
            'timeout_seconds' => 'integer',
            'max_attempts' => 'integer',
            'retry_backoff_seconds' => 'integer',
            'is_active' => 'boolean',
            'last_tested_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function hasSecret(): bool
    {
        return $this->getAttribute('encrypted_password') !== null;
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }
}
