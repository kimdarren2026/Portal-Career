<?php

declare(strict_types=1);

namespace App\Domains\Company\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Server-side derivation of `companies.slug` (PD-2, approved 26 August 2026):
 * the public route identifier for `GET /api/v1/public/companies/{slug}`.
 *
 * PD-2 semantics: generated once, stored, unique, non-null once assigned,
 * stable after assignment — a company-name edit never regenerates it, and no
 * legal identifier or numeric ID is ever exposed through it. The slug carries
 * NO authorization meaning; public company visibility is governed entirely by
 * the separate public company contract (never by slug format or presence).
 *
 * Mirrors the vacancy identifier's construction (`VacancyIdentifier`):
 * IMPLEMENTATION-LEVEL format only. No FR or contract prescribes the exact
 * shape beyond unique, stable, and route-readable.
 */
final class CompanyIdentifier
{
    public static function slug(string $name): string
    {
        $base = Str::limit(Str::slug($name), 200, '');
        $base = $base === '' ? 'company' : $base;

        // Uniqueness is a schema property, so the discriminator is added
        // unconditionally rather than probed for; probing would race.
        $slug = $base.'-'.Str::lower(Str::random(8));

        while (DB::table('companies')->where('slug', $slug)->exists()) {
            $slug = $base.'-'.Str::lower(Str::random(8));
        }

        return $slug;
    }
}
