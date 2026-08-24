<?php

declare(strict_types=1);

namespace App\Domains\Candidate\Actions;

use App\Domains\Candidate\Exceptions\CandidateInvalidReferenceException;
use App\Domains\Candidate\Models\CandidateEducation;
use App\Domains\Candidate\Models\CandidateProfile;
use App\Domains\MasterData\Models\StudyProgram;

final class SyncCandidateEducations extends SyncCandidateCollection
{
    protected function modelClass(): string { return CandidateEducation::class; }
    protected function fields(): array { return ['institution_name', 'study_program_id', 'study_program_name', 'education_level', 'start_date', 'graduation_date', 'graduation_year', 'score_summary']; }
    protected function collectionName(): string { return 'educations'; }
    protected function orderColumn(): string { return 'start_date'; }
    protected function validateReferences(CandidateProfile $profile, array $items): void { foreach ($items as $item) { if (($id = $item['study_program_id'] ?? null) !== null && ! StudyProgram::query()->whereKey($id)->where('active', true)->exists()) { throw new CandidateInvalidReferenceException('Study program is unavailable.'); } } }
}
