<?php

use App\Domains\Candidate\Support\CandidateCollectionRegistry;
use App\Http\Controllers\Auth\AuthenticationController;
use App\Http\Controllers\Auth\AuthPageController;
use App\Http\Controllers\Web\AccountSettingsPageController;
use App\Http\Controllers\Web\ApplicationController;
use App\Http\Controllers\Web\CandidateApplicationPageController;
use App\Http\Controllers\Web\CandidateCollectionController;
use App\Http\Controllers\Web\CandidateDocumentController;
use App\Http\Controllers\Web\CandidatePageController;
use App\Http\Controllers\Web\CandidateProfileController;
use App\Http\Controllers\Web\CareerCenterPageController;
use App\Http\Controllers\Web\CompanyController;
use App\Http\Controllers\Web\CompanyMemberController;
use App\Http\Controllers\Web\CompanyMemberPageController;
use App\Http\Controllers\Web\CompanyPageController;
use App\Http\Controllers\Web\HrPageController;
use App\Http\Controllers\Web\HrVacancyController;
use App\Http\Controllers\Web\NotificationController;
use App\Http\Controllers\Web\NotificationPageController;
use App\Http\Controllers\Web\DashboardPageController;
use App\Http\Controllers\Web\EvaluationController;
use App\Http\Controllers\Web\OfferController;
use App\Http\Controllers\Web\PublicVacancyController;
use App\Http\Controllers\Web\RecruiterApplicantPageController;
use App\Http\Controllers\Web\RecruiterVacancyPageController;
use App\Http\Controllers\Web\RecruitmentOutcomeController;
use App\Http\Controllers\Web\RecruitmentOutcomePageController;
use App\Http\Controllers\Web\RecruitmentStageController;
use App\Http\Controllers\Web\SelectionScheduleController;
use App\Http\Controllers\Web\SelectionSchedulePageController;
use App\Http\Controllers\Web\SmtpConfigurationController;
use App\Http\Controllers\Web\SuperAdminPageController;
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
| BOOTSTRAP PHASE: no business route exists yet. '/' redirects to the public
| vacancy listing; the bootstrap health check moved to /bootstrap-health so it
| no longer occupies the root path.
*/

Route::redirect('/', '/lowongan');

