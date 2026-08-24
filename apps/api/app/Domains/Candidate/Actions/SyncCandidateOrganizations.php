<?php

declare(strict_types=1);

namespace App\Domains\Candidate\Actions;

final class SyncCandidateOrganizations extends SyncCandidateCollection
{
    protected function fields(): array { return ['organization_name', 'role_title', 'organization_type', 'start_date', 'end_date', 'is_current', 'description']; }
}
