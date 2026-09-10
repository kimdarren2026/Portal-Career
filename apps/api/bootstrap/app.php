<?php

use App\Http\Middleware\AssignCorrelationId;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\EnforceAuthAbuseControls;
use App\Http\Middleware\EnsureAccountStatus;
use App\Http\Middleware\EnsureVerifiedEmail;
use App\Http\Middleware\EnforceCandidateDocumentUploadRateLimit;
use App\Http\Middleware\EnforceCompanyMemberInviteRateLimit;
use App\Http\Middleware\EnforcePublicDiscoveryRateLimit;
use App\Http\Middleware\EnforceVacancyReportRateLimit;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Responses\ContractResponse;
use Inertia\Inertia;
use App\Domains\Application\Exceptions\ApplicationAlreadyExists;
use App\Domains\Application\Exceptions\ApplicationAlreadyWithdrawn;
use App\Domains\Application\Exceptions\ApplicationConsentRequired;
use App\Domains\Application\Exceptions\ApplicationDocumentArchived;
use App\Domains\Application\Exceptions\ApplicationDocumentRequired;
use App\Domains\Application\Exceptions\ApplicationInvalidTransition;
use App\Domains\Application\Exceptions\ApplicationNotFound;
use App\Domains\Application\Exceptions\ApplicationNotInPortalVacancy;
use App\Domains\Application\Exceptions\ApplicationScreeningIncomplete;
use App\Domains\Application\Exceptions\ApplicationScreeningInvalid;
use App\Domains\Application\Exceptions\ApplicationStageAlreadyCurrent;
use App\Domains\Application\Exceptions\ApplicationStageTargetInactive;
use App\Domains\Application\Exceptions\ApplicationStaleVersion;
use App\Domains\Application\Exceptions\ApplicationTerminal;
use App\Domains\Application\Exceptions\CandidateNotEligible;
use App\Domains\Application\Exceptions\ConsentReceiverMismatch;
use App\Domains\Application\Exceptions\ConsentVersionUnknown;
use App\Domains\Application\Exceptions\VacancyNotOpenForApplication;
use App\Domains\Candidate\Exceptions\CandidateCollectionNotFoundException;
use App\Domains\Candidate\Exceptions\CandidateDocumentNotOwnedException;
use App\Domains\Candidate\Exceptions\CandidateDocumentPersistenceException;
use App\Domains\Candidate\Exceptions\CandidateDocumentSignatureInvalidException;
use App\Domains\Candidate\Exceptions\CandidateDocumentStorageException;
use App\Domains\Candidate\Exceptions\CandidateDocumentUnsupportedMediaTypeException;
use App\Domains\Candidate\Exceptions\CandidateInvalidReferenceException;
use App\Domains\Candidate\Exceptions\CandidateProfileRequiredException;
use App\Domains\Company\Exceptions\CompanyDocumentNotDraft;
use App\Domains\Company\Exceptions\CompanyDocumentNotFound;
use App\Domains\Company\Exceptions\CompanyDocumentSupersedeInvalid;
use App\Domains\Company\Exceptions\CompanyDocumentTooLarge;
use App\Domains\Company\Exceptions\CompanyDocumentTypeInvalid;
use App\Domains\Company\Exceptions\CompanyDocumentUnsupportedMediaType;
use App\Domains\Company\Exceptions\CompanyMemberAlreadyActive;
use App\Domains\Company\Exceptions\CompanyMemberNotFound;
use App\Domains\Company\Exceptions\CompanyNotFound;
use App\Domains\Company\Exceptions\CompanyNotPublic;
use App\Domains\Company\Exceptions\LastCompanyAdmin;
use App\Domains\Notification\Exceptions\NotificationNotFound;
use App\Domains\Recruitment\Evaluation\Exceptions\EvaluationAlreadySubmitted;
use App\Domains\Recruitment\Evaluation\Exceptions\EvaluationNotFound;
use App\Domains\Recruitment\Evaluation\Exceptions\EvaluationNotOwned;
use App\Domains\Recruitment\Evaluation\Exceptions\EvaluationStageTargetInactive;
use App\Domains\Recruitment\Offering\Exceptions\OfferAlreadyAcceptedForApplication;
use App\Domains\Recruitment\Offering\Exceptions\OfferAlreadyResponded;
use App\Domains\Recruitment\Offering\Exceptions\OfferExpired;
use App\Domains\Recruitment\Offering\Exceptions\OfferInvalidTransition;
use App\Domains\Recruitment\Offering\Exceptions\OfferNotFound;
use App\Domains\Recruitment\Offering\Exceptions\OfferNotSent;
use App\Domains\Recruitment\Outcome\Exceptions\OutcomeAlreadyRecorded;
use App\Domains\Recruitment\Outcome\Exceptions\RecruitmentOutcomeNotFound;
use App\Domains\Recruitment\SelectionSchedule\Exceptions\ScheduleInvalidTransition;
use App\Domains\Recruitment\SelectionSchedule\Exceptions\ScheduleMethodDetailRequired;
use App\Domains\Recruitment\SelectionSchedule\Exceptions\ScheduleTargetStageInactive;
use App\Domains\Recruitment\SelectionSchedule\Exceptions\ScheduleTimeInvalid;
use App\Domains\Recruitment\SelectionSchedule\Exceptions\SelectionScheduleNotFound;
use App\Domains\Recruitment\SelectionSchedule\Exceptions\SelectionScheduleStaleVersion;
use App\Domains\Vacancy\Exceptions\RecruitmentStageNotFound;
use App\Domains\Vacancy\Exceptions\RecruitmentStageNotInVacancy;
use App\Domains\Vacancy\Exceptions\ScreeningQuestionNotFound;
use App\Domains\Vacancy\Exceptions\ReviewReasonRequired as VacancyReviewReasonRequired;
use App\Domains\Vacancy\Exceptions\VacancyCloseBeforeOpen;
use App\Domains\Vacancy\Exceptions\VacancyCompanyNotVerified;
use App\Domains\Vacancy\Exceptions\VacancyDatesRequired;
use App\Domains\Vacancy\Exceptions\VacancyExternalUrlInvalid;
use App\Domains\Vacancy\Exceptions\VacancyExternalUrlRequired;
use App\Domains\Vacancy\Exceptions\VacancyInvalidTransition;
use App\Domains\Vacancy\Exceptions\VacancyModerationNotApplicable;
use App\Domains\Vacancy\Exceptions\VacancyNotFound;
use App\Domains\Vacancy\Exceptions\VacancyNotProcessable;
use App\Domains\Vacancy\Exceptions\VacancyNotPublic;
use App\Domains\Vacancy\Exceptions\VacancyProfileIncomplete;
use App\Domains\Vacancy\Exceptions\SelectionStageAssignmentNotFound;
use App\Domains\Vacancy\Exceptions\SelectorAssignmentAlreadyActive;
use App\Domains\Vacancy\Exceptions\SelectorAssignmentUserNotFound;
use App\Domains\Vacancy\Exceptions\SelectorRoleRequired;
use App\Domains\Application\Exceptions\ApplicationDocumentNotFound;
use App\Domains\Notification\Exceptions\EmailOutboxMessageNotFound;
use App\Domains\VacancyReport\Exceptions\VacancyReportInvalidTransition;
use App\Domains\VacancyReport\Exceptions\VacancyReportNotFound;
use App\Domains\VacancyReport\Exceptions\VacancyReportSelfReview;
use App\Domains\VacancyReport\Exceptions\VacancyReportVacancyNotFound;
use App\Domains\ExternalApply\Exceptions\ExternalApplyConfirmationForbidden;
use App\Domains\ExternalApply\Exceptions\ExternalApplyEventAlreadyConfirmed;
use App\Domains\ExternalApply\Exceptions\ExternalApplyEventNotFound;
use App\Domains\ExternalApply\Exceptions\ExternalApplyInvalidMethod;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        // Operational health endpoints (DEPLOYMENT_ARCHITECTURE.md §5), served
        // WITHOUT the web/session/CSRF stack: a readiness probe every few
        // seconds must not open a session. `/health/live` never touches a
        // dependency (restart supervision); `/health/ready` checks PostgreSQL,
        // Redis and object storage for the load balancer. Neither discloses
        // anything beyond pass/fail per dependency. Laravel's `/up` is kept.
        then: function (): void {
            \Illuminate\Support\Facades\Route::get('/health/live', [\App\Http\Controllers\HealthController::class, 'live'])->name('health.live');
            \Illuminate\Support\Facades\Route::get('/health/ready', [\App\Http\Controllers\HealthController::class, 'ready'])->name('health.ready');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Behind a TLS-terminating reverse proxy / load balancer, honour
        // X-Forwarded-* so the scheme, host, port and client IP are correct
        // (secure-cookie decisions, absolute URLs, rate-limit keys). The proxy
        // set is deployment-specific: TRUSTED_PROXIES is a comma list, or `*`
        // when the app is only ever reachable through the proxy. Unset = trust
        // nothing (safe default for local / direct exposure).
        $trustedProxies = (string) env('TRUSTED_PROXIES', '');
        if ($trustedProxies !== '') {
            $middleware->trustProxies(
                at: $trustedProxies === '*' ? '*' : array_map('trim', explode(',', $trustedProxies)),
                headers: Request::HEADER_X_FORWARDED_FOR
                    | Request::HEADER_X_FORWARDED_HOST
                    | Request::HEADER_X_FORWARDED_PORT
                    | Request::HEADER_X_FORWARDED_PROTO,
            );
        }

        // Baseline security response headers (SECURITY_ARCHITECTURE.md §3) —
        // defence in depth; HSTS and CSP remain the edge proxy's job (§6).
        $middleware->append(SecurityHeaders::class);

        // Correlation ID on every request, propagated into logs, jobs and
        // audit_logs.correlation_id (FSD §9.3, API_CONTRACT.md Part I §10).
        $middleware->append(AssignCorrelationId::class);

        // Guest hitting an `auth`-guarded browser page: send them to the login
        // page. The frozen login route is named `auth.login.page`, not `login`,
        // so the framework default `route('login')` throws RouteNotFoundException
        // (HTTP 500) for every Inertia page visit by an unauthenticated user.
        // A path here is resolved without a route name. JSON callers still get
        // the existing 401 `ContractResponse` envelope via the exception render.
        $middleware->redirectGuestsTo('/login');

        // INERTIA_WEB surface: session cookie + CSRF (ADR-001, ADR-005).
        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'auth.abuse' => EnforceAuthAbuseControls::class,
            'account.status' => EnsureAccountStatus::class,
            'verified.email' => EnsureVerifiedEmail::class,
            'candidate.document-upload-rate' => EnforceCandidateDocumentUploadRateLimit::class,
            'company.member-invite-rate' => EnforceCompanyMemberInviteRateLimit::class,
            'public-discovery.rate-limit' => EnforcePublicDiscoveryRateLimit::class,
            'vacancy-report-rate' => EnforceVacancyReportRateLimit::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        /*
        | Recruitment Frontend Vertical Slice v1 — styled Inertia error pages
        | for browser/Inertia GET page navigation only (Frontend Correction
        | Turn). Registered BEFORE the plain-JSON renderables for the same
        | exception below, using the identical `expectsJson()` predicate
        | `shouldRenderJsonWhen` already uses: when the request wants JSON
        | (every `authRequest()` mutation call explicitly sends
        | `Accept: application/json`), this returns null and Laravel falls
        | through to the existing `ContractResponse` renderable — untouched,
        | same status/code/message as always. When it does not (an Inertia
        | `<Link>`/`router.get` page visit or a plain browser address-bar
        | navigation, neither of which ever sets that header), it renders
        | the shared `Error` page with the correct HTTP status and no
        | resource identifier, exception class, or SQLSTATE.
        */
        $exceptions->render(function (ApplicationNotFound $exception, Request $request) {
            if ($request->expectsJson()) {
                return null;
            }

            return Inertia::render('Error', ['status' => 404])->toResponse($request)->setStatusCode(404);
        });
        $exceptions->render(function (SelectionScheduleNotFound $exception, Request $request) {
            if ($request->expectsJson()) {
                return null;
            }

            return Inertia::render('Error', ['status' => 404])->toResponse($request)->setStatusCode(404);
        });

        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if (in_array($request->path(), ['me', 'me/password', 'auth/logout'], true) || $request->is('candidate/*') || $request->is('companies/*') || $request->is('companies') || $request->is('vacancies') || $request->is('vacancies/*') || $request->is('applications') || $request->is('applications/*') || $request->is('notifications') || $request->is('notifications/*') || $request->is('stages/*') || $request->is('selector-assignments/*') || $request->is('external-apply-events/*') || $request->is('application-documents/*') || $request->is('admin/*') || $request->is('vacancy-reports/*')) {
                return ContractResponse::error($request, 'UNAUTHENTICATED', 401, 'Sesi autentikasi diperlukan.');
            }
        });
        $exceptions->render(fn (CandidateProfileRequiredException $exception, Request $request) => ContractResponse::error($request, 'CANDIDATE_PROFILE_REQUIRED', 422, 'Profil kandidat tidak tersedia.'));
        $exceptions->render(fn (LastCompanyAdmin $exception, Request $request) => ContractResponse::error($request, 'MEMBER_LAST_ADMIN', 409, 'Setidaknya satu Company Admin aktif harus dipertahankan.'));
        $exceptions->render(fn (CompanyMemberAlreadyActive $exception, Request $request) => ContractResponse::error($request, 'MEMBER_ALREADY_ACTIVE', 409, 'Anggota ini sudah aktif di perusahaan tersebut.'));
        // PGC-V1 / PD-D — company legal documents.
        $exceptions->render(fn (CompanyDocumentNotFound $exception, Request $request) => ContractResponse::error($request, 'NOT_FOUND', 404, 'Dokumen legalitas tidak ditemukan.'));
        $exceptions->render(fn (CompanyDocumentNotDraft $exception, Request $request) => ContractResponse::error($request, 'COMPANY_DOCUMENT_IS_VERIFICATION_EVIDENCE', 409, 'Dokumen yang sudah diajukan tidak dapat dihapus; gunakan penggantian dokumen.'));
        $exceptions->render(fn (CompanyDocumentSupersedeInvalid $exception, Request $request) => ContractResponse::error($request, 'CONFLICT', 409, 'Penggantian dokumen tidak sah.'));
        $exceptions->render(fn (CompanyDocumentTypeInvalid $exception, Request $request) => ContractResponse::error($request, 'VALIDATION_FAILED', 422, 'Jenis dokumen legalitas tidak dikenali.'));
        $exceptions->render(fn (CompanyDocumentTooLarge $exception, Request $request) => ContractResponse::error($request, 'PAYLOAD_TOO_LARGE', 413, 'Ukuran berkas melebihi 10 MiB.'));
        $exceptions->render(fn (CompanyDocumentUnsupportedMediaType $exception, Request $request) => ContractResponse::error($request, 'UNSUPPORTED_MEDIA_TYPE', 415, 'Berkas harus berupa PDF, JPEG, atau PNG.'));
        // Out of COMPANY_SCOPE and absent answer identically: 403 would confirm
        // the row exists to an actor who must not know it does (matrix §1).
        $exceptions->render(fn (CompanyNotFound $exception, Request $request) => ContractResponse::error($request, 'NOT_FOUND', 404, 'Perusahaan tidak ditemukan.'));
        // PD-2 public company detail: a nonexistent slug and a non-VERIFIED
        // company are indistinguishable — both are a plain 404.
        $exceptions->render(fn (CompanyNotPublic $exception, Request $request) => ContractResponse::error($request, 'NOT_FOUND', 404, 'Perusahaan tidak ditemukan.'));
        $exceptions->render(fn (CompanyMemberNotFound $exception, Request $request) => ContractResponse::error($request, 'NOT_FOUND', 404, 'Anggota perusahaan tidak ditemukan.'));
        // A notification outside the actor's OWN set is indistinguishable from
        // one that does not exist — both are a plain 404 (no cross-user leak).
        $exceptions->render(fn (NotificationNotFound $exception, Request $request) => ContractResponse::error($request, 'NOT_FOUND', 404, 'Notifikasi tidak ditemukan.'));
        $exceptions->render(fn (VacancyNotFound $exception, Request $request) => ContractResponse::error($request, 'NOT_FOUND', 404, 'Lowongan tidak ditemukan.'));
        // Public discovery (§10): a non-existent slug and a slug that fails any
        // one visibility condition are indistinguishable — both are VACANCY_NOT_PUBLIC.
        $exceptions->render(fn (VacancyNotPublic $exception, Request $request) => ContractResponse::error($request, 'VACANCY_NOT_PUBLIC', 404, 'Lowongan tidak ditemukan.'));
        // Lifecycle (B-1 … B-5). Every code below is already frozen in ERROR_CODES.md.
        $exceptions->render(fn (VacancyInvalidTransition $exception, Request $request) => ContractResponse::error($request, 'VACANCY_INVALID_TRANSITION', 409, 'Aksi tidak sah dari status lowongan saat ini.'));
        $exceptions->render(fn (VacancyModerationNotApplicable $exception, Request $request) => ContractResponse::error($request, 'VACANCY_MODERATION_NOT_APPLICABLE', 409, 'Lowongan kampus tidak melalui moderasi.'));
        $exceptions->render(fn (VacancyReviewReasonRequired $exception, Request $request) => ContractResponse::error($request, 'REVIEW_REASON_REQUIRED', 422, 'Kategori alasan dan catatan untuk recruiter wajib diisi.'));
        $exceptions->render(fn (VacancyProfileIncomplete $exception, Request $request) => ContractResponse::error($request, 'VACANCY_PROFILE_INCOMPLETE', 422, 'Lowongan belum lengkap untuk diajukan.', ['missing' => $exception->missing]));
        $exceptions->render(fn (VacancyDatesRequired $exception, Request $request) => ContractResponse::error($request, 'VACANCY_DATES_REQUIRED', 422, 'Tanggal buka dan tutup wajib diisi.'));
        $exceptions->render(fn (VacancyCloseBeforeOpen $exception, Request $request) => ContractResponse::error($request, 'VACANCY_CLOSE_BEFORE_OPEN', 422, 'Tanggal tutup harus setelah tanggal buka.'));
        $exceptions->render(fn (VacancyExternalUrlRequired $exception, Request $request) => ContractResponse::error($request, 'VACANCY_EXTERNAL_ATS_URL_REQUIRED', 422, 'URL ATS eksternal wajib diisi.'));
        $exceptions->render(fn (VacancyExternalUrlInvalid $exception, Request $request) => ContractResponse::error($request, 'VACANCY_EXTERNAL_ATS_URL_INVALID', 422, 'URL ATS eksternal harus menggunakan HTTPS.'));
        $exceptions->render(fn (VacancyCompanyNotVerified $exception, Request $request) => ContractResponse::error($request, 'VACANCY_COMPANY_NOT_VERIFIED', 403, 'Perusahaan harus terverifikasi.'));
        $exceptions->render(fn (ScreeningQuestionNotFound $exception, Request $request) => ContractResponse::error($request, 'NOT_FOUND', 404, 'Pertanyaan seleksi tidak ditemukan.'));
        // Recruitment Stage Authoring Foundation v1 (RS-2, RS-6).
        $exceptions->render(fn (RecruitmentStageNotFound $exception, Request $request) => ContractResponse::error($request, 'NOT_FOUND', 404, 'Tahap seleksi tidak ditemukan.'));
        // Candidate Application Foundation v1. AD-1 reuses the existing
        // VACANCY_COMPANY_NOT_VERIFIED mapping above — no separate code.
        $exceptions->render(fn (ApplicationNotFound $exception, Request $request) => ContractResponse::error($request, 'NOT_FOUND', 404, 'Lamaran tidak ditemukan.'));
        $exceptions->render(fn (VacancyNotOpenForApplication $exception, Request $request) => ContractResponse::error($request, 'VACANCY_NOT_OPEN', 409, 'Lowongan belum dibuka atau sudah ditutup.'));
        $exceptions->render(fn (ApplicationNotInPortalVacancy $exception, Request $request) => ContractResponse::error($request, 'APPLICATION_NOT_IN_PORTAL_VACANCY', 422, 'Lowongan ini tidak menggunakan lamaran dalam portal.'));
        $exceptions->render(fn (CandidateNotEligible $exception, Request $request) => ContractResponse::error($request, 'CANDIDATE_NOT_ELIGIBLE', 403, 'Anda tidak memenuhi syarat target kandidat lowongan ini.'));
        $exceptions->render(fn (ApplicationAlreadyExists $exception, Request $request) => ContractResponse::error($request, 'APPLICATION_ALREADY_EXISTS', 409, 'Anda sudah melamar lowongan ini.', ['application_id' => $exception->applicationId]));
        $exceptions->render(fn (ApplicationConsentRequired $exception, Request $request) => ContractResponse::error($request, 'APPLICATION_CONSENT_REQUIRED', 422, 'Persetujuan pembagian data diperlukan.'));
        $exceptions->render(fn (ConsentVersionUnknown $exception, Request $request) => ContractResponse::error($request, 'CONSENT_VERSION_UNKNOWN', 422, 'Versi persetujuan tidak dikenali.'));
        $exceptions->render(fn (ConsentReceiverMismatch $exception, Request $request) => ContractResponse::error($request, 'CONSENT_RECEIVER_MISMATCH', 422, 'Penerima persetujuan tidak sah.'));
        $exceptions->render(fn (ApplicationDocumentRequired $exception, Request $request) => ContractResponse::error($request, 'APPLICATION_DOCUMENT_REQUIRED', 422, 'Lengkapi dokumen wajib.'));
        $exceptions->render(fn (ApplicationDocumentArchived $exception, Request $request) => ContractResponse::error($request, 'DOCUMENT_ARCHIVED', 422, 'Dokumen ini sudah diarsipkan.'));
        $exceptions->render(fn (ApplicationScreeningIncomplete $exception, Request $request) => ContractResponse::error($request, 'APPLICATION_SCREENING_INCOMPLETE', 422, 'Lengkapi pertanyaan seleksi yang wajib dijawab.'));
        $exceptions->render(fn (ApplicationScreeningInvalid $exception, Request $request) => ContractResponse::error($request, 'APPLICATION_SCREENING_INVALID', 422, 'Jawaban seleksi tidak valid.'));
        $exceptions->render(fn (ApplicationAlreadyWithdrawn $exception, Request $request) => ContractResponse::error($request, 'APPLICATION_ALREADY_WITHDRAWN', 409, 'Lamaran ini sudah ditarik.'));
        // Recruiter Applicant Management Foundation v1 (RA-1, RA-2). RA-2's
        // company gate reuses VACANCY_COMPANY_NOT_VERIFIED above — no separate code.
        $exceptions->render(fn (ApplicationInvalidTransition $exception, Request $request) => ContractResponse::error($request, 'APPLICATION_INVALID_TRANSITION', 409, 'Perubahan status tidak sah dari status saat ini.', ['from' => $exception->from, 'attempted' => $exception->attempted]));
        $exceptions->render(fn (ApplicationTerminal $exception, Request $request) => ContractResponse::error($request, 'APPLICATION_TERMINAL', 409, 'Lamaran berada pada status akhir dan tidak dapat diubah.'));
        $exceptions->render(fn (ApplicationStaleVersion $exception, Request $request) => ContractResponse::error($request, 'STALE_VERSION', 409, 'Versi lamaran sudah berubah. Muat ulang sebelum menyimpan.'));
        $exceptions->render(fn (VacancyNotProcessable $exception, Request $request) => ContractResponse::error($request, 'APPLICATION_VACANCY_NOT_PROCESSABLE', 409, 'Lowongan tidak dalam status yang mengizinkan pemrosesan lamaran.'));
        // Application Stage Movement Foundation v1 (MS-3, MS-4). Wrong-vacancy
        // target reuses STAGE_NOT_IN_VACANCY (INV-019), the same code and
        // exception Recruitment Stage Authoring's reorder already established.
        $exceptions->render(fn (RecruitmentStageNotInVacancy $exception, Request $request) => ContractResponse::error($request, 'STAGE_NOT_IN_VACANCY', 422, 'Susunan tahap tidak sesuai dengan tahap lowongan ini.'));
        $exceptions->render(fn (ApplicationStageTargetInactive $exception, Request $request) => ContractResponse::error($request, 'VALIDATION_FAILED', 422, 'Tahap tujuan tidak aktif.'));
        $exceptions->render(fn (ApplicationStageAlreadyCurrent $exception, Request $request) => ContractResponse::error($request, 'VALIDATION_FAILED', 422, 'Aplikasi sudah berada pada tahap ini.'));
        // Selection Schedule Foundation v1 (SS-1, SS-2, SS-3, SS-5, SS-8, SS-9).
        $exceptions->render(fn (SelectionScheduleNotFound $exception, Request $request) => ContractResponse::error($request, 'NOT_FOUND', 404, 'Jadwal seleksi tidak ditemukan.'));
        $exceptions->render(fn (ScheduleTimeInvalid $exception, Request $request) => ContractResponse::error($request, 'SCHEDULE_TIME_INVALID', 422, 'Waktu jadwal tidak valid.'));
        $exceptions->render(fn (ScheduleMethodDetailRequired $exception, Request $request) => ContractResponse::error($request, 'SCHEDULE_METHOD_DETAIL_REQUIRED', 422, 'Lokasi atau tautan pertemuan wajib diisi sesuai metode.'));
        $exceptions->render(fn (ScheduleInvalidTransition $exception, Request $request) => ContractResponse::error($request, 'SCHEDULE_INVALID_TRANSITION', 409, 'Aksi tidak sah dari status jadwal saat ini.'));
        $exceptions->render(fn (ScheduleTargetStageInactive $exception, Request $request) => ContractResponse::error($request, 'VALIDATION_FAILED', 422, 'Tahap yang dirujuk tidak aktif.'));
        $exceptions->render(fn (SelectionScheduleStaleVersion $exception, Request $request) => ContractResponse::error($request, 'STALE_VERSION', 409, 'Versi jadwal sudah berubah. Muat ulang sebelum menyimpan.'));
        // Evaluation / Scoring Foundation v1 (EV-1, EV-2, RC-1).
        $exceptions->render(fn (EvaluationNotFound $exception, Request $request) => ContractResponse::error($request, 'NOT_FOUND', 404, 'Evaluasi tidak ditemukan.'));
        $exceptions->render(fn (EvaluationStageTargetInactive $exception, Request $request) => ContractResponse::error($request, 'VALIDATION_FAILED', 422, 'Tahap yang dipilih tidak aktif.'));
        $exceptions->render(fn (EvaluationAlreadySubmitted $exception, Request $request) => ContractResponse::error($request, 'EVALUATION_ALREADY_SUBMITTED', 409, 'Evaluasi sudah difinalisasi dan tidak dapat diubah.'));
        $exceptions->render(fn (EvaluationNotOwned $exception, Request $request) => ContractResponse::error($request, 'EVALUATION_NOT_OWNED', 403, 'Evaluasi ini milik evaluator lain.'));
        // Offering Foundation v1 (OF-1, OF-2, RC-1).
        $exceptions->render(fn (OfferNotFound $exception, Request $request) => ContractResponse::error($request, 'NOT_FOUND', 404, 'Offering tidak ditemukan.'));
        $exceptions->render(fn (OfferInvalidTransition $exception, Request $request) => ContractResponse::error($request, 'OFFER_INVALID_TRANSITION', 409, 'Aksi tidak sah dari status offering saat ini.'));
        $exceptions->render(fn (OfferNotSent $exception, Request $request) => ContractResponse::error($request, 'OFFER_NOT_SENT', 409, 'Offering berstatus draf tidak dapat direspons.'));
        $exceptions->render(fn (OfferAlreadyResponded $exception, Request $request) => ContractResponse::error($request, 'OFFER_ALREADY_RESPONDED', 409, 'Offering ini sudah direspons.'));
        $exceptions->render(fn (OfferExpired $exception, Request $request) => ContractResponse::error($request, 'OFFER_EXPIRED', 409, 'Batas waktu respons offering sudah lewat.'));
        $exceptions->render(fn (OfferAlreadyAcceptedForApplication $exception, Request $request) => ContractResponse::error($request, 'OFFER_ALREADY_ACCEPTED_FOR_APPLICATION', 409, 'Sudah ada offering lain yang diterima untuk lamaran ini.'));
        // Recruitment Outcome Foundation v1 (OC-1, RC-2).
        $exceptions->render(fn (RecruitmentOutcomeNotFound $exception, Request $request) => ContractResponse::error($request, 'NOT_FOUND', 404, 'Recruitment outcome tidak ditemukan.'));
        $exceptions->render(fn (OutcomeAlreadyRecorded $exception, Request $request) => ContractResponse::error($request, 'OUTCOME_ALREADY_RECORDED', 409, 'Outcome sudah pernah dicatat untuk lamaran ini.'));
        // Selector stage assignment (FR-HR-006, INV-037). Out-of-scope / missing
        // assignments and users answer as a plain NOT_FOUND — an actor may not
        // learn a campus-only stage or another user exists by probing.
        $exceptions->render(fn (SelectorRoleRequired $exception, Request $request) => ContractResponse::error($request, 'SELECTOR_ROLE_REQUIRED', 422, 'Pengguna tidak memiliki role SELECTOR aktif dan tidak dapat ditugaskan.'));
        $exceptions->render(fn (SelectorAssignmentAlreadyActive $exception, Request $request) => ContractResponse::error($request, 'SELECTOR_ASSIGNMENT_ALREADY_ACTIVE', 409, 'Penugasan aktif untuk selektor dan tahap ini sudah ada.'));
        $exceptions->render(fn (SelectionStageAssignmentNotFound $exception, Request $request) => ContractResponse::error($request, 'NOT_FOUND', 404, 'Penugasan selektor tidak ditemukan.'));
        $exceptions->render(fn (SelectorAssignmentUserNotFound $exception, Request $request) => ContractResponse::error($request, 'NOT_FOUND', 404, 'Pengguna tidak ditemukan.'));
        // External Apply runtime (FR-EXT-001..004, INV-012, INV-024).
        $exceptions->render(fn (ExternalApplyInvalidMethod $exception, Request $request) => ContractResponse::error($request, 'EXTERNAL_APPLY_INVALID_METHOD', 422, 'Lowongan ini tidak menggunakan lamaran ATS eksternal.'));
        $exceptions->render(fn (ExternalApplyEventNotFound $exception, Request $request) => ContractResponse::error($request, 'NOT_FOUND', 404, 'Aktivitas lamaran eksternal tidak ditemukan.'));
        $exceptions->render(fn (ExternalApplyEventAlreadyConfirmed $exception, Request $request) => ContractResponse::error($request, 'EXTERNAL_APPLY_EVENT_ALREADY_CONFIRMED', 409, 'Aktivitas lamaran eksternal ini sudah dikonfirmasi.'));
        $exceptions->render(fn (ExternalApplyConfirmationForbidden $exception, Request $request) => ContractResponse::error($request, 'EXTERNAL_APPLY_CONFIRMATION_FORBIDDEN', 403, 'Anda bukan sumber konfirmasi yang sah.'));
        // PGC-V1 / PD-A — application-shared document download. Out-of-scope,
        // revoked, non-existent and missing-object are one enumeration-safe 404.
        $exceptions->render(fn (ApplicationDocumentNotFound $exception, Request $request) => ContractResponse::error($request, 'NOT_FOUND', 404, 'Dokumen lamaran tidak ditemukan.'));
        // PGC-V1 / PD-B — Super Admin email-outbox requeue.
        $exceptions->render(fn (EmailOutboxMessageNotFound $exception, Request $request) => ContractResponse::error($request, 'NOT_FOUND', 404, 'Pesan email tidak ditemukan atau tidak dapat dikirim ulang.'));
        // PGC-V1 / PD-C — Laporkan Lowongan.
        $exceptions->render(fn (VacancyReportVacancyNotFound $exception, Request $request) => ContractResponse::error($request, 'NOT_FOUND', 404, 'Lowongan tidak ditemukan.'));
        $exceptions->render(fn (VacancyReportNotFound $exception, Request $request) => ContractResponse::error($request, 'NOT_FOUND', 404, 'Laporan tidak ditemukan.'));
        $exceptions->render(fn (VacancyReportInvalidTransition $exception, Request $request) => ContractResponse::error($request, 'CONFLICT', 409, 'Aksi tidak sah dari status laporan saat ini.'));
        $exceptions->render(fn (VacancyReportSelfReview $exception, Request $request) => ContractResponse::error($request, 'AUTH_FORBIDDEN', 403, 'Anda tidak dapat meninjau laporan yang Anda kirim sendiri.'));
        $exceptions->render(fn (CandidateCollectionNotFoundException $exception, Request $request) => ContractResponse::error($request, 'NOT_FOUND', 404, 'Data tidak ditemukan.'));
        $exceptions->render(fn (CandidateDocumentNotOwnedException $exception, Request $request) => ContractResponse::error($request, 'DOCUMENT_NOT_OWNED', 403, 'Dokumen bukan milik kandidat ini.'));
        $exceptions->render(fn (CandidateDocumentUnsupportedMediaTypeException $exception, Request $request) => ContractResponse::error($request, 'UNSUPPORTED_MEDIA_TYPE', 415, 'Dokumen harus berupa PDF.'));
        $exceptions->render(fn (CandidateDocumentSignatureInvalidException $exception, Request $request) => ContractResponse::error($request, 'DOCUMENT_TYPE_NOT_ALLOWED', 422, 'Konten dokumen tidak valid.'));
        $exceptions->render(fn (CandidateDocumentStorageException $exception, Request $request) => ContractResponse::error($request, 'SERVER_ERROR', 500, 'Dokumen tidak dapat disimpan saat ini.'));
        $exceptions->render(fn (CandidateDocumentPersistenceException $exception, Request $request) => ContractResponse::error($request, 'SERVER_ERROR', 500, 'Dokumen tidak dapat disimpan saat ini.'));
        $exceptions->render(fn (CandidateInvalidReferenceException $exception, Request $request) => ContractResponse::error($request, 'VALIDATION_FAILED', 422, 'Referensi yang dikirim tidak valid.'));
        $exceptions->render(function (ModelNotFoundException $exception, Request $request) {
            if ($request->is('candidate/documents/*')) {
                return ContractResponse::error($request, 'NOT_FOUND', 404, 'Dokumen tidak ditemukan.');
            }
        });
    })->create();
