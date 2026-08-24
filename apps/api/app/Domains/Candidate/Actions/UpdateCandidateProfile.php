<?php

declare(strict_types=1);

namespace App\Domains\Candidate\Actions;

use App\Domains\Candidate\Models\CandidateProfile;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\AuditWriter;
use Illuminate\Support\Facades\DB;

/**
 * PATCH /candidate/profile. `current_candidate_type` is not editable here (INV-028)
 * and `profile_completed_at` is never recomputed (DEFERRED POLICY, Part X item 8).
 */
final class UpdateCandidateProfile
{
    public function __construct(private readonly AuditWriter $audit) {}

    /** @param array<string, mixed> $attributes Already validated and field-allow-listed. */
    public function execute(User $actor, CandidateProfile $profile, array $attributes): CandidateProfile
    {
        return DB::transaction(function () use ($actor, $profile, $attributes): CandidateProfile {
            /** @var CandidateProfile $locked */
            $locked = CandidateProfile::query()->whereKey($profile->getKey())->lockForUpdate()->firstOrFail();

            if (array_key_exists('province_geographic_area_id', $attributes) && $attributes['province_geographic_area_id'] !== null) { $attributes['province'] = null; }
            if (array_key_exists('province', $attributes) && $attributes['province'] !== null && $attributes['province'] !== '') { $attributes['province_geographic_area_id'] = null; }
            if (array_key_exists('city_geographic_area_id', $attributes) && $attributes['city_geographic_area_id'] !== null) { $attributes['city'] = null; }
            if (array_key_exists('city', $attributes) && $attributes['city'] !== null && $attributes['city'] !== '') { $attributes['city_geographic_area_id'] = null; }

            $locked->fill($attributes);
            $changed = array_keys($locked->getDirty());

            if ($changed !== []) {
                $locked->updated_at = now();
                $locked->save();
            }

            // Redacted summary: which fields moved, never their values (FR-AUD-001).
            if ($changed !== []) {
                $this->audit->record(
                    'candidate_profile_updated',
                    $actor,
                    'candidate_profile',
                    (int) $locked->getKey(),
                    ['fields' => $changed],
                );
            }

            return $locked;
        });
    }
}
