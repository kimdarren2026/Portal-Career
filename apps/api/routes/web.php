<?php

use App\Domains\Candidate\Support\CandidateCollectionRegistry;
use App\Http\Controllers\Auth\AuthenticationController;
use App\Http\Controllers\Auth\AuthPageController;
use App\Http\Controllers\Web\AccountSettingsPageController;
use App\Http\Controllers\Web\AdminEmailOutboxController;
use App\Http\Controllers\Web\AdminUserController;
use App\Http\Controllers\Web\AdminUserRoleController;
use App\Http\Controllers\Web\ApplicationController;
use App\Http\Controllers\Web\CandidateApplicationPageController;
use App\Http\Controllers\Web\CandidateCollectionController;
use App\Http\Controllers\Web\CandidateDocumentController;
use App\Http\Controllers\Web\CandidatePageController;
use App\Http\Controllers\Web\CandidateProfileController;
use App\Http\Controllers\Web\CareerCenterPageController;
use App\Http\Controllers\Web\CompanyController;
use App\Http\Controllers\Web\CompanyDocumentController;
use App\Http\Controllers\Web\CompanyMemberController;
use App\Http\Controllers\Web\CompanyMemberPageController;
use App\Http\Controllers\Web\CompanyPageController;
use App\Http\Controllers\Web\HrPageController;
use App\Http\Controllers\Web\HrVacancyController;
use App\Http\Controllers\Web\NotificationController;
use App\Http\Controllers\Web\NotificationPageController;
use App\Http\Controllers\Web\DashboardPageController;
use App\Http\Controllers\Web\ApplicationDocumentController;
use App\Http\Controllers\Web\EvaluationController;
use App\Http\Controllers\Web\HomePageController;
use App\Http\Controllers\Web\ExternalApplyController;
use App\Http\Controllers\Web\OfferController;
use App\Http\Controllers\Web\PublicVacancyController;
use App\Http\Controllers\Web\RecruiterApplicantPageController;
use App\Http\Controllers\Web\RecruiterVacancyPageController;
use App\Http\Controllers\Web\RecruitmentOutcomeController;
use App\Http\Controllers\Web\RecruitmentOutcomePageController;
use App\Http\Controllers\Web\RecruitmentStageController;
use App\Http\Controllers\Web\SelectionScheduleController;
use App\Http\Controllers\Web\SelectionSchedulePageController;
use App\Http\Controllers\Web\SelectorAssignmentController;
use App\Http\Controllers\Web\SmtpConfigurationController;
use App\Http\Controllers\Web\SuperAdminPageController;
use App\Http\Controllers\Web\VacancyController;
use App\Http\Controllers\Web\VacancyReportController;
use App\Http\Controllers\Web\VacancyLifecycleController;
use App\Http\Controllers\Web\VacancyScreeningQuestionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes — INERTIA_WEB surface
|--------------------------------------------------------------------------
| Session-cookie authenticated, CSRF protected, not part of the public
| backward-compatibility promise. Behaviour for every operation is defined by
| docs/api/API_CONTRACT.md regardless of surface, and both surfaces call the
| same Actions and Policies — no business rule is ever implemented twice.
*/

/*
| Public homepage (PGC-V1 / PD-G). Anonymous. Renders a real minimal career
| portal landing page — fixed hero copy, quick pathways to filtered
| `/lowongan`, up to six real latest PUBLISHED vacancies with a truthful empty
| state, auth CTAs. No fabricated data, no public metrics, and NO environment
| diagnostics (the former bootstrap Health page is gone; health lives only at
| /health/live, /health/ready, /up).
*/
Route::get('/', [HomePageController::class, 'index'])->name('home');

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

/*
| Laporkan Lowongan — public anti-fraud vacancy reporting (PGC-V1 / PD-C,
| API_CONTRACT.md Part X item 64). Anonymous OR authenticated; an authenticated
| reporter's id is attached server-side. CSRF applies (web group); `store` is
| rate-limited (5/IP/hour anonymous, 10/account/day authenticated). A
| non-public / non-existent slug is an enumeration-safe 404.
*/
Route::get('/lowongan/{vacancy}/laporkan', [VacancyReportController::class, 'create'])
    ->where('vacancy', '[a-z0-9-]+')->name('vacancy-reports.create');
