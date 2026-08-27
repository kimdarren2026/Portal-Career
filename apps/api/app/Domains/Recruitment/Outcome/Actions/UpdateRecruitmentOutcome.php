<?php

declare(strict_types=1);

namespace App\Domains\Recruitment\Outcome\Actions;

use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\AuditWriter;
use App\Domains\Recruitment\Outcome\Models\RecruitmentOutcome;
use Illuminate\Support\Facades\DB;

/**
 * `PATCH /recruitment-outcomes/{outcome}` — authorized correction.
 *
 * The source reference (`source_type`, `application_id`,
 * `external_apply_event_id`) is never editable — an outcome is never
 * reassigned to another application. `confirmed_by`/`confirmed_at` are the
 * original recorder and record time; correction provenance lives only in
 * the audit log (`recruitment_outcome_updated`), never by overwriting them
 * or a history table, neither of which exists for this entity.
 */
final class UpdateRecruitmentOutcome
{
    public function __construct(private readonly AuditWriter $audit) {}

    /** @param array<string, mixed> $attributes */
    public function execute(User $actor, RecruitmentOutcome $outcome, array $attributes): RecruitmentOutcome
    {
        return DB::transaction(function () use ($actor, $outcome, $attributes): RecruitmentOutcome {
            /** @var RecruitmentOutcome $locked */
            $locked = RecruitmentOutcome::query()->whereKey($outcome->getKey())->lockForUpdate()->firstOrFail();

            $changes = [];
            if (array_key_exists('outcome', $attributes)) {
                $changes['outcome'] = $attributes['outcome'];
            }
            if (array_key_exists('reported_by_source', $attributes)) {
                $changes['reported_by_source'] = $attributes['reported_by_source'];
            }
            if (array_key_exists('notes', $attributes)) {
                $changes['notes'] = $attributes['notes'];
            }

            if ($changes !== []) {
                $locked->forceFill($changes)->save();
            }

            $this->audit->record('recruitment_outcome_updated', $actor, 'recruitment_outcome', (int) $locked->getKey(), $changes);

            return $locked->refresh();
        });
    }
}
