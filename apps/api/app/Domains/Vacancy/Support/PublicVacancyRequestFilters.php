<?php

declare(strict_types=1);

namespace App\Domains\Vacancy\Support;

use App\Domains\Vacancy\Queries\ListPublicVacancies;
use Illuminate\Http\Request;

/**
 * The single request-parsing step for `GET /api/v1/public/vacancies` (JSON)
 * and its Inertia web counterpart — extracts the exact frozen filter
 * allow-list and sort field from a request, identically for both surfaces.
 * Neither surface re-implements this extraction independently.
 */
final class PublicVacancyRequestFilters
{
    /** @return array{filters: array<string, mixed>, sort: string, direction: string, perPage: int, cursor: ?string} */
    public static function parse(Request $request, int $defaultPerPage = 20): array
    {
        $sort = $request->string('sort', 'published_at')->toString();
        $perPage = $request->integer('per_page', $defaultPerPage);
        $cursor = $request->string('cursor')->toString();

        return [
            'filters' => array_intersect_key($request->query(), array_flip(ListPublicVacancies::FILTERS)),
            'sort' => in_array($sort, ListPublicVacancies::SORTABLE, true) ? $sort : 'published_at',
            'direction' => $request->string('direction', 'desc')->toString() === 'asc' ? 'asc' : 'desc',
            'perPage' => $perPage > 0 ? $perPage : $defaultPerPage,
            'cursor' => $cursor === '' ? null : $cursor,
        ];
    }

    /** Every query parameter present must be an allow-listed filter or a paging/sort control. */
    public static function hasUnsupportedParameter(Request $request): ?string
    {
        $allowed = [...ListPublicVacancies::FILTERS, 'sort', 'direction', 'page', 'per_page', 'cursor'];
        foreach (array_keys($request->query()) as $parameter) {
            if (! in_array($parameter, $allowed, true)) {
                return $parameter;
            }
        }

        return null;
    }

    /** True when an explicit `sort` value was supplied and it is not one of the frozen sortable fields. */
    public static function hasInvalidSort(Request $request): bool
    {
        return $request->has('sort') && ! in_array($request->string('sort')->toString(), ListPublicVacancies::SORTABLE, true);
    }
}
