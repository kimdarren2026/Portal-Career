<?php

declare(strict_types=1);

namespace App\Domains\Candidate\Actions;

use App\Domains\Candidate\Models\CandidateDocument;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\AuditWriter;
use Illuminate\Support\Facades\DB;

/** PATCH /candidate/documents/{document} — metadata only. Stored content is never replaced. */
final class UpdateCandidateDocument
{
    public function __construct(private readonly AuditWriter $audit) {}

    /** @param array<string, mixed> $attributes `display_name` and `document_type` only. */
    public function execute(User $actor, CandidateDocument $document, array $attributes): CandidateDocument
    {
        return DB::transaction(function () use ($actor, $document, $attributes): CandidateDocument {
            /** @var CandidateDocument $locked */
            $locked = CandidateDocument::query()->whereKey($document->getKey())->lockForUpdate()->firstOrFail();

            $locked->fill($attributes);
            $changed = array_keys($locked->getDirty());

            if ($changed !== []) {
                $locked->updated_at = now();
                $locked->save();
            }

            if ($changed !== []) {
                $this->audit->record(
                    'document_metadata_updated',
                    $actor,
                    'candidate_document',
                    (int) $locked->getKey(),
                    ['fields' => $changed],
                );
            }

            return $locked;
        });
    }
}
