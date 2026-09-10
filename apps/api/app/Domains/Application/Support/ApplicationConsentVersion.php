<?php

declare(strict_types=1);

namespace App\Domains\Application\Support;

/**
 * The known published consent-version identifiers a submit request may cite
 * (rule 6: "consent_version must be a known published version"). This is
 * IMPLEMENTATION-LEVEL infrastructure only — a version-identifier registry
 * required to make the frozen validation rule enforceable at all. It invents
 * no consent text/content; the text itself and its full versioning policy
 * remain outside this milestone.
 */
final class ApplicationConsentVersion
{
    public const CURRENT = 'APPLICATION_CONSENT_2026_08';

    /** @return list<string> */
    public static function known(): array
    {
        return [self::CURRENT];
    }

    /**
     * The authoritative `consent_text_hash_reference` for a known version —
     * derived SERVER-SIDE and never taken from the request.
     *
     * The vulnerability this closes: the client used to hand over an
     * arbitrary string that was persisted verbatim as the "proof" of which
     * consent text was shown. A random value could therefore become the
     * trusted record. The server now owns this reference outright.
     *
     * No approved consent *document* exists yet (§14 open questions / the
     * class note above), so this is not yet a digest of ratified wording —
     * it is a deterministic function of the version identifier the server
     * already owns. When the approved text is ratified, replace the hashed
     * input with a digest of that document; the persisted value stays
     * server-authoritative either way. Callers must treat any unknown
     * version as already rejected before calling this.
     */
    public static function serverDerivedHash(string $version): string
    {
        return 'sha256:'.hash('sha256', 'APPLICATION_CONSENT_TEXT_REFERENCE::'.$version);
    }
}
