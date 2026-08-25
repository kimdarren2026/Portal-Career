<?php

declare(strict_types=1);

namespace App\Domains\Vacancy\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Server-side derivation of the two identifier columns the schema requires but
 * no contract describes: `vacancies.vacancy_code` ("Stable vacancy reference")
 * and `vacancies.slug` ("Public route/readable identifier"), both NOT NULL and
 * UNIQUE (`DATA_DICTIONARY.md`).
 *
 * IMPLEMENTATION-LEVEL ONLY. No FR, contract, or error code prescribes a
 * format, and none is invented here beyond satisfying the frozen properties:
 * unique, stable, and route-readable. Both values are derived mechanically and
 * carry no business meaning — a later approved format may replace this without
 * changing any operation, route, or payload shape.
 */
final class VacancyIdentifier
{
    public static function code(): string
    {
        return 'VAC-'.Str::upper(Str::ulid()->toBase32());
    }

    public static function slug(string $title): string
    {
        $base = Str::limit(Str::slug($title), 200, '');
        $base = $base === '' ? 'vacancy' : $base;

        // Uniqueness is a frozen column property, so the discriminator is added
        // unconditionally rather than probed for; probing would race.
        $slug = $base.'-'.Str::lower(Str::random(8));

        while (DB::table('vacancies')->where('slug', $slug)->exists()) {
            $slug = $base.'-'.Str::lower(Str::random(8));
        }

        return $slug;
    }
}
