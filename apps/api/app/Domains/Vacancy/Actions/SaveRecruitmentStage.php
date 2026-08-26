<?php

declare(strict_types=1);

namespace App\Domains\Vacancy\Actions;

use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\AuditWriter;
use App\Domains\Vacancy\Models\RecruitmentStage;
use App\Domains\Vacancy\Models\Vacancy;
use Illuminate\Support\Facades\DB;

/**
 * POST/PATCH /vacancies/{vacancy}/stages (RS-2, RS-6 — approved and CLOSED).
 *
 * RS-2: no vacancy-status or company-verification gate — the vacancy row is
 * locked only to serialise concurrent stage writes on the same vacancy,
 * never to check editability. A stage belongs to exactly one vacancy
 * (INV-019). There is deliberately no delete path here: a stage referenced
 * anywhere is DB-protected by `ON DELETE RESTRICT`, and deactivation via
 * `active = false` is the only lifecycle mechanism this milestone exposes.
 */
final class SaveRecruitmentStage
{
    public function __construct(private readonly AuditWriter $audit) {}

    /** @param array<string, mixed> $attributes */
    public function create(User $actor, Vacancy $vacancy, array $attributes): RecruitmentStage
    {
        return DB::transaction(function () use ($actor, $vacancy, $attributes): RecruitmentStage {
            /** @var Vacancy $locked */
            $locked = Vacancy::query()->whereKey($vacancy->getKey())->lockForUpdate()->firstOrFail();

            $stage = new RecruitmentStage();
            $stage->fill($attributes);
            $stage->forceFill(['vacancy_id' => $locked->getKey()])->save();

            $this->audit->record('vacancy_stage_changed', $actor, 'vacancy', (int) $locked->getKey(), [
                'stage_id' => (int) $stage->getKey(),
                'change' => 'created',
            ]);

            return $stage;
        });
    }

    /** @param array<string, mixed> $attributes */
    public function update(User $actor, Vacancy $vacancy, RecruitmentStage $stage, array $attributes): RecruitmentStage
    {
        return DB::transaction(function () use ($actor, $vacancy, $stage, $attributes): RecruitmentStage {
            /** @var Vacancy $locked */
            $locked = Vacancy::query()->whereKey($vacancy->getKey())->lockForUpdate()->firstOrFail();

            /** @var RecruitmentStage $lockedStage */
            $lockedStage = RecruitmentStage::query()->whereKey($stage->getKey())->lockForUpdate()->firstOrFail();
            $lockedStage->fill($attributes);
            $changed = array_keys($lockedStage->getDirty());
            $lockedStage->save();

            $this->audit->record('vacancy_stage_changed', $actor, 'vacancy', (int) $locked->getKey(), [
                'stage_id' => (int) $lockedStage->getKey(),
                'change' => 'updated',
                'fields' => $changed,
            ]);

            return $lockedStage->refresh();
        });
    }
}
