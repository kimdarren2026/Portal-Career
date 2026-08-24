<?php

declare(strict_types=1);

namespace App\Domains\Candidate\Support;

use App\Domains\Candidate\Exceptions\CandidateProfileRequiredException;
use App\Domains\Candidate\Models\CandidateProfile;
use App\Domains\Identity\Models\User;

/** Single place that turns an authenticated identity into its own candidate shell. */
final class CandidateProfileResolver
{
    /** @throws CandidateProfileRequiredException */
    public function forActor(User $actor): CandidateProfile
    {
        $profile = CandidateProfile::query()->where('user_id', $actor->getKey())->first();

        if (! $profile instanceof CandidateProfile) {
            throw new CandidateProfileRequiredException();
        }

        return $profile;
    }
}
