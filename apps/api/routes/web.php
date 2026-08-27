<?php

use App\Domains\Candidate\Support\CandidateCollectionRegistry;
use App\Http\Controllers\Auth\AuthenticationController;
use App\Http\Controllers\Auth\AuthPageController;
use App\Http\Controllers\Web\ApplicationController;
use App\Http\Controllers\Web\CandidateCollectionController;
use App\Http\Controllers\Web\CandidateDocumentController;
use App\Http\Controllers\Web\CandidatePageController;
use App\Http\Controllers\Web\CandidateProfileController;
use App\Http\Controllers\Web\CompanyController;
use App\Http\Controllers\Web\CompanyMemberController;
use App\Http\Controllers\Web\PublicVacancyController;
use App\Http\Controllers\Web\RecruitmentStageController;
use App\Http\Controllers\Web\SelectionScheduleController;
use App\Http\Controllers\Web\VacancyController;
use App\Http\Controllers\Web\VacancyLifecycleController;
use App\Http\Controllers\Web\VacancyScreeningQuestionController;
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

/*
| Public Vacancy Discovery — browser-facing, Inertia SSR (ADR-017).
| Anonymous: no auth, no verified.email. Consumes the same read layer as the
| VERSIONED_API public surface (routes/api.php) — never a loopback call to
| it. `/lowongan` mirrors the canonical Stitch screen names
| (design/stitch/public/daftar-lowongan, .../detail-lowongan) and the site's
| established Indonesian-language public vocabulary; the path itself is a
| technical routing choice — FSD/Stitch fix the terminology and screens, not
| a URL shape, and no existing route claims this prefix.
*/
Route::get('/lowongan', [PublicVacancyController::class, 'index'])->name('lowongan.index');
Route::get('/lowongan/{slug}', [PublicVacancyController::class, 'show'])
    ->where('slug', '[a-z0-9-]+')->name('lowongan.show');

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

Route::middleware(['auth', 'account.status'])->prefix('companies')->name('companies.')->group(function (): void {
    Route::post('/', [CompanyController::class, 'create'])->middleware('verified.email')->name('store');
    Route::get('/{company}', [CompanyController::class, 'show'])->name('show');
    Route::get('/{company}/members', [CompanyMemberController::class, 'index'])->name('members.index');
    Route::middleware('verified.email')->group(function (): void {
        Route::patch('/{company}', [CompanyController::class, 'update'])->name('update');
        Route::post('/{company}/submit-verification', [CompanyController::class, 'submit'])->name('submit');
        // FR-COMP-004 + closed D-1. The last active COMPANY_ADMIN can never be
        // demoted or revoked; leaving is the member's own act.
        Route::post('/{company}/members', [CompanyMemberController::class, 'store'])
            ->middleware('company.member-invite-rate')->name('members.store');
        Route::patch('/{company}/members/{member}', [CompanyMemberController::class, 'update'])->name('members.update');
        Route::delete('/{company}/members/{member}', [CompanyMemberController::class, 'destroy'])->name('members.destroy');
    });
    Route::post('/{company}/{action}', [CompanyController::class, 'review'])
        ->where('action', 'verify|request-revision|reject|suspend|restore')
        ->name('review');
});

/*
| Candidate Core — INERTIA_WEB (SPEC-DOC-07, accepted)
|
| Session guard + CSRF, OWN authorization, verified-email gate on every mutation.
| POST /candidate/verifications remains deliberately unrouted because API_CONTRACT.md
| blocks its implementation. Candidate document upload is implemented under
| candidate-document-upload-policy-v1.
|   - POST /candidate/verifications  (verification business decision, Part X item 1)
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

    Route::middleware(['candidate.document-upload-rate', 'verified.email'])->group(function (): void {
        Route::post('/documents', [CandidateDocumentController::class, 'store'])->name('documents.store');
    });
});

/*
| Candidate Application Foundation v1 — INERTIA_WEB
|
| Session guard + CSRF, candidate OWN authorization, verified-email gate on
| every mutation — the same pattern already established for Candidate Core.
| API_CONTRACT.md documents these operations as VERSIONED_API, but the
| Sanctum-authenticated /api/v1 surface remains BOOTSTRAP PHASE / intentionally
| empty (routes/api.php) exactly as it does for every other business domain
| (Company Onboarding, Vacancy Authoring/Moderation) — this is a transport
| mapping, not a business-behaviour change, and follows the identical
| precedent those domains already set.
|
| reopen is deliberately NOT routed: AD-2 remains OPEN and deferred.
|
| Recruiter Applicant Management Foundation v1 (RA-1, RA-2, approved and
| CLOSED) extends index/show to COMPANY_SCOPE and SUPER_ADMIN and adds
| transition. No email-verified gate on transition: its contract states
| "Authentication: Required" without one, the same as vacancy moderation.
| move-stage is routed by Application Stage Movement Foundation v1 (MS-3,
| MS-4, approved and CLOSED). bulk-transition and document download are
| deliberately NOT routed — out of scope (RA-3 defers download).
|
| Selection Schedule Foundation v1 (SS-1, SS-2, SS-3, SS-5, SS-8, SS-9,
| approved and CLOSED) adds schedule create (nested here) plus the
| /schedules routes below. No email-verified gate: the schedule contract
| states "Authentication: Required" without one, same as transition/move-stage.
*/
Route::middleware(['auth', 'account.status'])->group(function (): void {
    Route::post('/vacancies/{vacancy}/applications', [ApplicationController::class, 'store'])
        ->whereNumber('vacancy')->middleware('verified.email')->name('vacancies.applications.store');

    Route::prefix('applications')->name('applications.')->group(function (): void {
        Route::get('/', [ApplicationController::class, 'index'])->name('index');
        Route::get('/{application}', [ApplicationController::class, 'show'])->whereNumber('application')->name('show');
        Route::post('/{application}/withdraw', [ApplicationController::class, 'withdraw'])
            ->whereNumber('application')->middleware('verified.email')->name('withdraw');
        Route::post('/{application}/transition', [ApplicationController::class, 'transition'])
            ->whereNumber('application')->name('transition');
        Route::post('/{application}/move-stage', [ApplicationController::class, 'moveStage'])
            ->whereNumber('application')->name('move-stage');
        Route::post('/{application}/schedules', [SelectionScheduleController::class, 'store'])
            ->whereNumber('application')->name('schedules.store');
    });

    Route::prefix('schedules')->name('schedules.')->group(function (): void {
        Route::get('/', [SelectionScheduleController::class, 'index'])->name('index');
        Route::get('/{schedule}', [SelectionScheduleController::class, 'show'])->whereNumber('schedule')->name('show');
        Route::get('/{schedule}/history', [SelectionScheduleController::class, 'history'])->whereNumber('schedule')->name('history');
        Route::patch('/{schedule}', [SelectionScheduleController::class, 'reschedule'])->whereNumber('schedule')->name('reschedule');
        Route::post('/{schedule}/cancel', [SelectionScheduleController::class, 'cancel'])->whereNumber('schedule')->name('cancel');
    });
});