Route::get('/bootstrap-health', function () {
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

    /*
    | In-app notification centre (API_CONTRACT.md Part IX · FSD §4.2).
    | Reclassified VERSIONED_API → INERTIA_WEB for the MVP browser portal by
    | an approved Product Owner / SPEC-DOC decision (Part X decision row) — a
    | transport classification only; OWN authorization and every business
    | rule are unchanged. `GET /notifications` is the JSON contract shape
    | (paginated + `meta.unread_count`); the `/notifikasi` Inertia page below
    | is its browser realization. No email-verified gate — reading one's own
    | notifications carries none. No idempotency header — mark-read is
    | naturally idempotent.
    */
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'read'])
        ->whereNumber('notification')->name('notifications.read');
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
|
| Evaluation / Scoring Foundation v1 (EV-1, EV-2, RC-1, approved and CLOSED)
| adds evaluation create/list (nested here) plus the /evaluations routes
| below. No email-verified gate, same rationale as schedules. Evaluations
| are never candidate-visible — no candidate route exists for this domain.
|
| Offering Foundation v1 (OF-1, OF-2, RC-1, approved and CLOSED) adds offer
| create (nested here) plus the /offers routes below. No email-verified
| gate. accept/reject are OWN-only candidate actions with no proxy for any
| other actor, including SUPER_ADMIN (OF-2).
|
| Recruitment Outcome Foundation v1 (OC-1, RC-2, H-5, approved and CLOSED)
| adds the /recruitment-outcomes routes below, including .../incomplete
| (H-5). INTERNAL_APPLICATION only — CAMPUS_SCOPE and Career Center's
| alumni/reporting grant (RC-2) remain deferred/inactive. No candidate route
| exists. incomplete is read-only reporting: it never creates an outcome,
| mutates an application, or sends a notification.
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
        Route::get('/{application}/evaluations', [EvaluationController::class, 'index'])
            ->whereNumber('application')->name('evaluations.index');
        Route::post('/{application}/evaluations', [EvaluationController::class, 'store'])
            ->whereNumber('application')->name('evaluations.store');
        Route::post('/{application}/offers', [OfferController::class, 'store'])
            ->whereNumber('application')->name('offers.store');
    });

    Route::prefix('schedules')->name('schedules.')->group(function (): void {
        Route::get('/', [SelectionScheduleController::class, 'index'])->name('index');
        Route::get('/{schedule}', [SelectionScheduleController::class, 'show'])->whereNumber('schedule')->name('show');
        Route::get('/{schedule}/history', [SelectionScheduleController::class, 'history'])->whereNumber('schedule')->name('history');
        Route::patch('/{schedule}', [SelectionScheduleController::class, 'reschedule'])->whereNumber('schedule')->name('reschedule');
        Route::post('/{schedule}/cancel', [SelectionScheduleController::class, 'cancel'])->whereNumber('schedule')->name('cancel');
    });

    Route::prefix('evaluations')->name('evaluations.')->group(function (): void {
        Route::get('/{evaluation}', [EvaluationController::class, 'show'])->whereNumber('evaluation')->name('show');
        Route::patch('/{evaluation}', [EvaluationController::class, 'update'])->whereNumber('evaluation')->name('update');
        Route::post('/{evaluation}/submit', [EvaluationController::class, 'submit'])->whereNumber('evaluation')->name('submit');
    });

    Route::prefix('offers')->name('offers.')->group(function (): void {
        Route::patch('/{offer}', [OfferController::class, 'update'])->whereNumber('offer')->name('update');
        Route::post('/{offer}/send', [OfferController::class, 'send'])->whereNumber('offer')->name('send');
        Route::post('/{offer}/accept', [OfferController::class, 'accept'])->whereNumber('offer')->name('accept');
        Route::post('/{offer}/reject', [OfferController::class, 'reject'])->whereNumber('offer')->name('reject');
    });

    Route::prefix('recruitment-outcomes')->name('recruitment-outcomes.')->group(function (): void {
        Route::get('/', [RecruitmentOutcomeController::class, 'index'])->name('index');
        Route::get('/incomplete', [RecruitmentOutcomeController::class, 'incomplete'])->name('incomplete');
        Route::post('/', [RecruitmentOutcomeController::class, 'store'])->name('store');
        Route::patch('/{outcome}', [RecruitmentOutcomeController::class, 'update'])->whereNumber('outcome')->name('update');
    });

    /*
    | Recruitment Frontend Vertical Slice v1 — Inertia page delivery only.
    | Every page controller here reuses the frozen Query/Scope/Presenter
    | classes above directly (never a loopback HTTP call) and mutates
    | nothing. Mutations still post to the JSON routes already registered
    | above. `pages.` names keep these separate from the JSON route names
    | they render a shell around, so neither can be confused with the other.
    */
    Route::get('/dashboard', [DashboardPageController::class, 'index'])->name('pages.dashboard');

    Route::get('/lamaran-saya', [CandidateApplicationPageController::class, 'index'])->name('pages.applications.index');
    Route::get('/lamaran-saya/{application}', [CandidateApplicationPageController::class, 'show'])
        ->whereNumber('application')->name('pages.applications.show');
    Route::get('/lowongan/{slug}/lamar', [CandidateApplicationPageController::class, 'apply'])
        ->where('slug', '[a-z0-9-]+')->name('pages.applications.apply');

    Route::get('/jadwal-seleksi', [SelectionSchedulePageController::class, 'index'])->name('pages.schedules.index');
    Route::get('/jadwal-seleksi/{schedule}', [SelectionSchedulePageController::class, 'show'])
        ->whereNumber('schedule')->name('pages.schedules.show');

    Route::get('/pelamar', [RecruiterApplicantPageController::class, 'index'])->name('pages.applicants.index');
    Route::get('/pelamar/{application}', [RecruiterApplicantPageController::class, 'show'])
        ->whereNumber('application')->name('pages.applicants.show');

    // Frontend Vertical Slice v2 — activates the canonical "Outcome Rekrutmen"
    // recruiter nav item. Page delivery only; create/correct still post to the
    // JSON `recruitment-outcomes.*` routes above.
    Route::get('/outcome-rekrutmen', [RecruitmentOutcomePageController::class, 'index'])->name('pages.outcomes.index');

    /*
    | Recruiter Company & Vacancy Frontend Slice v3 — Inertia page delivery
    | only. Activates the canonical "Profil Perusahaan", "Status Verifikasi"
    | and "Lowongan" recruiter nav items. Each controller reuses the frozen
    | Company/Vacancy Scope/Query/Presenter classes directly (never a loopback
    | HTTP call) and mutates nothing; every mutation still posts to the JSON
    | routes already registered above (`companies.*`, `vacancies.*`,
    | `companies.vacancies.store`, `vacancies.submit-review`, `vacancies.close`).
    | `/kelola-lowongan` is a private routing choice — the public discovery
    | prefix `/lowongan` is untouched.
    */
    Route::get('/profil-perusahaan', [CompanyPageController::class, 'profile'])->name('pages.company.profile');
    Route::get('/status-verifikasi', [CompanyPageController::class, 'verification'])->name('pages.company.verification');
    Route::get('/kelola-lowongan', [RecruiterVacancyPageController::class, 'index'])->name('pages.vacancies.index');
    Route::get('/kelola-lowongan/baru', [RecruiterVacancyPageController::class, 'create'])->name('pages.vacancies.create');
    Route::get('/kelola-lowongan/{vacancy}', [RecruiterVacancyPageController::class, 'show'])
        ->whereNumber('vacancy')->name('pages.vacancies.show');

    /*
    | Recruiter Dashboard & Member Management Frontend Slice v5 — activates the
    | canonical "Anggota Perusahaan" recruiter nav item (Dashboard was already
    | routed at `/dashboard`). Page delivery only: the read reuses the frozen
    | `company->members()->active()` model presented through
    | `CompanyMemberPresenter`; every membership mutation still posts to the
    | frozen `companies.members.*` JSON routes (FR-COMP-004, closed D-1).
    */
    Route::get('/anggota-perusahaan', [CompanyMemberPageController::class, 'index'])
        ->name('pages.company.members');

    /*
    | Recruiter Account Settings Frontend Slice v6 — activates the canonical
    | "Pengaturan Akun" recruiter nav item. Page delivery only: the read is the
    | frozen `GET /me` shape (self scope); password change posts to the frozen
    | `PUT /me/password`, verification resend to `POST /auth/resend-verification`
    | and sign-out to `POST /auth/logout`. "Notifikasi" stays deferred —
    | MODULE_BLOCKED_NOTIFIKASI (no INERTIA_WEB reclassification, no runtime).
    */
    Route::get('/pengaturan-akun', [AccountSettingsPageController::class, 'index'])
        ->name('pages.account.settings');

    /*
    | Recruiter Notification Frontend Slice v7 — activates the canonical
    | "Notifikasi" recruiter nav item. Page delivery only: the read reuses the
    | frozen ListNotifications / NotificationScope / NotificationPresenter
    | (OWN by user_id); mark-read and mark-all-read post to the frozen
    | `notifications.*` JSON routes above. Dokumen Legalitas and Kemitraan
    | remain deferred (D-6 open; partnership lifecycle under-specified).
    */
    Route::get('/notifikasi', [NotificationPageController::class, 'index'])->name('pages.notifications');

    /*
    | Campus Recruitment Frontend Slice v8 — the Admin Kepegawaian workspace.
    | Non-mutating Inertia page delivery under `/kepegawaian/*`; every read
    | reuses the frozen Query/Scope/Presenter classes (all now CAMPUS_SCOPE-
    | aware, SPEC-DOC-10). Mutations post to the frozen JSON routes
    | (`/hr/vacancies*`, `/applications/*`, `/schedules/*`, `/evaluations/*`,
    | `/offers/*`, `/recruitment-outcomes`, `/notifications/*`). "Laporan"
    | stays deferred (FR-REP-003 / GET /reports has no runtime).
    */
    Route::prefix('kepegawaian')->name('pages.hr.')->group(function (): void {
        Route::get('/dashboard', [HrPageController::class, 'dashboard'])->name('dashboard');
        Route::get('/lowongan-kampus', [HrPageController::class, 'vacancyIndex'])->name('vacancies.index');
        Route::get('/lowongan-kampus/baru', [HrPageController::class, 'vacancyCreate'])->name('vacancies.create');
        Route::get('/lowongan-kampus/{vacancy}', [HrPageController::class, 'vacancyShow'])
            ->whereNumber('vacancy')->name('vacancies.show');
        Route::get('/pelamar', [HrPageController::class, 'applicantIndex'])->name('applicants.index');
        Route::get('/pelamar/{application}', [HrPageController::class, 'applicantShow'])
            ->whereNumber('application')->name('applicants.show');
        Route::get('/jadwal-seleksi', [HrPageController::class, 'scheduleIndex'])->name('schedules.index');
        Route::get('/penilaian', [HrPageController::class, 'evaluationIndex'])->name('evaluations.index');
        Route::get('/offering', [HrPageController::class, 'offerIndex'])->name('offers.index');
        Route::get('/outcome-rekrutmen', [HrPageController::class, 'outcomeIndex'])->name('outcomes.index');
        Route::get('/notifikasi', [HrPageController::class, 'notifications'])->name('notifications');
        Route::get('/pengaturan', [HrPageController::class, 'settings'])->name('settings');
    });

    /*
    | Career Center Company Verification & Vacancy Moderation Frontend Slice v4
    | — Inertia page delivery only. Activates the canonical Career Center nav
    | items "Verifikasi Perusahaan" and "Moderasi Lowongan". Each read reuses
    | the frozen Company/Vacancy Scope/Query classes directly (Career Center is
    | a global reader in both) and mutates nothing; every moderation mutation
    | still posts to the JSON routes already registered above
    | (`companies.review`, `vacancies.approve|request-revision|reject|suspend|
    | restore|close`). No publish route exists — a company vacancy publishes
    | only through approval-in-window or the scheduler (B-4).
    */
    Route::get('/verifikasi-perusahaan', [CareerCenterPageController::class, 'companyIndex'])
        ->name('pages.career-center.companies.index');
    Route::get('/verifikasi-perusahaan/{company}', [CareerCenterPageController::class, 'companyShow'])
        ->whereNumber('company')->name('pages.career-center.companies.show');
    Route::get('/moderasi-lowongan', [CareerCenterPageController::class, 'vacancyIndex'])
        ->name('pages.career-center.vacancies.index');
    Route::get('/moderasi-lowongan/{vacancy}', [CareerCenterPageController::class, 'vacancyShow'])
        ->whereNumber('vacancy')->name('pages.career-center.vacancies.show');

    /*
    | Career Center Frontend Slice v9 — activates the canonical "Data
    | Perusahaan" nav item (read-only company reference directory; Career
    | Center is a global reader in `CompanyScope`). "Dashboard" and
    | "Notifikasi" for the Career Center persona are served by the shared
    | `/dashboard` and `/notifikasi` controllers, persona-branched.
    | Kemitraan, Alumni & Outcome, Laporan, Template Email and Pengaturan
    | Moderasi remain deferred (no frozen runtime).
    */
    Route::get('/data-perusahaan', [CareerCenterPageController::class, 'companyDirectoryIndex'])
        ->name('pages.career-center.companies.directory');
    Route::get('/data-perusahaan/{company}', [CareerCenterPageController::class, 'companyDirectoryShow'])
        ->whereNumber('company')->name('pages.career-center.companies.directory-show');

    /*
    | Super Admin Control Plane Frontend Slice v10 — Inertia page delivery
    | only. Activates the single canonical Super Admin nav item with a frozen
    | runtime: "Audit Log" (FSD §4.6, §5.13 FR-AUD-001; API_CONTRACT.md
    | `GET /api/v1/audit-logs`). Read-only — `audit_logs` is physically
    | append-only and no mutation endpoint exists for any role, Super Admin
    | included (INV-016). Gated to SUPER_ADMIN or AUDITOR (both READ_ONLY,
    | AUTHORIZATION_MATRIX.md §4.9). The remaining eleven Super Admin nav
    | items stay deferred: master-data writes are deferred beyond MVP
    | (API_SIZE_REVIEW.md DF-1), "Jenis Lowongan" is a frozen enum, and
    | Template Workflow / Konfigurasi SMTP runtime / Template Notifikasi /
    | Integrasi / Retensi Data / Pengaturan Sistem have no frozen runtime.
    | "Dashboard" and "Notifikasi" for Super Admin are served by the shared
    | `/dashboard` (redirect) and `/notifikasi` (persona-branched) controllers.
    */
    Route::get('/audit-log', [SuperAdminPageController::class, 'auditLogIndex'])
        ->name('pages.super-admin.audit-log');

    /*
    | Super Admin "Jenis Lowongan" (Frontend Slice v12) — read-only reference
    | over the frozen VacancyType enum (PO decision
    | SUPER_ADMIN_VACANCY_TYPE_REFERENCE_MVP, API_CONTRACT.md Part X item 61).
    | GET only; no create/update/delete/reorder route exists. SUPER_ADMIN only.
    */
    Route::get('/jenis-lowongan', [SuperAdminPageController::class, 'vacancyTypeIndex'])
        ->name('pages.super-admin.vacancy-types');

    /*
    | SMTP Configuration Foundation (FR-NOTIF-005 / ADR-015) — runtime-managed
    | SMTP settings with an application-encrypted, write-only credential
    | (INV-035) and at most one active configuration (INV-036). Contract
    | endpoints already carry `Surface: INERTIA_WEB`; the browser paths are
    | the contract URIs with `/api/v1` removed. SUPER_ADMIN only on every
    | route (Policy-checked); Auditor is denied (matrix §4.9 footnote 33).
    | `/konfigurasi-smtp` is the Super Admin page (Frontend Slice v11); the
    | mutations post to the JSON routes below.
    */
    Route::get('/konfigurasi-smtp', [SuperAdminPageController::class, 'smtpConfigurationIndex'])
        ->name('pages.super-admin.smtp');
    Route::get('/admin/smtp-configuration', [SmtpConfigurationController::class, 'show'])
        ->name('admin.smtp-configuration.show');
    Route::put('/admin/smtp-configuration', [SmtpConfigurationController::class, 'update'])
        ->name('admin.smtp-configuration.update');
    Route::post('/admin/smtp-configuration/test', [SmtpConfigurationController::class, 'test'])
        ->name('admin.smtp-configuration.test');
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

