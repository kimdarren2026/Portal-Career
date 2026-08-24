<?php

declare(strict_types=1);

namespace App\Domains\Candidate\Queries;

use App\Domains\Candidate\Models\CandidateProfile;
use App\Domains\Candidate\Support\CandidatePresenter;

/** GET /candidate/verifications — paired read of the verification contract. */
final class GetCandidateVerifications
{
    /** @return list<array<string, mixed>> */
    public function execute(CandidateProfile $profile): array
    {
        return $profile->verifications()
            ->orderBy('verification_type')->orderBy('id')
            ->get()->map(CandidatePresenter::verification(...))->all();
    }
}
