<?php

declare(strict_types=1);

namespace App\Domains\Partnership\Support;

use Illuminate\Support\Facades\DB;

/**
 * The single `mitra_kampus_active` derivation (INV-003, INV-020): an ACTIVE
 * partnership row within its period. Never a company status field — company
 * verification and partnership are frozen as two separate concepts.
 */
final class PartnershipStatus
{
    public static function isActiveFor(int $companyId): bool
    {
        return self::activeIds([$companyId]) !== [];
    }

    /**
     * Batch lookup for N companies in one query, so listing surfaces never
     * issue one partnership query per row.
     *
     * @param list<int> $companyIds
     * @return array<int, bool>
     */
    public static function batchActive(array $companyIds): array
    {
        $active = array_flip(self::activeIds($companyIds));

        return array_reduce($companyIds, static function (array $carry, int $id) use ($active): array {
            $carry[$id] = isset($active[$id]);

            return $carry;
        }, []);
    }

    /** @param list<int> $companyIds @return list<int> */
    private static function activeIds(array $companyIds): array
    {
        if ($companyIds === []) {
            return [];
        }

        return DB::table('partnerships')
            ->select('company_id')
            ->whereIn('company_id', $companyIds)
            ->where('status', 'ACTIVE')
            ->where('start_date', '<=', now()->toDateString())
            ->where(function ($q): void {
                $q->whereNull('end_date')->orWhere('end_date', '>=', now()->toDateString());
            })
            ->distinct()
            ->pluck('company_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }
}