/*
| Campus Recruitment Foundation — INERTIA_WEB.
|
| Activates the previously DEFERRED CAMPUS_SCOPE / HR_ADMIN runtime (approved
| Product Owner / SPEC-DOC decision; see API_CONTRACT.md Part X). BRD/FSD
| already require the Karier di Kampus track (FSD §5.5, FR-HR-001..007, §8.4).
|
| `POST /hr/vacancies` creates a campus vacancy. The five
| `POST /hr/vacancies/{vacancy}/{action}` routes are the campus lifecycle
| (FSD §8.4) — campus vacancies are NOT moderated and never touch Career
| Center. Campus vacancy READ / EDIT / stages / screening reuse the shared
| `/vacancies/*` surface (VacancyScope + VacancyPolicy already carry the
| campus branch). Campus applicant processing, schedule, evaluation, offering
| and outcome reuse their existing `/applications/*`, `/schedules/*`,
| `/evaluations/*`, `/offers/*` and `/recruitment-outcomes/*` routes with the
| CAMPUS_SCOPE branch now active in every scope.
*/
Route::middleware(['auth', 'account.status'])->prefix('hr')->name('hr.')->group(function (): void {
    Route::post('/vacancies', [HrVacancyController::class, 'store'])
        ->middleware('verified.email')->name('vacancies.store');

    Route::prefix('vacancies/{vacancy}')->whereNumber('vacancy')->name('vacancies.')->group(function (): void {
        Route::post('/publish', [HrVacancyController::class, 'publish'])->name('publish');
        Route::post('/schedule', [HrVacancyController::class, 'schedule'])->name('schedule');
        Route::post('/close', [HrVacancyController::class, 'close'])->name('close');
        Route::post('/suspend', [HrVacancyController::class, 'suspend'])->name('suspend');
        Route::post('/restore', [HrVacancyController::class, 'restore'])->name('restore');
    });
});
