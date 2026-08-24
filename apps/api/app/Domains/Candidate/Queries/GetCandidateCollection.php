<?php

declare(strict_types=1);

namespace App\Domains\Candidate\Queries;

use App\Domains\Candidate\Models\CandidateProfile;
use App\Domains\Candidate\Support\CandidateCollectionRegistry;
use App\Domains\Candidate\Support\CandidatePresenter;
use Illuminate\Database\Eloquent\Model;

/** GET /candidate/{collection} — always scoped by the actor's own profile. */
final class GetCandidateCollection
{
    /** @return list<array<string, mixed>> */
    public function execute(CandidateProfile $profile, string $slug): array
    {
        /** @var class-string<Model> $model */
        $model = CandidateCollectionRegistry::modelFor($slug);

        $query = $model::query()->where('candidate_profile_id', $profile->getKey());
        foreach (CandidateCollectionRegistry::orderFor($slug) as $column) {
            $query->orderBy($column);
        }

        return $query->get()->map(CandidatePresenter::collectionRow(...))->all();
    }
}
