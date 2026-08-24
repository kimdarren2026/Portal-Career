<?php

declare(strict_types=1);

namespace App\Domains\Candidate\Policies;

use App\Domains\Candidate\Models\CandidateProfile;
use App\Domains\Identity\Enums\RoleCode;
use App\Domains\Identity\Models\User;

/** Own-candidate authorization. Elevated administrative roles never proxy it. */
final class CandidateProfilePolicy
{
    public function view(User $user, CandidateProfile $profile): bool { return $this->ownsCandidateProfile($user, $profile); }
    public function update(User $user, CandidateProfile $profile): bool { return $this->ownsCandidateProfile($user, $profile); }
    public function manage(User $user, CandidateProfile $profile): bool { return $this->ownsCandidateProfile($user, $profile); }

    private function ownsCandidateProfile(User $user, CandidateProfile $profile): bool
    {
        if ((int) $profile->user_id !== (int) $user->getKey()) {
            return false;
        }

        foreach ([RoleCode::CandidateExternal, RoleCode::CandidateStudentFinalYear, RoleCode::CandidateAlumni] as $role) {
            if ($user->hasActiveRole($role)) {
                return true;
            }
        }

        return false;
    }
}
