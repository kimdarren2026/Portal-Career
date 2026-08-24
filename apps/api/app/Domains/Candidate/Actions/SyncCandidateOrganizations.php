<?php

declare(strict_types=1);

namespace App\Domains\Candidate\Actions;

use App\Domains\Candidate\Models\CandidateOrganization;

final class SyncCandidateOrganizations extends SyncCandidateCollection
{
    protected function modelClass(): string { return CandidateOrganization::class; }
    protected function fields(): array { return ['organization_name', 'role_title', 'organization_type', 'start_date', 'end_date', 'is_current', 'description']; }
    protected function collectionName(): string { return 'organizations'; }
    protected function orderColumn(): string { return 'start_date'; }
}
