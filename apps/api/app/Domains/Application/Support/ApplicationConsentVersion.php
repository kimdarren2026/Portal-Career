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
}
