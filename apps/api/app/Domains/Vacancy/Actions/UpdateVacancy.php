<?php

declare(strict_types=1);

namespace App\Domains\Vacancy\Actions;

use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\AuditWriter;
use App\Domains\Vacancy\Exceptions\VacancyNotEditable;
use App\Domains\Vacancy\Exceptions\VacancyStaleVersion;
use App\Domains\Vacancy\Models\Vacancy;
use App\Domains\Vacancy\Models\VacancyVersion;
use App\Domains\Vacancy\Support\VacancyVersionWriter;
use Illuminate\Support\Facades\DB;

/**
 * PATCH /vacancies/{vacancy} (FR-VAC-007).
 *
 * Editing never creates a new vacancy — the same row is revised — and every
 * accepted edit appends exactly one `vacancy_versions` snapshot so the
 * before/after state stays reconstructable. `current_status` is not writable
 * here.
 *
 * The vacancy row is locked for the whole transaction: that lock serialises
 * both the optimistic-concurrency check and version-number allocation, so two
 * concurrent edits cannot claim the same version number.
 */
final class UpdateVacancy
{
    public function __construct(
        private readonly VacancyVersionWriter $versions,
        private readonly AuditWriter $audit,
    ) {}

    /** @param array<string, mixed> $attributes Already validated and field-allow-listed. */
    public function execute(User $actor, Vacancy $vacancy, array $attributes, ?int $expectedVersion = null): Vacancy
    {
        return DB::transaction(function () use ($actor, $vacancy, $attributes, $expectedVersion): Vacancy {
            /** @var Vacancy $locked */
            $locked = Vacancy::query()->whereKey($vacancy->getKey())->lockForUpdate()->firstOrFail();

            if (! $locked->current_status->isCompanyEditable()) {
                throw new VacancyNotEditable('VACANCY_NOT_EDITABLE');
            }

            $currentVersion = (int) VacancyVersion::query()
                ->where('vacancy_id', $locked->getKey())
                ->max('version_number');

            // If-Match on the version: a stale write mutates nothing and appends
            // no version, because the whole Action is one transaction and this
            // throws before any write.
            if ($expectedVersion !== null && $expectedVersion !== $currentVersion) {
                throw new VacancyStaleVersion('STALE_VERSION');
            }

            $locked->fill($attributes);
            $changed = array_keys($locked->getDirty());
            $locked->updated_at = now();
            $locked->save();

            $version = $this->versions->append($locked, $actor);

            $this->audit->record('vacancy_updated', $actor, 'vacancy', (int) $locked->getKey(), [
                'fields' => $changed,
                'version_number' => $version->version_number,
            ]);

            return $locked->refresh();
        });
    }
}
