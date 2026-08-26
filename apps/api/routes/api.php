<?php

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
| GET /api/v1/public/companies/{slug} is deliberately NOT declared here: the
| frozen `companies` table has no `slug` column (DATA_DICTIONARY.md), so the
| endpoint as literally specified cannot be implemented without a schema
| change. See docs/api/API_CONTRACT.md for the recorded gap. Vacancy cards and
| detail carry an embedded public company summary instead, which needs no
| company slug lookup.
*/
Route::prefix('public')->name('public.')->middleware('public-discovery.rate-limit')->group(function (): void {
    Route::get('/vacancies', [PublicVacancyController::class, 'index'])->name('vacancies.index');
    Route::get('/vacancies/{slug}', [PublicVacancyController::class, 'show'])
        ->where('slug', '[a-z0-9-]+')->name('vacancies.show');
    Route::get('/reference-data', [PublicReferenceDataController::class, 'index'])->name('reference-data');
});