Route::post('/lowongan/{vacancy}/laporkan', [VacancyReportController::class, 'store'])
    ->where('vacancy', '[a-z0-9-]+')->middleware('vacancy-report-rate')->name('vacancy-reports.store');

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

    /*
    | Company legal documents (FR-ONB-002, PGC-V1 / PD-D — closes API_CONTRACT.md
    | Part X item 2 / adds item 65). Read (list, download) is CompanyPolicy::view
    | (member, or global readers Career Center / Auditor / Super Admin); write
    | (upload, draft delete, supersede) is CompanyPolicy::update (member or Super
    | Admin - Career Center DENIED). Draft-only delete + supersede preserve the
    | frozen Q-2 rule (INV-038).
    */
    Route::get('/{company}/documents', [CompanyDocumentController::class, 'index'])
        ->whereNumber('company')->name('documents.index');
    Route::get('/{company}/documents/{document}/download', [CompanyDocumentController::class, 'download'])
        ->whereNumber('company')->whereNumber('document')->name('documents.download');

    Route::middleware('verified.email')->group(function (): void {
        Route::patch('/{company}', [CompanyController::class, 'update'])->name('update');
        Route::post('/{company}/submit-verification', [CompanyController::class, 'submit'])->name('submit');
        Route::post('/{company}/documents', [CompanyDocumentController::class, 'store'])
            ->whereNumber('company')->name('documents.store');
        Route::delete('/{company}/documents/{document}', [CompanyDocumentController::class, 'destroy'])
            ->whereNumber('company')->whereNumber('document')->name('documents.destroy');
        Route::post('/{company}/documents/{document}/supersede', [CompanyDocumentController::class, 'supersede'])
            ->whereNumber('company')->whereNumber('document')->name('documents.supersede');
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
    | Selector stage assignment (API_CONTRACT.md Part VIII · FR-HR-006 ·
    | INV-037). Contract Surface INERTIA_WEB. `HR_ADMIN` (Admin Kepegawaian)
    | and `SUPER_ADMIN` only, and only on stages of a CAMPUS vacancy
    | (assignment is a campus-recruitment capability — matrix §4.8 footnote
    | 23). A selector can never assign, extend, or revoke — including their
    | own (footnote 24). Contracts state "Authentication: Required" without an
    | email-verified gate, so none is added here, matching moderation and
    | transition.
    */
    Route::get('/stages/{stage}/selector-assignments', [SelectorAssignmentController::class, 'index'])
        ->whereNumber('stage')->name('stages.selector-assignments.index');
    Route::post('/stages/{stage}/selector-assignments', [SelectorAssignmentController::class, 'store'])
        ->whereNumber('stage')->name('stages.selector-assignments.store');
    Route::post('/selector-assignments/{assignment}/revoke', [SelectorAssignmentController::class, 'revoke'])
        ->whereNumber('assignment')->name('selector-assignments.revoke');

    /*
    | External Apply runtime (API_CONTRACT.md Part VII · FR-EXT-001..004 ·
    | INV-012, INV-024). Contract Surface VERSIONED_API, realized on the
    | browser session-guard surface exactly as every other business domain.
    | `start` requires a verified email (contract error AUTH_EMAIL_NOT_VERIFIED);
    | `confirm` and the history read follow the moderation/transition pattern
    | of "Authentication: Required" without an email gate. The EXTERNAL_APPLY
    | recruitment-outcome path stays deferred (Part X item 46) — no route.
    */
    /*
    | Application-shared document download (PGC-V1 / PD-A — supersedes RA-3
    | for the download operation, API_CONTRACT.md Part X items 22 / 62).
    | Streams the immutable application_documents snapshot; all authorization
    | (matrix 4.6), auditing and the enumeration-safe 404 live in the Action.
    */
    Route::get('/application-documents/{applicationDocument}/download', [ApplicationDocumentController::class, 'download'])
        ->whereNumber('applicationDocument')->name('application-documents.download');

    Route::post('/vacancies/{vacancy}/external-apply/start', [ExternalApplyController::class, 'start'])
        ->whereNumber('vacancy')->middleware('verified.email')->name('vacancies.external-apply.start');
    Route::post('/external-apply-events/{event}/confirm', [ExternalApplyController::class, 'confirm'])
        ->whereNumber('event')->name('external-apply-events.confirm');
    Route::get('/candidate/external-apply-events', [ExternalApplyController::class, 'events'])
        ->name('candidate.external-apply-events.index');

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
    | Laporkan Lowongan review queue (PGC-V1 / PD-C). Career Center owns the
    | NEW -> UNDER_REVIEW -> ACTIONED|DISMISSED lifecycle; Super Admin may read
    | the queue but not transition.
    */
    Route::get('/laporan-lowongan', [CareerCenterPageController::class, 'reportsIndex'])
        ->name('pages.career-center.reports');
    Route::get('/moderasi-lowongan/laporan/data', [VacancyReportController::class, 'index'])
        ->name('vacancy-reports.index');
    Route::post('/vacancy-reports/{report}/review', [VacancyReportController::class, 'startReview'])
        ->whereNumber('report')->name('vacancy-reports.review');
    Route::post('/vacancy-reports/{report}/{outcome}', [VacancyReportController::class, 'resolve'])
        ->whereNumber('report')->where('outcome', 'action|dismiss')->name('vacancy-reports.resolve');

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

    /*
    | Super Admin Full Activation — every FSD §4.6 navigation item now opens a
    | real page. Menu/page activation is distinct from full CRUD availability:
    | where a writable contract is unresolved the page is a READ_ONLY_REFERENCE
    | or PARTIAL_FUNCTIONAL surface, never a dead "Segera hadir" entry. Each
    | page controller gates SUPER_ADMIN and mutates nothing. Unresolved write /
    | destructive operations remain unrouted.
    |
    | - Pengguna dan Role: PARTIAL_FUNCTIONAL — role catalogue + assign/revoke
    |   (`POST /admin/users/{user}/roles` + revoke pair, frozen contract,
    |   Idempotency-Key, 409 on active duplicate, audit `role_changed`,
    |   affected user notified). No user directory (`GET /admin/users` has no
    |   frozen field/filter/pagination contract); no suspend/restore/DISABLED.
    | - Master Data / Unit Organisasi / Program Studi: READ_ONLY_REFERENCE over
    |   `GET /admin/master-data/{collection}`; writes DEFERRED (DF-1).
    | - Template Workflow / Template Notifikasi / Integrasi / Retensi Data /
    |   Pengaturan Sistem: READ_ONLY_REFERENCE — truthful capability/policy
    |   state, no invented schema, no fake values.
    */
    Route::get('/pengguna-role', [SuperAdminPageController::class, 'userRoleIndex'])
        ->name('pages.super-admin.user-roles');
    Route::post('/admin/users/{user}/roles', [AdminUserRoleController::class, 'assign'])
        ->whereNumber('user')->name('admin.users.roles.assign');
    Route::post('/admin/users/{user}/roles/{role}/revoke', [AdminUserRoleController::class, 'revoke'])
        ->whereNumber('user')->name('admin.users.roles.revoke');

    /*
    | Super Admin user directory + account lifecycle (PGC-V1 / PD-F,
    | API_CONTRACT.md Part X item 67). SUPER_ADMIN only. MVP lifecycle is
    | ACTIVE <-> SUSPENDED only; DISABLED stays deferred. Suspension is
    | immediate (server-side session rows cleared + OL-10 per-request check),
    | never notifies the user, and is audited.
    */
    Route::get('/admin/users', [AdminUserController::class, 'index'])->name('admin.users.index');
    Route::post('/admin/users/{user}/suspend', [AdminUserController::class, 'suspend'])
        ->whereNumber('user')->name('admin.users.suspend');
    Route::post('/admin/users/{user}/restore', [AdminUserController::class, 'restore'])
        ->whereNumber('user')->name('admin.users.restore');

    /*
    | Transactional email outbox operations (PGC-V1 / PD-B). SUPER_ADMIN only.
    | The delivery worker (DeliverEmailOutboxMessage) and the scheduled
    | outbox:sweep run automatically; requeue re-drives a FAILED_RETRYABLE /
    | DEAD_LETTER row.
    */
    Route::get('/admin/email-outbox', [AdminEmailOutboxController::class, 'index'])->name('admin.email-outbox.index');
    Route::post('/admin/email-outbox/{message}/requeue', [AdminEmailOutboxController::class, 'requeue'])
        ->whereNumber('message')->name('admin.email-outbox.requeue');

    Route::get('/master-data', [SuperAdminPageController::class, 'masterDataIndex'])
        ->name('pages.super-admin.master-data');
    Route::get('/unit-organisasi', [SuperAdminPageController::class, 'organizationalUnitIndex'])
        ->name('pages.super-admin.organizational-units');
    Route::get('/program-studi', [SuperAdminPageController::class, 'studyProgramIndex'])
        ->name('pages.super-admin.study-programs');
    Route::get('/template-workflow', [SuperAdminPageController::class, 'templateWorkflowIndex'])
        ->name('pages.super-admin.template-workflow');
    Route::get('/template-notifikasi', [SuperAdminPageController::class, 'templateNotifikasiIndex'])
        ->name('pages.super-admin.template-notifikasi');
    Route::get('/integrasi', [SuperAdminPageController::class, 'integrationIndex'])
        ->name('pages.super-admin.integrations');
    Route::get('/retensi-data', [SuperAdminPageController::class, 'dataRetentionIndex'])
        ->name('pages.super-admin.data-retention');
    Route::get('/pengaturan-sistem', [SuperAdminPageController::class, 'systemSettingsIndex'])
        ->name('pages.super-admin.system-settings');
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
