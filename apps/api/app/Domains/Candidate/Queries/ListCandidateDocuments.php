<?php

declare(strict_types=1);

namespace App\Domains\Candidate\Queries;

use App\Domains\Candidate\Models\CandidateDocument;
use App\Domains\Candidate\Models\CandidateProfile;
use App\Domains\Candidate\Support\CandidatePresenter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * GET /candidate/documents — query-scoped by `candidate_profile_id`, never merely
 * Policy-checked, so another candidate's document cannot appear in the result set.
 */
final class ListCandidateDocuments
{
    private const SORTABLE = ['uploaded_at', 'display_name'];
    private const PER_PAGE = 20;

    public function execute(
        CandidateProfile $profile,
        ?string $documentType = null,
        bool $includeArchived = false,
        string $sort = 'uploaded_at',
        string $direction = 'desc',
    ): LengthAwarePaginator {
        $sort = in_array($sort, self::SORTABLE, true) ? $sort : 'uploaded_at';
        $direction = $direction === 'asc' ? 'asc' : 'desc';

        $documents = CandidateDocument::query()
            ->where('candidate_profile_id', $profile->getKey())
            ->when($documentType !== null, fn ($query) => $query->where('document_type', $documentType))
            ->when(! $includeArchived, fn ($query) => $query->whereNull('archived_at'))
            ->orderBy($sort, $direction)
            ->orderBy('id')
            ->paginate(self::PER_PAGE);

        $shared = $this->sharedDocumentIds($documents->getCollection()->modelKeys());

        return $documents->through(fn (CandidateDocument $document): array => CandidatePresenter::document(
            $document,
            in_array((int) $document->getKey(), $shared, true),
        ));
    }

    /** @param list<int|string> $documentIds @return list<int> */
    private function sharedDocumentIds(array $documentIds): array
    {
        if ($documentIds === []) {
            return [];
        }

        return array_map('intval', DB::table('application_documents')
            ->whereIn('candidate_document_id', $documentIds)
            ->whereNull('revoked_at')
            ->distinct()
            ->pluck('candidate_document_id')->all());
    }
}
