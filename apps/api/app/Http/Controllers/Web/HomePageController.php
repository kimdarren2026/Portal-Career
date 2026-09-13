<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domains\Vacancy\Queries\ListPublicVacancies;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * `GET /` — the public career-portal homepage (PGC-V1 / PD-G).
 *
 * Anonymous, no auth. The only dynamic content is a small window of real
 * `PUBLISHED` public vacancies drawn from the same `ListPublicVacancies`
 * read layer used by `/lowongan` — never a loopback call, never a second
 * visibility predicate. No fabricated statistics, companies, or vacancies;
 * no public metrics; no environment diagnostics. An empty database renders a
 * truthful empty state.
 */
final class HomePageController extends Controller
{
    private const LATEST_LIMIT = 6;

    public function index(ListPublicVacancies $query): Response
    {
        $latest = $query->execute(
            filters: [],
            sort: 'published_at',
            direction: 'desc',
            perPage: self::LATEST_LIMIT,
        );

        return Inertia::render('public/Home', [
            'latest_vacancies' => array_slice($latest->items(), 0, self::LATEST_LIMIT),
        ]);
    }
}
