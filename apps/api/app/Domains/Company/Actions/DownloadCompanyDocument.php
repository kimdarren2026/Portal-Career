<?php

declare(strict_types=1);

namespace App\Domains\Company\Actions;

use App\Domains\Company\Exceptions\CompanyDocumentNotFound;
use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\CompanyDocument;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\AuditWriter;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * `GET /companies/{company}/documents/{document}/download`. Application-mediated
 * private streaming — no signed/public URL. Company scope is resolved by the
 * caller; the document must belong to that company. Every access is audited as
 * `document_access`. A missing object is a safe 404.
 */
final class DownloadCompanyDocument
{
    public function __construct(
        private readonly AuditWriter $audit,
        private readonly FilesystemFactory $filesystem,
    ) {}

    public function execute(User $actor, Company $company, int $documentId): StreamedResponse
    {
        /** @var CompanyDocument|null $document */
        $document = CompanyDocument::query()
            ->where('company_id', $company->getKey())
            ->whereKey($documentId)
            ->first();

        if ($document === null) {
            $this->audit->record('document_access', $actor, 'company_document', $documentId, ['outcome' => 'not_found']);

            throw new CompanyDocumentNotFound();
        }

        $disk = $this->filesystem->disk((string) config('filesystems.default'));
        if (! $disk->exists((string) $document->storage_reference)) {
            $this->audit->record('document_access', $actor, 'company_document', $documentId, [
                'company_id' => (int) $company->getKey(),
                'outcome' => 'missing',
            ]);

            throw new CompanyDocumentNotFound();
        }

        $this->audit->record('document_access', $actor, 'company_document', $documentId, [
            'company_id' => (int) $company->getKey(),
            'outcome' => 'streamed',
        ]);

        $name = ($document->document_type ?? 'dokumen').'-'.$documentId.'.'.pathinfo((string) $document->storage_reference, PATHINFO_EXTENSION);

        return $disk->download((string) $document->storage_reference, $name, [
            'Content-Type' => $document->mime_type ?: 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'X-Robots-Tag' => 'noindex',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
