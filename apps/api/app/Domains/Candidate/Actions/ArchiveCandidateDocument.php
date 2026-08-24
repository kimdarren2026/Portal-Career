<?php

declare(strict_types=1);

namespace App\Domains\Candidate\Actions;

use App\Domains\Candidate\Models\CandidateDocument;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\AuditWriter;
use Illuminate\Support\Facades\DB;

/**
 * DELETE /candidate/documents/{document}: archive, never a hard delete (INV-032).
 * The object is retained so any application_documents snapshot stays intact.
 */
final class ArchiveCandidateDocument
{
    public function __construct(private readonly AuditWriter $audit) {}

    public function execute(User $actor, CandidateDocument $document): void
    {
        DB::transaction(function () use ($actor, $document): void {
            /** @var CandidateDocument $locked */
            $locked = CandidateDocument::query()->whereKey($document->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->archived_at === null) {
                $locked->forceFill(['archived_at' => now(), 'updated_at' => now()])->save();
                $this->audit->record('document_archived', $actor, 'candidate_document', (int) $locked->getKey());
            }
        });
    }
}
