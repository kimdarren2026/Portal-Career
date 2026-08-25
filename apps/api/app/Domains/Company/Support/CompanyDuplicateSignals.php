<?php

declare(strict_types=1);

namespace App\Domains\Company\Support;

use App\Domains\Company\Models\Company;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * FR-COMP-001 duplicate detection: flag and review, never hard-reject.
 *
 * INV-034 deliberately leaves `normalized_name` non-unique — a similar name is
 * a review signal, not a rejection. Merging duplicates is a separate authorized
 * capability and is not performed here.
 */
final class CompanyDuplicateSignals
{
    /** @param array<string, mixed> $attributes @return list<string> Signal names that matched. */
    public static function detect(array $attributes, ?int $excludeCompanyId = null): array
    {
        $candidates = [
            'NORMALIZED_NAME' => ['normalized_name', isset($attributes['name']) ? CompanyNameNormalizer::normalize((string) $attributes['name']) : null],
            'LEGAL_IDENTIFIER' => ['legal_identifier', self::text($attributes['legal_identifier'] ?? null)],
            'OFFICIAL_PHONE' => ['official_phone', self::text($attributes['official_phone'] ?? null)],
        ];

        $signals = [];
        foreach ($candidates as $signal => [$column, $value]) {
            if ($value !== null && self::base($excludeCompanyId)->where($column, $value)->exists()) {
                $signals[] = $signal;
            }
        }

        foreach (['WEBSITE_DOMAIN' => ['website', $attributes['website'] ?? null], 'OFFICIAL_EMAIL_DOMAIN' => ['official_email', $attributes['official_email'] ?? null]] as $signal => [$column, $raw]) {
            $domain = self::domain($column, $raw);
            if ($domain !== null && self::base($excludeCompanyId)->whereRaw("lower({$column}) like ?", ['%'.$domain])->exists()) {
                $signals[] = $signal;
            }
        }

        return $signals;
    }

    private static function base(?int $excludeCompanyId): Builder
    {
        return DB::table((new Company())->getTable())
            ->when($excludeCompanyId !== null, fn (Builder $query) => $query->where('id', '<>', $excludeCompanyId));
    }

    private static function text(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : null;

        return ($value === null || $value === '') ? null : $value;
    }

    private static function domain(string $column, mixed $raw): ?string
    {
        $value = self::text($raw);
        if ($value === null) {
            return null;
        }
        $domain = $column === 'official_email'
            ? mb_strtolower((string) mb_strstr($value, '@', false))
            : mb_strtolower((string) parse_url($value, PHP_URL_HOST));
        $domain = ltrim($domain, '@');

        return $domain === '' ? null : $domain;
    }
}
