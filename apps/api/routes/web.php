<?php

use App\Domains\Candidate\Support\CandidateCollectionRegistry;
use App\Http\Controllers\Auth\AuthenticationController;
use App\Http\Controllers\Auth\AuthPageController;
use App\Http\Controllers\Web\CandidateCollectionController;
use App\Http\Controllers\Web\CandidateDocumentController;
use App\Http\Controllers\Web\CandidatePageController;
use App\Http\Controllers\Web\CandidateProfileController;
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

/*
| Candidate Core — INERTIA_WEB (SPEC-DOC-07, accepted)
|
| Session guard + CSRF, OWN authorization, verified-email gate on every mutation.
| Two inventoried operations are deliberately NOT routed because API_CONTRACT.md
| blocks their implementation, and a route that guesses their rules would be an
| invention rather than a contract:
|   - POST /candidate/verifications  (verification business decision, Part X item 1)
|   - POST /candidate/documents      (CANDIDATE_DOCUMENT_UPLOAD_POLICY_REQUIRED, item 9)
*/
Route::middleware(['auth', 'account.status'])->prefix('candidate')->name('candidate.')->group(function (): void {
    $collections = implode('|', CandidateCollectionRegistry::slugs());

    Route::get('/profile', [CandidateProfileController::class, 'show'])->name('profile.show');
    Route::get('/profile/edit', [CandidatePageController::class, 'profile'])->name('profile.page');
    Route::get('/verifications', [CandidateProfileController::class, 'verifications'])->name('verifications.index');

    Route::get('/documents', [CandidateDocumentController::class, 'index'])->name('documents.index');
    Route::get('/documents/{document}/download', [CandidateDocumentController::class, 'download'])
        ->name('documents.download');

    // Reads stay available to an unverified account; writes do not.
    Route::get('/{collection}', [CandidateCollectionController::class, 'index'])
        ->where('collection', $collections)->name('collections.index');

    Route::middleware('verified.email')->group(function () use ($collections): void {
        Route::patch('/profile', [CandidateProfileController::class, 'update'])->name('profile.update');
        Route::put('/{collection}', [CandidateCollectionController::class, 'sync'])
            ->where('collection', $collections)->name('collections.sync');
        Route::patch('/documents/{document}', [CandidateDocumentController::class, 'update'])
            ->name('documents.update');
        Route::delete('/documents/{document}', [CandidateDocumentController::class, 'destroy'])
            ->name('documents.destroy');
    });
});
