<?php

declare(strict_types=1);

namespace App\Domains\Candidate\Actions;

use App\Domains\Candidate\Exceptions\CandidateInvalidReferenceException;
use App\Domains\Candidate\Models\CandidateProfile;
use App\Domains\MasterData\Models\Skill;

final class SyncCandidateSkills extends SyncCandidateCollection
{
    protected function fields(): array { return ['skill_id', 'proficiency_level']; }
    protected function validateReferences(CandidateProfile $profile, array $items): void { foreach ($items as $item) { if (! Skill::query()->whereKey($item['skill_id'])->where('active', true)->exists()) { throw new CandidateInvalidReferenceException('Skill is unavailable.'); } } }
}
