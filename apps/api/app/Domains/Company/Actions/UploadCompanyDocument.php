<?php

declare(strict_types=1);

namespace App\Domains\Company\Actions;

use App\Domains\Company\Exceptions\CompanyDocumentTooLarge;
use App\Domains\Company\Exceptions\CompanyDocumentTypeInvalid;
use App\Domains\Company\Exceptions\CompanyDocumentUnsupportedMediaType;
use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\CompanyDocument;
use App\Domains\Company\Support\CompanyLegalDocumentPolicy;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\AuditWriter;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * `POST /companies/{company}/documents` (FR-ONB-002, PGC-V1 / PD-D).
 *
 * Server-inspected content: the sniffed MIME must be one of `application/pdf`
 * / `image/jpeg` / `image/png` AND the file's leading bytes must match that
 * type's signature — the filename extension is never trusted. Size ceiling is
 * 10 MiB, enforced here (transport ceilings sit above it). The object lands on
 * the private disk under a randomized key; the storage reference is never
 * returned to a client.
 */
final class UploadCompanyDocument
{
    public function __construct(
        private readonly AuditWriter $audit,
        private readonly FilesystemFactory $filesystem,
    ) {}

    public function execute(
        User $actor,
        Company $company,
        UploadedFile $file,
        string $documentType,
        ?string $documentNumber,
        ?string $issuedAt,
        ?string $expiresAt,
    ): CompanyDocument {
        if (! CompanyLegalDocumentPolicy::isAllowedType($documentType)) {
            throw new CompanyDocumentTypeInvalid();
        }

        if ((int) $file->getSize() > CompanyLegalDocumentPolicy::MAX_BYTES) {
            throw new CompanyDocumentTooLarge();
        }

        $mimeType = $this->inspect($file);

        $diskName = (string) config('filesystems.default');
        if ($diskName === 'public') {
            throw new \RuntimeException('Company documents require a private filesystem disk.');
        }
        $disk = $this->filesystem->disk($diskName);

        $storageReference = sprintf(
            'company-documents/%d/%s.%s',
            $company->getKey(),
            (string) Str::ulid(),
            CompanyLegalDocumentPolicy::extensionFor($mimeType),
        );

        $stream = fopen((string) $file->getRealPath(), 'rb');
        if ($stream === false || ! $disk->put($storageReference, $stream, ['visibility' => 'private'])) {
            throw new \RuntimeException('Company document could not be stored.');
        }

        try {
            return DB::transaction(function () use ($actor, $company, $documentType, $documentNumber, $issuedAt, $expiresAt, $storageReference, $mimeType, $file): CompanyDocument {
                $document = new CompanyDocument();
                $document->forceFill([
                    'company_id' => $company->getKey(),
                    'document_type' => $documentType,
                    'document_number' => $documentNumber,
                    'issued_at' => $issuedAt,
                    'expires_at' => $expiresAt,
                    'storage_reference' => $storageReference,
                    'original_filename' => $this->sanitizeName((string) $file->getClientOriginalName()),
                    'mime_type' => $mimeType,
                    'file_size_bytes' => (int) $file->getSize(),
                    'uploaded_by_user_id' => $actor->getKey(),
                    'status' => 'SUBMITTED',
                    'created_at' => now(),
                    'updated_at' => now(),
                ])->save();

                $this->audit->record('company_document_uploaded', $actor, 'company_document', (int) $document->getKey(), [
                    'company_id' => (int) $company->getKey(),
                    'document_type' => $documentType,
                    'mime_type' => $mimeType,
                    'size' => (int) $file->getSize(),
                ]);

                return $document;
            });
        } catch (Throwable $exception) {
            try {
                $disk->delete($storageReference);
            } catch (Throwable) {
                // best effort — the orphaned object is inert on a private disk
            }

            throw $exception;
        }
    }

    private function inspect(UploadedFile $file): string
    {
        $path = (string) $file->getRealPath();
        $sniffed = $path !== '' ? (new \finfo(FILEINFO_MIME_TYPE))->file($path) : false;
        if (! is_string($sniffed) || ! in_array($sniffed, CompanyLegalDocumentPolicy::mimeTypes(), true)) {
            throw new CompanyDocumentUnsupportedMediaType();
        }

        $leading = (string) file_get_contents($path, false, null, 0, 16);
        if (! CompanyLegalDocumentPolicy::contentMatches($sniffed, $leading)) {
            throw new CompanyDocumentUnsupportedMediaType();
        }

        return $sniffed;
    }

    private function sanitizeName(string $name): string
    {
        $name = preg_replace('/[\x00-\x1F\x7F"\\\\\/]+/', '_', trim($name)) ?? '';

        return $name === '' ? 'dokumen' : mb_substr($name, 0, 200);
    }
}
