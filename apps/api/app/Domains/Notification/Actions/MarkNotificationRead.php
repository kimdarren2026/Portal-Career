<?php

declare(strict_types=1);

namespace App\Domains\Notification\Actions;

use App\Domains\Notification\Models\Notification;
use Illuminate\Support\Facades\DB;

/**
 * `POST /notifications/{notification}/read` (API_CONTRACT.md Part IX).
 *
 * Naturally idempotent: an already-read row is left exactly as it is, so a
 * repeated call is a no-op and never moves `read_at`. Caller has already
 * resolved the row through `NotificationScope::findFor()`, so ownership is
 * guaranteed before this Action runs. No audit — reading one's own
 * notification is not an FR-AUD-001 event.
 */
final class MarkNotificationRead
{
    public function execute(Notification $notification): void
    {
        if ($notification->read_at !== null) {
            return;
        }

        DB::transaction(function () use ($notification): void {
            /** @var Notification $locked */
            $locked = Notification::query()->whereKey($notification->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->read_at !== null) {
                return;
            }
            $locked->forceFill(['read_at' => now()])->save();
        });
    }
}
