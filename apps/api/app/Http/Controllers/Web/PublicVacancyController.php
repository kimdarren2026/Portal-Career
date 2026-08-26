<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domains\Vacancy\Exceptions\VacancyNotPublic;
use App\Domains\Vacancy\Queries\GetPublicVacancy;
use App\Domains\Vacancy\Queries\ListPublicVacancies;
use App\Domains\Vacancy\Support\PublicVacancyRequestFilters;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Browser-facing public vacancy discovery (ADR-017 — Inertia SSR).
 * Anonymous, no auth/verified.email middleware. Consumes exactly the same
 * ListPublicVacancies / GetPublicVacancy / PublicVacancyRequestFilters used
 * by the VERSIONED_API surface (`Api\PublicVacancyController`) — never a
 * loopback HTTP call to it, and never a second visibility predicate.
 */
final class PublicVacancyController extends Controller
{
    public function index(Request $request, ListPublicVacancies $query): Response
    {
        // Unknown/invalid query parameters are tolerated (dropped or
        // defaulted) rather than hard-rejected here: a browsable public page
        // is not a machine contract, so a stray or stale query-string
        // parameter should never break the page. The filter/sort ALLOW-LIST
        // itself is identical to the API's — nothing ungoverned is honoured.
        $parsed = PublicVacancyRequestFilters::parse($request);

        $vacancies = $query->execute(
            $parsed['filters'],
            $parsed['sort'],
            $parsed['direction'],
            $parsed['perPage'],
        );

        return Inertia::render('public/VacancyList', [
            'items' => $vacancies->items(),
            'pagination' => [
                'page' => $vacancies->currentPage(),
                'per_page' => $vacancies->perPage(),
                'total' => $vacancies->total(),
                'last_page' => $vacancies->lastPage(),
            ],
            'filters' => (object) $parsed['filters'],
            'sort' => $parsed['sort'],
            'direction' => $parsed['direction'],
        ]);
    }

    public function show(string $slug, GetPublicVacancy $query): Response
    {
        try {
            $vacancy = $query->execute($slug);
        } catch (VacancyNotPublic) {
            // Same public-not-found semantics as the API (§10): no hidden
            // reason, no distinguishable status, never 403.
            abort(404);
        }

        return Inertia::render('public/VacancyDetail', ['vacancy' => $vacancy]);
    }
}
