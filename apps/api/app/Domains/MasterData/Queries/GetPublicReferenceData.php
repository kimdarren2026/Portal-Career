<?php

declare(strict_types=1);

namespace App\Domains\MasterData\Queries;

use Illuminate\Support\Facades\DB;

/**
 * GET /api/v1/public/reference-data (API_CONTRACT.md line 2412) — active-only
 * master data for public filter controls. Each category is bounded (no
 * unbounded nested-tree materialization) and carries only the fields a public
 * filter needs: id, code where it exists, and name.
 */
final class GetPublicReferenceData
{
    private const MAX_ROWS = 2000;

    /** @return array<string, list<array<string, mixed>>> */
    public function execute(): array
    {
        return [
            'study_programs' => $this->rows('study_programs', ['id', 'code', 'name']),
            'industries' => $this->rows('industries', ['id', 'code', 'name']),
            'organization_types' => $this->rows('organization_types', ['id', 'code', 'name']),
            'geographic_areas' => $this->rows('geographic_areas', ['id', 'parent_geographic_area_id', 'code', 'name', 'area_type']),
            'skills' => $this->rows('skills', ['id', 'name']),
        ];
    }

    /** @param list<string> $columns @return list<array<string, mixed>> */
    private function rows(string $table, array $columns): array
    {
        return DB::table($table)
            ->select($columns)
            ->where('active', true)
            ->orderBy('name')
            ->orderBy('id')
            ->limit(self::MAX_ROWS)
            ->get()
            ->map(static fn ($row): array => (array) $row)
            ->values()
            ->all();
    }
}
