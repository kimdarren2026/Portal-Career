<?php

declare(strict_types=1);

namespace App\Domains\Company\Support;

/**
 * The single company-name normalization algorithm.
 *
 * `companies.normalized_name` is a duplicate-detection signal only and is
 * deliberately NOT unique (INV-034).
 */
final class CompanyNameNormalizer
{
    public static function normalize(string $name): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/', ' ', $name)));
    }
}
