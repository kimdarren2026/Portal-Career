<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domains\Candidate\Actions\ArchiveCandidateDocument;
use App\Domains\Candidate\Actions\DownloadCandidateDocument;
use App\Domains\Candidate\Actions\UpdateCandidateDocument;
use App\Domains\Candidate\Models\CandidateDocument;
use App\Domains\Candidate\Queries\ListCandidateDocuments;
use App\Domains\Candidate\Support\CandidatePresenter;
use App\Http\Requests\Candidate\UpdateCandidateDocumentRequest;
use App\Http\Requests\Candidate\ListCandidateDocumentsRequest;
use App\Http\Responses\ContractResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Candidate private documents (FR-CAN-005).
 *
 * Upload is deliberately absent: POST /candidate/documents stays BLOCKED on
 * CANDIDATE_DOCUMENT_UPLOAD_POLICY_REQUIRED (API_CONTRACT.md Part X item 9), because the
 * MIME allowlist and size ceiling are unapproved and must not be invented.
 */
final class CandidateDocumentController extends CandidateController
{
    public function index(ListCandidateDocumentsRequest $request, ListCandidateDocuments $query): JsonResponse
    {
        $profile = $this->ownProfile($request);
        $this->authorizeOrFail($request, 'view', $profile);

        $documents = $query->execute(
            $profile,
            $request->filled('document_type') ? $request->string('document_type')->toString() : null,
            $request->boolean('include_archived'),
            $request->string('sort', 'uploaded_at')->toString(),
        );

        return ContractResponse::success($request, [
            'items' => $documents->items(),
            'pagination' => [
                'page' => $documents->currentPage(),
                'per_page' => $documents->perPage(),
                'total' => $documents->total(),
                'last_page' => $documents->lastPage(),
            ],
        ]);
    }

    public function update(
        UpdateCandidateDocumentRequest $request,
        CandidateDocument $document,
        UpdateCandidateDocument $action,
    ): JsonResponse {
        $this->authorizeOrFail($request, 'update', $document, 'DOCUMENT_NOT_OWNED');

        $updated = $action->execute($this->actor($request), $document, $request->validated());

        return ContractResponse::success($request, CandidatePresenter::document(
            $updated,
            $this->isShared($updated),
        ));
    }

    public function destroy(Request $request, CandidateDocument $document, ArchiveCandidateDocument $action): Response
    {
        $this->authorizeOrFail($request, 'delete', $document, 'DOCUMENT_NOT_OWNED');

        $action->execute($this->actor($request), $document);

        return response()->noContent();
    }

    public function download(
        Request $request,
        CandidateDocument $document,
        DownloadCandidateDocument $action,
    ): StreamedResponse|JsonResponse {
        $actor = $this->actor($request);

        // A denied download is auditable evidence in its own right (FR-AUD-001).
        if (Gate::forUser($actor)->denies('download', $document)) {
            $action->recordDenied($actor, (int) $document->getKey());

            return ContractResponse::error($request, 'DOCUMENT_NOT_OWNED', 403, 'Dokumen ini bukan milik Anda.');
        }

        return $action->execute($actor, $document);
    }

    private function isShared(CandidateDocument $document): bool
    {
        return $document->newQuery()->getConnection()
            ->table('application_documents')
            ->where('candidate_document_id', $document->getKey())
            ->whereNull('revoked_at')
            ->exists();
    }
}
