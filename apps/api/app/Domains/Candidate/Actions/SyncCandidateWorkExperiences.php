<?php

declare(strict_types=1);

namespace App\Domains\Candidate\Actions;

final class SyncCandidateWorkExperiences extends SyncCandidateCollection
{
    protected function fields(): array { return ['employer_name', 'position_title', 'employment_type', 'start_date', 'end_date', 'is_current', 'description']; }
}
