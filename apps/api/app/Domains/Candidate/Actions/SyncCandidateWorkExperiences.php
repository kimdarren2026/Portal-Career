<?php

declare(strict_types=1);

namespace App\Domains\Candidate\Actions;

use App\Domains\Candidate\Models\CandidateWorkExperience;

final class SyncCandidateWorkExperiences extends SyncCandidateCollection
{
    protected function modelClass(): string { return CandidateWorkExperience::class; }
    protected function fields(): array { return ['employer_name', 'position_title', 'employment_type', 'start_date', 'end_date', 'is_current', 'description']; }
    protected function collectionName(): string { return 'work_experiences'; }
    protected function orderColumn(): string { return 'start_date'; }
}
