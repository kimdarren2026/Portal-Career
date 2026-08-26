<?php

declare(strict_types=1);

namespace App\Domains\Application\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Server-side derivation of `applications.application_code`: unique, stable
 * after creation. IMPLEMENTATION-LEVEL format only — no FR or contract
 * prescribes the exact shape beyond unique and stable; the illustrative
 * `APP-2026-000123` in API_CONTRACT.md is an example, not a frozen pattern.
 * Mirrors `VacancyIdentifier`/`CompanyIdentifier`'s construction.
 */
final class ApplicationIdentifier
{
    public static function code(): string
    {
        $year = now()->year;
        do {
            $code = sprintf('APP-%d-%s', $year, Str::upper(Str::random(10)));
        } while (DB::table('applications')->where('application_code', $code)->exists());

        return $code;
    }
}
