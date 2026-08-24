<?php

declare(strict_types=1);

namespace App\Domains\Candidate\Policies;

use App\Domains\Candidate\Models\CandidateDocument;
use App\Domains\Candidate\Models\CandidateProfile;
use App\Domains\Identity\Models\User;

final class CandidateDocumentPolicy
{
    public function __construct(private readonly CandidateProfilePolicy $profiles) {}

    public function view(User $user, CandidateDocument $document): bool { return $this->owns($user, $document); }
    public function update(User $user, CandidateDocument $document): bool { return $this->owns($user, $document); }
    public function delete(User $user, CandidateDocument $document): bool { return $this->owns($user, $document); }
    public function download(User $user, CandidateDocument $document): bool { return $this->owns($user, $document); }

    private function owns(User $user, CandidateDocument $document): bool
    {
        $profile = $document->relationLoaded('candidateProfile')
            ? $document->candidateProfile
            : CandidateProfile::query()->find($document->candidate_profile_id);

        return $profile instanceof CandidateProfile && $this->profiles->manage($user, $profile);
    }
}
