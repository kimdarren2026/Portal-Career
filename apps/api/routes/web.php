<?php

use App\Http\Controllers\Auth\AuthenticationController;
use App\Http\Controllers\Auth\AuthPageController;
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

// Page delivery only: these routes do not consume a verification or reset token.
Route::get('/login', [AuthPageController::class, 'login'])->name('auth.login.page');
Route::get('/register', [AuthPageController::class, 'register'])->name('auth.register.page');
Route::get('/forgot-password', [AuthPageController::class, 'forgotPassword'])->name('auth.forgot-password.page');
Route::get('/reset-password/{token}', [AuthPageController::class, 'resetPassword'])->name('auth.reset-password.page');
Route::get('/verify-email/{token}', [AuthPageController::class, 'verifyEmail'])->name('auth.verify-email.page');

Route::prefix('auth')->name('auth.')->group(function (): void {
    Route::post('/register/candidate', [AuthenticationController::class, 'registerCandidate'])
        ->middleware('auth.abuse:registration')->name('register.candidate');
    Route::post('/register/recruiter', [AuthenticationController::class, 'registerRecruiter'])
        ->middleware('auth.abuse:registration')->name('register.recruiter');
    Route::post('/verify-email', [AuthenticationController::class, 'verifyEmail'])
        ->middleware('auth.abuse:verify-email')->name('verify-email');
    Route::post('/resend-verification', [AuthenticationController::class, 'resendVerification'])
        ->middleware('auth.abuse:resend-verification')->name('resend-verification');
    Route::post('/login', [AuthenticationController::class, 'login'])
        ->middleware('auth.abuse:login')->name('login');
    Route::post('/forgot-password', [AuthenticationController::class, 'forgotPassword'])
        ->middleware('auth.abuse:forgot-password')->name('forgot-password');
    Route::post('/reset-password', [AuthenticationController::class, 'resetPassword'])
        ->middleware('auth.abuse:reset-password')->name('reset-password');
});

Route::middleware(['auth', 'account.status'])->group(function (): void {
    Route::post('/auth/logout', [AuthenticationController::class, 'logout'])->name('auth.logout');
    Route::get('/me', [AuthenticationController::class, 'me'])->name('auth.me');
    Route::put('/me/password', [AuthenticationController::class, 'changePassword'])->name('auth.password.update');
});
