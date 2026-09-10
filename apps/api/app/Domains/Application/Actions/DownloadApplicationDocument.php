<?php

declare(strict_types=1);

namespace App\Domains\Application\Actions;

use App\Domains\Application\Exceptions\ApplicationDocumentNotFound;
use App\Domains\Application\Models\ApplicationDocument;
use App\Domains\Application\Support\ApplicationDocumentAccess;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\AuditWriter;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * `GET /application-documents/{applicationDocument}/download` (PGC-V1 / PD-A,
 * AUTHORIZATION_MATRIX.md §4.6, INV-032).
 *
 * Serves the **immutable snapshot** captured at share time
 * (`snapshot_storage_reference` / `snapshot_name`) — never the candidate's
 * live `candidate_documents` file. Authorization is resolved from the parent
 * application before any object is retrieved; a revoked share, an out-of-scope
 * object, a non-existent id and a missing stored object are all the same
 * enumeration-safe `404`. Every authorized download and every denied attempt
 * is audited as `document_access`. No public / pre-signed URL is ever issued;
 * the storage reference is never returned.
 */
final class DownloadApplicationDocument
{
    public function __construct(
        private readonly AuditWriter $audit,
        private readonly ApplicationDocumentAccess $access,
        private readonly FilesystemFactory $filesystem,
    ) {}

    public function execute(User $actor, int $applicationDocumentId): StreamedResponse
    {
        /** @var ApplicationDocument|null $share */
        $share = ApplicationDocument::query()->whereKey($applicationDocumentId)->first();

        if ($share === null || $share->revoked_at !== null) {
            $this->recordDenied($actor, $applicationDocumentId, 'not_found');

            throw new ApplicationDocumentNotFound();
        }

        $application = $this->access->resolveApplicationFor($actor, (int) $share->application_id);
        if ($application === null) {
            $this->recordDenied($actor, $applicationDocumentId, 'denied');

            throw new ApplicationDocumentNotFound();
        }

        $disk = $this->filesystem->disk((string) config('filesystems.default'));

        if (! $disk->exists((string) $share->snapshot_storage_reference)) {
            $this->audit->record('document_access', $actor, 'application_document', $applicationDocumentId, [
                'application_id' => (int) $share->application_id,
                'outcome' => 'missing',
            ]);

            throw new ApplicationDocumentNotFound();
        }

        $this->audit->record('document_access', $actor, 'application_document', $applicationDocumentId, [
            'application_id' => (int) $share->application_id,
            'outcome' => 'streamed',
        ]);

        // Always an attachment: a crafted HTML/SVG snapshot must never render
        // in a trusted origin. The stored MIME is not persisted for shares, so
        // the browser is told not to sniff.
        return $disk->download((string) $share->snapshot_storage_reference, $this->safeFilename($share), [
            'Content-Type' => 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'X-Robots-Tag' => 'noindex',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function safeFilename(ApplicationDocument $share): string
    {
        $name = trim((string) $share->snapshot_name);
        $name = preg_replace('/[\x00-\x1F\x7F"\\\\\/]+/', '_', $name) ?? '';
        $name = trim($name);

        return $name === '' ? 'dokumen-lamaran' : mb_substr($name, 0, 200);
    }

    private function recordDenied(User $actor, int $applicationDocumentId, string $outcome): void
    {
        $this->audit->record('document_access', $actor, 'application_document', $applicationDocumentId, [
            'outcome' => $outcome,
        ]);
    }
}
