<?php

declare(strict_types=1);

namespace Tests\Feature\Application;

use App\Domains\Application\Support\ApplicationConsentVersion;
use Tests\TestCase;

/**
 * PGC-V1 / PD-E — `APPLICATION_CONSENT_2026_08` canonical text is ratified and
 * the persisted hash is bound to that exact text + version, server-side.
 */
final class ConsentCanonicalTextTest extends TestCase
{
    private const RATIFIED = 'Saya menyetujui data profil, CV, dokumen, dan informasi lamaran yang saya pilih untuk lamaran ini diproses dan dibagikan kepada perusahaan atau unit kampus pemilik lowongan yang saya lamar, hanya untuk keperluan proses rekrutmen dan seleksi. Sistem akan mencatat versi persetujuan, tujuan penggunaan, penerima data, dan waktu persetujuan sebagai bagian dari riwayat lamaran.';

    public function test_the_ratified_text_is_stored_verbatim(): void
    {
        self::assertSame(self::RATIFIED, ApplicationConsentVersion::canonicalText(ApplicationConsentVersion::CURRENT));
        self::assertSame([ApplicationConsentVersion::CURRENT], ApplicationConsentVersion::known());
    }

    public function test_the_hash_is_a_digest_of_the_exact_text_plus_version(): void
    {
        $expected = 'sha256:'.hash('sha256', ApplicationConsentVersion::CURRENT."\n".self::RATIFIED);

        self::assertSame($expected, ApplicationConsentVersion::serverDerivedHash(ApplicationConsentVersion::CURRENT));
    }

    public function test_a_one_character_change_to_the_text_would_change_the_hash(): void
    {
        $tampered = 'sha256:'.hash('sha256', ApplicationConsentVersion::CURRENT."\n".self::RATIFIED.'.');

        self::assertNotSame($tampered, ApplicationConsentVersion::serverDerivedHash(ApplicationConsentVersion::CURRENT));
    }

    public function test_an_unknown_version_has_no_canonical_text(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ApplicationConsentVersion::canonicalText('APPLICATION_CONSENT_9999_99');
    }
}
