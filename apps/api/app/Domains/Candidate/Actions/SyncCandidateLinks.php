<?php

declare(strict_types=1);

namespace App\Domains\Candidate\Actions;

use App\Domains\Candidate\Models\CandidateLink;

final class SyncCandidateLinks extends SyncCandidateCollection
{
    protected function modelClass(): string { return CandidateLink::class; }
    protected function fields(): array { return ['link_type', 'label', 'url', 'sort_order']; }
    protected function collectionName(): string { return 'links'; }
    protected function orderColumn(): string { return 'sort_order'; }
}
