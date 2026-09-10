<?php

declare(strict_types=1);

namespace App\Domains\Company\Actions;

use App\Domains\Company\Exceptions\CompanyDocumentNotDraft;
use App\Domains\Company\Exceptions\CompanyDocumentNotFound;
use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\CompanyDocument;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\AuditWriter;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * `DELETE /companies/{company}/documents/{document}` — draft-only (Q-2 /
 * INV-038). A document that has ever formed part of a submitted verification
 * package (`first_submitted_at` not null) is verification evidence and must be
 * replaced through `supersede`, never destructively deleted.
 */
final class DeleteCompanyDocument
{
    public function __construct(
        private readonly AuditWriter $audit,
        private readonly FilesystemFactory $filesystem,
    ) {}

    public function execute(User $actor, Company $company, int $documentId): void
    {
        $storageReference = DB::transaction(function () use ($actor, $company, $documentId): string {
            /** @var CompanyDocument|null $document */
            $document = CompanyDocument::query()
                ->where('company_id', $company->getKey())
                ->whereKey($documentId)
                ->lockForUpdate()
                ->first();

            if ($document === null) {
                throw new CompanyDocumentNotFound();
            }

            if (! $document->isDraft() || $document->superseded_at !== null) {
                throw new CompanyDocumentNotDraft();
            }

            $reference = (string) $document->storage_reference;
            $document->delete();

            $this->audit->record('company_document_deleted', $actor, 'company_document', $documentId, [
                'company_id' => (int) $company->getKey(),
            ]);

            return $reference;
        });

        try {
            $this->filesystem->disk((string) config('filesystems.default'))->delete($storageReference);
        } catch (Throwable) {
            // best effort — orphaned private object is inert
        }
    }
}
