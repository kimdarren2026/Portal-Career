<?php

declare(strict_types=1);

namespace App\Domains\Application\Support;

use App\Domains\Application\Models\Application;
use App\Domains\Candidate\Support\CandidateProfileResolver;
use App\Domains\Identity\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Candidate `OWN` scope for `GET /applications` and `GET /applications/{id}`
 * — query-scoped, never filtered after fetch (API_CONTRACT.md: "This is the
 * endpoint where a Policy alone is insufficient"). Foundation v1 implements
 * the candidate `OWN` scope only; COMPANY_SCOPE/CAMPUS_SCOPE/ASSIGNED_STAGE/
 * Auditor scopes belong to the Recruiter Applicant Management phase.
 */
final class ApplicationScope
{
    public function __construct(private readonly CandidateProfileResolver $profiles) {}

    public function queryFor(User $user): Builder
    {
        $profile = $this->profiles->forActor($user);

        return Application::query()->where('candidate_profile_id', $profile->getKey());
    }

    public function findFor(User $user, int $applicationId): ?Application
    {
        return $this->queryFor($user)->whereKey($applicationId)->first();
    }
}
