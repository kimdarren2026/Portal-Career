<?php

declare(strict_types=1);

namespace App\Domains\Candidate\Actions;

use App\Domains\Candidate\Exceptions\CandidateDocumentSignatureInvalidException;
use App\Domains\Candidate\Exceptions\CandidateDocumentPersistenceException;
use App\Domains\Candidate\Exceptions\CandidateDocumentStorageException;
use App\Domains\Candidate\Exceptions\CandidateDocumentUnsupportedMediaTypeException;
use App\Domains\Candidate\Models\CandidateDocument;
use App\Domains\Candidate\Models\CandidateProfile;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\AuditWriter;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/** Stores one private, server-inspected PDF and its metadata. */
final class UploadCandidateDocument
{
    private const PDF_MIME = 'application/pdf';

    public function __construct(
        private readonly AuditWriter $audit,
        private readonly FilesystemFactory $filesystem,
    ) {}

    public function execute(
        User $actor,
        CandidateProfile $profile,
        UploadedFile $file,
        string $documentType,
        ?string $displayName,
    ): CandidateDocument {
        $mimeType = $this->inspectPdf($file);
        $name = $this->sanitizeDisplayName($displayName ?? $file->getClientOriginalName());
        $storageReference = 'candidate-documents/'.$profile->getKey().'/'.Str::ulid().'.pdf';
        $diskName = (string) config('filesystems.default');

        if ($diskName === 'public') {
            throw new CandidateDocumentStorageException('Candidate documents require a private filesystem disk.');
        }

        $disk = $this->filesystem->disk($diskName);
        $this->store($disk, $storageReference, $file);

        try {
            return DB::transaction(function () use ($actor, $profile, $documentType, $name, $storageReference, $mimeType, $file): CandidateDocument {
                $document = new CandidateDocument();
                $document->forceFill([
                    'candidate_profile_id' => $profile->getKey(),
                    'document_type' => $documentType,
                    'display_name' => $name,
                    'storage_reference' => $storageReference,
                    'mime_type' => $mimeType,
                    'size' => (int) $file->getSize(),
                    'uploaded_at' => now(),
                ])->save();

                $this->audit->record(
                    'document_uploaded',
                    $actor,
                    'candidate_document',
                    (int) $document->getKey(),
                    ['document_type' => $documentType, 'size' => (int) $file->getSize(), 'mime_type' => $mimeType],
                );

                return $document;
            });
        } catch (Throwable $exception) {
            try {
                $disk->delete($storageReference);
            } catch (Throwable) {
                Log::warning('Candidate document cleanup failed after persistence rollback.', [
                    'correlation_id' => app()->bound('request') ? request()->attributes->get('correlation_id') : null,
                ]);
            }

            throw new CandidateDocumentPersistenceException('Candidate document metadata could not be persisted.', 0, $exception);
        }
    }

    private function inspectPdf(UploadedFile $file): string
    {
        $path = $file->getRealPath();
        $mimeType = is_string($path) && $path !== ''
            ? (new \finfo(FILEINFO_MIME_TYPE))->file($path)
            : false;

        if ($mimeType !== self::PDF_MIME) {
            throw new CandidateDocumentUnsupportedMediaTypeException();
        }

        $signature = file_get_contents($path, false, null, 0, 5);
        if ($signature !== '%PDF-') {
            throw new CandidateDocumentSignatureInvalidException();
        }

        return $mimeType;
    }

    private function store(object $disk, string $storageReference, UploadedFile $file): void
    {
        $path = $file->getRealPath();
        $stream = is_string($path) && $path !== '' ? fopen($path, 'rb') : false;
        if ($stream === false) {
            throw new CandidateDocumentStorageException('Candidate document temporary file is unavailable.');
        }

        try {
            if (! $disk->put($storageReference, $stream, ['visibility' => 'private'])) {
                throw new CandidateDocumentStorageException('Candidate document could not be stored.');
            }
        } catch (CandidateDocumentStorageException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new CandidateDocumentStorageException('Candidate document could not be stored.', 0, $exception);
        } finally {
            fclose($stream);
        }
    }

    private function sanitizeDisplayName(string $value): string
    {
        $value = str_replace(['/', '\\'], ' ', $value);
        $value = preg_replace('/(?:\.\.)+/u', '', $value) ?? '';
        $value = preg_replace('/[\x00-\x1F\x7F]/u', '', $value) ?? '';
        $value = preg_replace('/[\x{061C}\x{200E}\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}]/u', '', $value) ?? '';

        if (class_exists(\Normalizer::class)) {
            $value = \Normalizer::normalize($value, \Normalizer::FORM_C) ?: '';
        }

        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');
        $value = mb_substr($value, 0, 255);

        return $value === '' ? 'dokumen.pdf' : $value;
    }
}
