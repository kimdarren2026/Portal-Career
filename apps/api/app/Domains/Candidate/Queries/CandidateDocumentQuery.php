<?php

declare(strict_types=1);

namespace App\Domains\Candidate\Queries;

use App\Domains\Candidate\Models\CandidateDocument;
use App\Domains\Candidate\Models\CandidateProfile;
use Illuminate\Support\Facades\DB;

final class CandidateDocumentQuery
{
    /** @return array<string, mixed> */
    public function paginated(CandidateProfile $profile, ?string $type, bool $includeArchived, string $sort, int $page): array
    {
        $query = CandidateDocument::query()->where('candidate_profile_id', $profile->getKey());
        if ($type !== null) { $query->where('document_type', $type); }
        if (! $includeArchived) { $query->whereNull('archived_at'); }
        $direction = $sort === 'display_name' ? 'asc' : 'desc';
        $query->orderBy($sort, $direction)->orderBy('id');
        $documents = $query->paginate(20, ['*'], 'page', $page);

        return [
            'items' => $documents->getCollection()->map(fn (CandidateDocument $document): array => $this->document($document))->all(),
            'pagination' => ['current_page' => $documents->currentPage(), 'last_page' => $documents->lastPage(), 'per_page' => $documents->perPage(), 'total' => $documents->total()],
        ];
    }

    /** @return array<string, mixed> */
    public function document(CandidateDocument $document): array
    {
        return [
            'id' => (int) $document->getKey(),
            'document_type' => $document->document_type,
            'display_name' => $document->display_name,
            'mime_type' => $document->mime_type,
            'size' => (int) $document->size,
            'uploaded_at' => $document->uploaded_at?->toIso8601String(),
            'archived_at' => $document->archived_at?->toIso8601String(),
            'is_shared_with_application' => DB::table('application_documents')->where('candidate_document_id', $document->getKey())->whereNull('revoked_at')->exists(),
        ];
    }
}
