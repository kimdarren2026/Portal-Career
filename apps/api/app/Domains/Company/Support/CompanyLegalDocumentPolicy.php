<?php

declare(strict_types=1);

namespace App\Domains\Company\Support;

/**
 * The MVP company legal-document policy (PGC-V1 / PD-D). Closed vocabularies
 * and limits — no per-organization-type mandatory matrix, no expiry gate.
 */
final class CompanyLegalDocumentPolicy
{
    /** Maximum file size: 10 MiB. */
    public const MAX_BYTES = 10_485_760;

    /** @var array<string, string> allowed `document_type` => Indonesian label */
    public const TYPES = [
        'NIB' => 'NIB',
        'AKTA_PENDIRIAN' => 'Akta Pendirian',
        'SK_KEMENKUMHAM' => 'SK Kemenkumham',
        'IZIN_OPERASIONAL' => 'Izin Operasional',
        'DOKUMEN_LEGALITAS_LAINNYA' => 'Dokumen Legalitas Lainnya',
    ];

    /** @var array<string, list<string>> allowed MIME => leading magic-byte signatures */
    public const MIME_SIGNATURES = [
        'application/pdf' => ['%PDF-'],
        'image/jpeg' => ["\xFF\xD8\xFF"],
        'image/png' => ["\x89PNG\r\n\x1a\n"],
    ];

    /** @return list<string> */
    public static function types(): array
    {
        return array_keys(self::TYPES);
    }

    /** @return list<string> */
    public static function mimeTypes(): array
    {
        return array_keys(self::MIME_SIGNATURES);
    }

    public static function isAllowedType(string $type): bool
    {
        return array_key_exists($type, self::TYPES);
    }

    /** True only when the sniffed MIME is allowed AND the file's leading bytes match one of its signatures. */
    public static function contentMatches(string $sniffedMime, string $leadingBytes): bool
    {
        $signatures = self::MIME_SIGNATURES[$sniffedMime] ?? null;
        if ($signatures === null) {
            return false;
        }

        foreach ($signatures as $signature) {
            if (str_starts_with($leadingBytes, $signature)) {
                return true;
            }
        }

        return false;
    }

    public static function extensionFor(string $mime): string
    {
        return match ($mime) {
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            default => 'bin',
        };
    }
}
