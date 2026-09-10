<?php

declare(strict_types=1);

namespace App\Domains\Company\Actions;

use App\Domains\Company\Exceptions\CompanyDocumentNotFound;
use App\Domains\Company\Exceptions\CompanyDocumentSupersedeInvalid;
use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\CompanyDocument;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\AuditWriter;
use Illuminate\Support\Facades\DB;

/**
 * `POST /companies/{company}/documents/{document}/supersede` — replaces a
 * document that is already part of a submitted verification package. The
 * predecessor row is retained (INV-038, `chk_company_documents_supersede`);
 * the successor is a freshly uploaded `CompanyDocument`. Both must belong to
 * this company; a predecessor that is already superseded, or still a draft
 * (which should be deleted, not superseded), is rejected.
 */
final class SupersedeCompanyDocument
{
    public function __construct(private readonly AuditWriter $audit) {}

    public function execute(User $actor, Company $company, int $predecessorId, int $successorId): CompanyDocument
    {
        return DB::transaction(function () use ($actor, $company, $predecessorId, $successorId): CompanyDocument {
            /** @var CompanyDocument|null $predecessor */
            $predecessor = CompanyDocument::query()
                ->where('company_id', $company->getKey())->whereKey($predecessorId)->lockForUpdate()->first();
            /** @var CompanyDocument|null $successor */
            $successor = CompanyDocument::query()
                ->where('company_id', $company->getKey())->whereKey($successorId)->lockForUpdate()->first();

            if ($predecessor === null || $successor === null) {
                throw new CompanyDocumentNotFound();
            }

            if ($predecessor->getKey() === $successor->getKey()
                || $predecessor->superseded_at !== null
                || $predecessor->isDraft()
                || $successor->superseded_at !== null
                || $successor->superseded_by_document_id !== null) {
                throw new CompanyDocumentSupersedeInvalid();
            }

            $now = now();
            $predecessor->forceFill([
                'superseded_at' => $now,
                'superseded_by_document_id' => $successor->getKey(),
                'status' => 'SUPERSEDED',
                'updated_at' => $now,
            ])->save();

            $this->audit->record('company_document_superseded', $actor, 'company_document', (int) $predecessor->getKey(), [
                'company_id' => (int) $company->getKey(),
                'superseded_by_document_id' => (int) $successor->getKey(),
            ]);

            return $successor->refresh();
        });
    }
}
