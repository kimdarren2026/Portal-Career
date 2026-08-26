<?php

use App\Http\Controllers\Api\PublicCompanyController;
use App\Http\Controllers\Api\PublicReferenceDataController;
use App\Http\Controllers\Api\PublicVacancyController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — VERSIONED_API surface (/api/v1)
|--------------------------------------------------------------------------
| Behaviour: docs/api/API_CONTRACT.md.
|
| Two distinct surfaces share this prefix and must not be confused:
|
|   1. PUBLIC, unauthenticated discovery routes (below). These require no
|      session, no Sanctum bearer token, and no CSRF token — the contract
|      itself says "Authentication: None" for each. Activating them does not
|      require Sanctum.
|
|   2. The Sanctum bearer-token authenticated surface (57 endpoints,
|      docs/api/API_ENDPOINTS.md) remains BOOTSTRAP PHASE / intentionally
|      empty. It activates in a later phase together with Sanctum and its
|      `personal_access_tokens` table (DATABASE_SCHEMA.md §23). Declaring
|      those routes now, before the Actions and Policies they delegate to
|      exist, would create a compatibility promise this repository cannot
|      keep yet.
|
| GET /api/v1/public/companies/{slug} resolves by `companies.slug` (PD-2,
| approved 26 August 2026) — a narrowly scoped, backfilled column added
| specifically to make this already-frozen route resolvable. It returns a
| single company's public summary only, never a directory.
*/
Route::prefix('public')->name('public.')->middleware('public-discovery.rate-limit')->group(function (): void {
    Route::get('/vacancies', [PublicVacancyController::class, 'index'])->name('vacancies.index');
    Route::get('/vacancies/{slug}', [PublicVacancyController::class, 'show'])
        ->where('slug', '[a-z0-9-]+')->name('vacancies.show');
    Route::get('/companies/{slug}', [PublicCompanyController::class, 'show'])
        ->where('slug', '[a-z0-9-]+')->name('companies.show');
    Route::get('/reference-data', [PublicReferenceDataController::class, 'index'])->name('reference-data');
});
