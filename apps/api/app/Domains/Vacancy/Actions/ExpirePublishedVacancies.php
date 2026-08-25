<?php

declare(strict_types=1);

namespace App\Domains\Vacancy\Actions;

use App\Domains\Identity\Support\AuditWriter;
use App\Domains\Vacancy\Enums\VacancyStatus;
use App\Domains\Vacancy\Models\Vacancy;
use Illuminate\Support\Facades\DB;

/**
 * Automatic vacancy expiry (O-7, FR-VAC-008): a PUBLISHED company vacancy whose
 * active period has ended becomes EXPIRED.
 *
 * `close_at` is an EXCLUSIVE end boundary — `now < close_at` is still active,
 * `now == close_at` and later reach expiry eligibility. That is the same
 * boundary B-1 uses to refuse approval and B-2 uses to resolve a restore.
 *
 * Only PUBLISHED company vacancies expire here. A SUSPENDED vacancy past
 * `close_at` is left alone: B-2 remains authoritative, and an explicit restore
 * resolves it to CLOSED. EXPIRED is terminal — no restore or reopen exists.
 *
 * Expiry writes the status and nothing else. `published_at` is preserved
 * (INV-013), `closed_at` is NOT written because expiry is not a close,
 * `suspended_at` is left as it stands, applications and their history are
 * untouched, and neither a moderation-review row nor a version snapshot is
 * appended: expiry is neither a moderation decision nor an authored revision.
 *
 * There is no notification. FSD v1.1 does not define expiry as a notification
 * trigger, so no outbox or in-app row is created solely because of it.
 *
 * Each vacancy expires in its own transaction under its own row lock, with the
 * status and date re-checked after the lock, so two concurrent runs cannot both
 * transition it and a repeated run writes no duplicate audit entry.
 */
final class ExpirePublishedVacancies
{
    public function __construct(private readonly AuditWriter $audit) {}

    /** @return int Number of vacancies expired by this run. */
    public function execute(): int
    {
        $due = Vacancy::query()
            ->where('ownership_type', 'COMPANY')
            ->where('current_status', VacancyStatus::Published->value)
            ->whereNotNull('close_at')
            ->where('close_at', '<=', now())
            ->orderBy('id')
            ->pluck('id');

        $expired = 0;

        foreach ($due as $id) {
            $expired += DB::transaction(function () use ($id): int {
                /** @var Vacancy|null $locked */
                $locked = Vacancy::query()->whereKey($id)->lockForUpdate()->first();

                // Re-checked under the lock: a concurrent run, a suspension or a
                // manual close may have moved this row already.
                if ($locked === null
                    || $locked->current_status !== VacancyStatus::Published
                    || $locked->close_at === null
                    || $locked->close_at > now()) {
                    return 0;
                }

                $locked->current_status = VacancyStatus::Expired;
                $locked->updated_at = now();
                $locked->save();

                // System actor: the existing no-actor audit representation, never
                // a synthetic user identity.
                $this->audit->record('vacancy_expired', null, 'vacancy', (int) $locked->getKey(), [
                    'from_status' => VacancyStatus::Published->value,
                    'to_status' => VacancyStatus::Expired->value,
                    'close_at' => $locked->close_at?->toIso8601String(),
                ]);

                return 1;
            });
        }

        return $expired;
    }
}
