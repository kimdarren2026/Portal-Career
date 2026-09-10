<?php

declare(strict_types=1);

namespace App\Domains\Company\Queries;

use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\CompanyDocument;

/**
 * `GET /companies/{company}/documents` — metadata only. The storage reference
 * is never exposed. Company scope is resolved by the caller.
 */
final class ListCompanyDocuments
{
    /** @return list<array<string, mixed>> */
    public function execute(Company $company): array
    {
        return CompanyDocument::query()
            ->where('company_id', $company->getKey())
            ->orderBy('id')
            ->get()
            ->map(fn (CompanyDocument $document): array => [
                'id' => (int) $document->getKey(),
                'document_type' => $document->document_type,
                'document_number' => $document->document_number,
                'original_filename' => $document->original_filename,
                'mime_type' => $document->mime_type,
                'file_size_bytes' => $document->file_size_bytes,
                'issued_at' => $document->issued_at?->toDateString(),
                'expires_at' => $document->expires_at?->toDateString(),
                'status' => $document->status,
                'is_draft' => $document->isDraft(),
                'first_submitted_at' => $document->first_submitted_at?->toIso8601String(),
                'superseded_at' => $document->superseded_at?->toIso8601String(),
                'superseded_by_document_id' => $document->superseded_by_document_id !== null ? (int) $document->superseded_by_document_id : null,
                'created_at' => $document->created_at?->toIso8601String(),
            ])
            ->all();
    }
}
