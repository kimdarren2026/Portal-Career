<?php

declare(strict_types=1);

namespace App\Domains\Application\Support;

use InvalidArgumentException;

/**
 * The published application-consent versions and their authoritative canonical
 * text. Ratified by Product Owner decision PGC-V1 / PD-E
 * (`docs/decisions/PRODUCT_OWNER_DECISIONS.md`).
 *
 * The server is authoritative:
 *  - `known()` gates which `consent_version` a submit request may cite;
 *  - `canonicalText()` is the exact ratified wording for a version;
 *  - `serverDerivedHash()` is derived from that text + the version identifier
 *    and is the ONLY value persisted as `consents.consent_text_hash_reference`.
 *    A client-supplied hash is never authoritative.
 *
 * Changing punctuation or wording requires a NEW version constant + entry — a
 * historical version's text is never altered in place.
 */
final class ApplicationConsentVersion
{
    public const CURRENT = 'APPLICATION_CONSENT_2026_08';

    /**
     * Canonical ratified consent text per version (PGC-V1 / PD-E). The bytes
     * here are the authoritative record — edit only by adding a new version.
     *
     * @var array<string, string>
     */
    private const TEXT = [
        self::CURRENT => 'Saya menyetujui data profil, CV, dokumen, dan informasi lamaran yang saya pilih untuk lamaran ini diproses dan dibagikan kepada perusahaan atau unit kampus pemilik lowongan yang saya lamar, hanya untuk keperluan proses rekrutmen dan seleksi. Sistem akan mencatat versi persetujuan, tujuan penggunaan, penerima data, dan waktu persetujuan sebagai bagian dari riwayat lamaran.',
    ];

    /** @return list<string> */
    public static function known(): array
    {
        return array_keys(self::TEXT);
    }

    /** The exact ratified consent text for a known version. */
    public static function canonicalText(string $version): string
    {
        return self::TEXT[$version] ?? throw new InvalidArgumentException('Unknown consent version.');
    }

    /**
     * The authoritative `consent_text_hash_reference` — a digest of the exact
     * canonical text bound to its version. Derived server-side; the client's
     * value is discarded. Callers must reject an unknown version before
     * calling this.
     */
    public static function serverDerivedHash(string $version): string
    {
        return 'sha256:'.hash('sha256', $version."\n".self::canonicalText($version));
    }
}
