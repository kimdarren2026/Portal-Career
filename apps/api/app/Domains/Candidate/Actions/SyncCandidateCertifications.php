<?php

declare(strict_types=1);

namespace App\Domains\Candidate\Actions;

use App\Domains\Candidate\Exceptions\CandidateDocumentNotOwnedException;
use App\Domains\Candidate\Exceptions\CandidateInvalidReferenceException;
use App\Domains\Candidate\Models\CandidateCertification;
use App\Domains\Candidate\Models\CandidateDocument;
use App\Domains\Candidate\Models\CandidateProfile;

final class SyncCandidateCertifications extends SyncCandidateCollection
{
    protected function modelClass(): string { return CandidateCertification::class; }
    protected function fields(): array { return ['certification_name', 'issuer_name', 'credential_identifier', 'issued_at', 'expires_at', 'credential_url', 'document_id']; }
    protected function collectionName(): string { return 'certifications'; }
    protected function orderColumn(): string { return 'issued_at'; }
    protected function validateReferences(CandidateProfile $profile, array $items): void
    {
        foreach ($items as $item) {
            if (($id = $item['document_id'] ?? null) === null) { continue; }
            $document = CandidateDocument::query()->find($id);
            if ($document === null) { throw new CandidateInvalidReferenceException('Document does not exist.'); }
            if ((int) $document->candidate_profile_id !== (int) $profile->getKey()) { throw new CandidateDocumentNotOwnedException('Document belongs to another candidate.'); }
        }
    }
}
