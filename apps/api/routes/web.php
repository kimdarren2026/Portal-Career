<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Web Routes — INERTIA_WEB surface
|--------------------------------------------------------------------------
| Session-cookie authenticated, CSRF protected, not part of the public
| backward-compatibility promise. Behaviour for every operation is defined by
| docs/api/API_CONTRACT.md regardless of surface, and both surfaces call the
| same Actions and Policies — no business rule is ever implemented twice.
|
| BOOTSTRAP PHASE: no business route exists yet. The single route below proves
| the Laravel → Inertia → Vue → TypeScript → Tailwind → Vite chain is wired.
*/

Route::get('/', function () {
    return Inertia::render('Health', [
        'application' => config('app.name'),
        'laravel' => app()->version(),
        'php' => PHP_VERSION,
        'environment' => app()->environment(),
    ]);
})->name('bootstrap.health');
