<?php

declare(strict_types=1);

namespace App\Domains\MasterData\Queries;

use Illuminate\Support\Facades\DB;

/**
 * Read side of `GET /api/v1/admin/master-data/{collection}` (API_CONTRACT.md
 * Part IX, `INERTIA_WEB`, `SUPER_ADMIN`). This is the READ-ONLY reference
 * surface only: write operations (create / update / deactivate) stay
 * `DEFERRED` beyond MVP (`API_SIZE_REVIEW.md` DF-1) and no route exists for
 * them.
 *
 * Unlike `GetPublicReferenceData` this returns **active and inactive** rows so
 * an administrator can see the full institutional configuration, and it selects
 * exactly the columns `DATA_DICTIONARY.md` defines for each collection — no
 * invented field, no invented vocabulary.
 */
final class ListMasterDataCollection
{
    /** The six frozen collection slugs, in navigation order. */
    public const COLLECTIONS = [
        'organizational-units',
        'study-programs',
        'industries',
        'organization-types',
        'skills',
        'geographic-areas',
    ];

    private const MAX_ROWS = 5000;

    /** @var array<string, array{table: string, columns: list<string>, hierarchical: bool}> */
    private const MAP = [
        'organizational-units' => [
            'table' => 'organizational_units',
            'columns' => ['id', 'parent_unit_id', 'code', 'name', 'active', 'created_at', 'updated_at'],
            'hierarchical' => true,
        ],
        'study-programs' => [
            'table' => 'study_programs',
            'columns' => ['id', 'code', 'name', 'organizational_unit_id', 'active', 'created_at', 'updated_at'],
            'hierarchical' => false,
        ],
        'industries' => [
            'table' => 'industries',
            'columns' => ['id', 'code', 'name', 'active', 'created_at', 'updated_at'],
            'hierarchical' => false,
        ],
        'organization-types' => [
            'table' => 'organization_types',
            'columns' => ['id', 'code', 'name', 'active', 'created_at', 'updated_at'],
            'hierarchical' => false,
        ],
        'skills' => [
            'table' => 'skills',
            'columns' => ['id', 'name', 'normalized_name', 'description', 'active', 'created_at', 'updated_at'],
            'hierarchical' => false,
        ],
        'geographic-areas' => [
            'table' => 'geographic_areas',
            'columns' => ['id', 'parent_geographic_area_id', 'code', 'name', 'area_type', 'active', 'created_at', 'updated_at'],
            'hierarchical' => true,
        ],
    ];

    public static function isCollection(string $slug): bool
    {
        return array_key_exists($slug, self::MAP);
    }

    /**
     * @return array{
     *     collection: string,
     *     hierarchical: bool,
     *     columns: list<string>,
     *     rows: list<array<string, mixed>>,
     *     total: int,
     *     active_total: int
     * }
     */
    public function execute(string $slug): array
    {
        $config = self::MAP[$slug] ?? throw new \InvalidArgumentException("Unknown master-data collection: {$slug}");

        $rows = DB::table($config['table'])
            ->select($config['columns'])
            ->orderByDesc('active')
            ->orderBy('name')
            ->orderBy('id')
            ->limit(self::MAX_ROWS)
            ->get()
            ->map(static fn ($row): array => (array) $row)
            ->values()
            ->all();

        return [
            'collection' => $slug,
            'hierarchical' => $config['hierarchical'],
            'columns' => $config['columns'],
            'rows' => $rows,
            'total' => count($rows),
            'active_total' => count(array_filter($rows, static fn (array $r): bool => (bool) ($r['active'] ?? false))),
        ];
    }
}
