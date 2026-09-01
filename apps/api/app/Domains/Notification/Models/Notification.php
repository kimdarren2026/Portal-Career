<?php

declare(strict_types=1);

namespace App\Domains\Notification\Models;

use App\Domains\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An in-app notification row (FSD §4.2 *Notifikasi*, FR-NOTIF-001/002).
 *
 * `notifications` is in-app truth; `email_outbox` is delivery infrastructure —
 * two records of one business event, neither derived from the other. This
 * model never reads or joins `email_outbox`. The table has `created_at` only
 * (no `updated_at`); `read_at` is the sole mutable column and is written by
 * `MarkNotificationRead` / `MarkAllNotificationsRead`.
 */
final class Notification extends Model
{
    protected $table = 'notifications';

    public $timestamps = false;

    /** @var list<string> Every column is server-resolved by a domain notifier; none is client-writable here. */
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'read_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
