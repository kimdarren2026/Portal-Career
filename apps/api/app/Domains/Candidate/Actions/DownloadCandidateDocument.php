<?php

declare(strict_types=1);

namespace App\Domains\Candidate\Actions;

use App\Domains\Candidate\Models\CandidateDocument;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\AuditWriter;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * GET /candidate/documents/{document}/download.
 *
 * Policy check happens in the controller, then: audit write -> stream. The file is
 * always served through the application; no durable or pre-signed URL is ever
 * returned, because a direct object fetch would bypass FR-AUD-001 auditing (ADR-007).
 */
final class DownloadCandidateDocument
{
    public function __construct(
        private readonly AuditWriter $audit,
        private readonly FilesystemFactory $filesystem,
    ) {}

    public function execute(User $actor, CandidateDocument $document): StreamedResponse
    {
        $disk = $this->filesystem->disk(config('filesystems.default'));

        if (! $disk->exists($document->storage_reference)) {
            $this->audit->record(
                'document_access',
                $actor,
                'candidate_document',
                (int) $document->getKey(),
                ['outcome' => 'missing'],
            );

            throw (new ModelNotFoundException())->setModel(CandidateDocument::class, [$document->getKey()]);
        }

        $this->audit->record(
            'document_access',
            $actor,
            'candidate_document',
            (int) $document->getKey(),
            ['outcome' => 'streamed'],
        );

        // Never inline: a crafted HTML or SVG upload must not execute in a trusted origin.
        return $disk->download($document->storage_reference, $document->display_name, [
            'Content-Type' => $document->mime_type,
            'X-Robots-Tag' => 'noindex',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /** Denied attempts are audited too (FR-AUD-001). */
    public function recordDenied(User $actor, int $documentId): void
    {
        $this->audit->record(
            'document_access',
            $actor,
            'candidate_document',
            $documentId,
            ['outcome' => 'denied'],
        );
    }
}