/*
| Company Vacancy Authoring Foundation — INERTIA_WEB
|
| Authoring only: create, edit, owner list/read, append-only version history and
| the screening-question surface. Every lifecycle transition beyond DRAFT
| authoring is deliberately unrouted, because each depends on an unresolved
| decision: approve target state, restore target state, the Super Admin
| moderation actor set, the publish actor, and the submit completeness gate.
| No placeholder route exists for any of them.
*/
Route::middleware(['auth', 'account.status'])->group(function (): void {
    Route::post('/companies/{company}/vacancies', [VacancyController::class, 'store'])
        ->middleware('verified.email')->name('companies.vacancies.store');

    Route::prefix('vacancies')->name('vacancies.')->group(function (): void {
        Route::get('/', [VacancyController::class, 'index'])->name('index');
        Route::get('/{vacancy}', [VacancyController::class, 'show'])->whereNumber('vacancy')->name('show');
        Route::get('/{vacancy}/versions', [VacancyController::class, 'versions'])->whereNumber('vacancy')->name('versions');
        Route::get('/{vacancy}/screening-questions', [VacancyScreeningQuestionController::class, 'index'])
            ->whereNumber('vacancy')->name('screening-questions.index');
        Route::get('/{vacancy}/stages', [RecruitmentStageController::class, 'index'])
            ->whereNumber('vacancy')->name('stages.index');

        Route::middleware('verified.email')->group(function (): void {
            Route::patch('/{vacancy}', [VacancyController::class, 'update'])->whereNumber('vacancy')->name('update');
            Route::post('/{vacancy}/screening-questions', [VacancyScreeningQuestionController::class, 'store'])
                ->whereNumber('vacancy')->name('screening-questions.store');
            Route::patch('/{vacancy}/screening-questions/{question}', [VacancyScreeningQuestionController::class, 'update'])
                ->whereNumber('vacancy')->whereNumber('question')->name('screening-questions.update');

            // Recruitment Stage Authoring Foundation v1 (RS-2, RS-6 — approved
            // and CLOSED). No vacancy-status/company-verification gate; only
            // VacancyPolicy::manageStages governs every action here.
            Route::post('/{vacancy}/stages', [RecruitmentStageController::class, 'store'])
                ->whereNumber('vacancy')->name('stages.store');
            Route::patch('/{vacancy}/stages/{stage}', [RecruitmentStageController::class, 'update'])
                ->whereNumber('vacancy')->whereNumber('stage')->name('stages.update');
            Route::post('/{vacancy}/stages/reorder', [RecruitmentStageController::class, 'reorder'])
                ->whereNumber('vacancy')->name('stages.reorder');

            // Submit's contract requires a verified email; it is an owner action.
            Route::post('/{vacancy}/submit-review', [VacancyLifecycleController::class, 'submit'])
                ->whereNumber('vacancy')->name('submit-review');

        });

        /*
        | Moderation and manual close (B-1 … B-4). Their contracts state
        | "Authentication: Required" without an email-verified gate, so none is
        | added here.
        |
        | There is deliberately NO publish route: a company vacancy reaches
        | PUBLISHED only through approval inside its active window or through the
        | scheduler (B-4), so no actor publishes one directly and no company
        | vacancy can bypass moderation.
        */
        Route::post('/{vacancy}/request-revision', [VacancyLifecycleController::class, 'requestRevision'])
            ->whereNumber('vacancy')->name('request-revision');
        Route::post('/{vacancy}/reject', [VacancyLifecycleController::class, 'reject'])
            ->whereNumber('vacancy')->name('reject');
        Route::post('/{vacancy}/approve', [VacancyLifecycleController::class, 'approve'])
            ->whereNumber('vacancy')->name('approve');
        Route::post('/{vacancy}/suspend', [VacancyLifecycleController::class, 'suspend'])
            ->whereNumber('vacancy')->name('suspend');
        Route::post('/{vacancy}/restore', [VacancyLifecycleController::class, 'restore'])
            ->whereNumber('vacancy')->name('restore');
        Route::post('/{vacancy}/close', [VacancyLifecycleController::class, 'close'])
            ->whereNumber('vacancy')->name('close');
    });
});
