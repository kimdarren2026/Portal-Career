<?php

declare(strict_types=1);

namespace App\Domains\Application\Support;

use App\Domains\Application\Exceptions\CandidateNotEligible;
use App\Domains\Candidate\Models\CandidateProfile;
use Illuminate\Support\Facades\DB;

/**
 * Candidate Application Foundation v1 audience eligibility (INV-028).
 * `candidate_verifications` is the **only** admissible basis — neither the
 * candidate's role code nor `current_candidate_type` is accepted as proof.
 *
 * Supported audiences: PUBLIC, ALUMNI_ONLY, FINAL_YEAR_AND_ALUMNI.
 * INTERNAL remains OPEN (AD-3, deferred) — this class never evaluates it as
 * eligible; the caller must reject INTERNAL before reaching here.
 */
final class ApplicationEligibility
{
    /** @throws CandidateNotEligible */
    public static function assert(CandidateProfile $profile, string $targetAudience): void
    {
        $ok = match ($targetAudience) {
            'PUBLIC' => true,
            'ALUMNI_ONLY' => self::hasVerified($profile, 'ALUMNI'),
            'FINAL_YEAR_AND_ALUMNI' => self::hasVerified($profile, 'ALUMNI') || self::hasVerified($profile, 'FINAL_YEAR_STUDENT'),
            default => false,
        };

        if (! $ok) {
            throw new CandidateNotEligible();
        }
    }

    private static function hasVerified(CandidateProfile $profile, string $verificationType): bool
    {
        return DB::table('candidate_verifications')
            ->where('candidate_profile_id', $profile->getKey())
            ->where('verification_type', $verificationType)
            ->where('status', 'VERIFIED')
            ->exists();
    }
}
