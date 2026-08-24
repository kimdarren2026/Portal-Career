<?php

declare(strict_types=1);

namespace App\Domains\Candidate\Actions;

final class SyncCandidateLinks extends SyncCandidateCollection
{
    protected function fields(): array { return ['link_type', 'label', 'url', 'sort_order']; }
}
