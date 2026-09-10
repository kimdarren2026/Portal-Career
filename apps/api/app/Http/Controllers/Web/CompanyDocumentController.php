<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domains\Company\Actions\DeleteCompanyDocument;
use App\Domains\Company\Actions\DownloadCompanyDocument;
use App\Domains\Company\Actions\SupersedeCompanyDocument;
use App\Domains\Company\Actions\UploadCompanyDocument;
use App\Domains\Company\Exceptions\CompanyNotFound;
use App\Domains\Company\Models\Company;
use App\Domains\Company\Queries\ListCompanyDocuments;
use App\Domains\Company\Support\CompanyScope;
use App\Domains\Identity\Models\User;
use App\Http\Controllers\Controller;
use App\Http\Requests\Company\UploadCompanyDocumentRequest;
use App\Http\Responses\ContractResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Company legal-document runtime (FR-ONB-002, PGC-V1 / PD-D,
 * AUTHORIZATION_MATRIX.md §4.3).
 *
 *  - upload / delete (draft) / supersede — write: `CompanyPolicy::update`
 *    (an active member of that company, or Super Admin). Career Center and
 *    Auditor are DENIED here.
 *  - list / download — read: `CompanyPolicy::view` (member, or the global
 *    readers Career Center / Auditor / Super Admin — review visibility).
 *
 * Company scope is query-resolved (`CompanyScope`) so an out-of-scope company
 * is a 404, never a 403 that confirms the row exists.
 */
final class CompanyDocumentController extends Controller
{
    /** `GET /companies/{company}/documents`. */
    public function index(Request $request, int $company, ListCompanyDocuments $query): JsonResponse
    {
        $model = $this->scoped($request, $company);
        if (Gate::forUser($request->user())->denies('view', $model)) {
            return $this->forbidden($request);
        }

        return ContractResponse::success($request, ['items' => $query->execute($model)]);
    }

    /** `POST /companies/{company}/documents`. */
    public function store(UploadCompanyDocumentRequest $request, int $company, UploadCompanyDocument $action): JsonResponse
    {
        $model = $this->scoped($request, $company);
        /** @var User $actor */
        $actor = $request->user();
        if (Gate::forUser($actor)->denies('update', $model)) {
            return $this->forbidden($request);
        }

        $document = $action->execute(
            $actor,
            $model,
            $request->file('file'),
            $request->string('document_type')->toString(),
            $request->filled('document_number') ? $request->string('document_number')->toString() : null,
            $request->filled('issued_at') ? $request->string('issued_at')->toString() : null,
            $request->filled('expires_at') ? $request->string('expires_at')->toString() : null,
        );

        return ContractResponse::success($request, [
            'id' => (int) $document->getKey(),
            'document_type' => $document->document_type,
            'status' => $document->status,
        ], 201);
    }

    /** `GET /companies/{company}/documents/{document}/download`. */
    public function download(Request $request, int $company, int $document, DownloadCompanyDocument $action): StreamedResponse|JsonResponse
    {
        $model = $this->scoped($request, $company);
        /** @var User $actor */
        $actor = $request->user();
        if (Gate::forUser($actor)->denies('view', $model)) {
            return $this->forbidden($request);
        }

        return $action->execute($actor, $model, $document);
    }

    /** `DELETE /companies/{company}/documents/{document}`. */
    public function destroy(Request $request, int $company, int $document, DeleteCompanyDocument $action): JsonResponse
    {
        $model = $this->scoped($request, $company);
        /** @var User $actor */
        $actor = $request->user();
        if (Gate::forUser($actor)->denies('update', $model)) {
            return $this->forbidden($request);
        }

        $action->execute($actor, $model, $document);

        return ContractResponse::success($request, ['deleted' => true]);
    }

    /** `POST /companies/{company}/documents/{document}/supersede`. */
    public function supersede(Request $request, int $company, int $document, SupersedeCompanyDocument $action): JsonResponse
    {
        $model = $this->scoped($request, $company);
        /** @var User $actor */
        $actor = $request->user();
        if (Gate::forUser($actor)->denies('update', $model)) {
            return $this->forbidden($request);
        }

        $successorId = (int) $request->integer('successor_document_id');
        if ($successorId <= 0) {
            return ContractResponse::error($request, 'VALIDATION_FAILED', 422, 'Data yang dikirim tidak valid.', [
                'fields' => ['successor_document_id' => ['ID dokumen pengganti wajib diisi.']],
            ]);
        }

        $successor = $action->execute($actor, $model, $document, $successorId);

        return ContractResponse::success($request, [
            'id' => (int) $successor->getKey(),
            'superseded_document_id' => $document,
        ]);
    }

    private function scoped(Request $request, int $companyId): Company
    {
        return CompanyScope::findFor($request->user(), $companyId) ?? throw new CompanyNotFound();
    }

    private function forbidden(Request $request): JsonResponse
    {
        return ContractResponse::error($request, 'AUTH_FORBIDDEN', 403, 'Anda tidak berhak melakukan tindakan ini.');
    }
}
