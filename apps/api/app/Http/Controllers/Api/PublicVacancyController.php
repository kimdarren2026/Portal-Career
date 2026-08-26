<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domains\Vacancy\Queries\GetPublicVacancy;
use App\Domains\Vacancy\Queries\ListPublicVacancies;
use App\Http\Controllers\Controller;
use App\Http\Responses\ContractResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/v1/public/vacancies and .../{slug} — VERSIONED_API, unauthenticated,
 * PUBLIC (API_CONTRACT.md). Every result already passes through
 * PublicVacancyScope; this controller adds no visibility logic of its own.
 */
final class PublicVacancyController extends Controller
{
    public function index(Request $request, ListPublicVacancies $query): JsonResponse
    {
        foreach (array_keys($request->query()) as $parameter) {
            if (! in_array($parameter, [...ListPublicVacancies::FILTERS, 'sort', 'direction', 'page', 'per_page', 'cursor'], true)) {
                return ContractResponse::error($request, 'VALIDATION_FAILED', 422, 'Filter tidak didukung.', [
                    'fields' => [$parameter => ['Filter tidak didukung.']],
                ]);
            }
        }

        $sort = $request->string('sort', 'published_at')->toString();
        if (! in_array($sort, ListPublicVacancies::SORTABLE, true)) {
            return ContractResponse::error($request, 'VALIDATION_FAILED', 422, 'Sort tidak didukung.', [
                'fields' => ['sort' => ['Sort tidak didukung.']],
            ]);
        }

        $perPage = $request->integer('per_page', 20);
        $cursor = $request->string('cursor')->toString();

        $vacancies = $query->execute(
            array_intersect_key($request->query(), array_flip(ListPublicVacancies::FILTERS)),
            $sort,
            $request->string('direction', 'desc')->toString(),
            $perPage > 0 ? $perPage : 20,
            $cursor === '' ? null : $cursor,
        );

        $pagination = method_exists($vacancies, 'currentPage')
            ? [
                'page' => $vacancies->currentPage(),
                'per_page' => $vacancies->perPage(),
                'total' => $vacancies->total(),
                'last_page' => $vacancies->lastPage(),
            ]
            : [
                'per_page' => $vacancies->perPage(),
                'next_cursor' => $vacancies->nextCursor()?->encode(),
                'prev_cursor' => $vacancies->previousCursor()?->encode(),
            ];

        return ContractResponse::success($request, [
            'items' => $vacancies->items(),
            'pagination' => $pagination,
        ]);
    }

    public function show(Request $request, string $slug, GetPublicVacancy $query): JsonResponse
    {
        // VacancyNotPublic (non-existent slug or any failed visibility
        // condition) is handled identically by the global exception mapping —
        // never caught here, so the response shape cannot drift by route.
        return ContractResponse::success($request, $query->execute($slug));
    }
}
