<?php

use App\Http\Middleware\AssignCorrelationId;
use App\Http\Middleware\EnforceAuthAbuseControls;
use App\Http\Middleware\EnsureAccountStatus;
use App\Http\Middleware\EnsureVerifiedEmail;
use App\Http\Middleware\EnforceCandidateDocumentUploadRateLimit;
use App\Http\Middleware\EnforceCompanyMemberInviteRateLimit;
use App\Http\Middleware\EnforcePublicDiscoveryRateLimit;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Responses\ContractResponse;
use App\Domains\Candidate\Exceptions\CandidateCollectionNotFoundException;
use App\Domains\Candidate\Exceptions\CandidateDocumentNotOwnedException;
use App\Domains\Candidate\Exceptions\CandidateDocumentPersistenceException;
use App\Domains\Candidate\Exceptions\CandidateDocumentSignatureInvalidException;
use App\Domains\Candidate\Exceptions\CandidateDocumentStorageException;
use App\Domains\Candidate\Exceptions\CandidateDocumentUnsupportedMediaTypeException;
use App\Domains\Candidate\Exceptions\CandidateInvalidReferenceException;
use App\Domains\Candidate\Exceptions\CandidateProfileRequiredException;
use App\Domains\Company\Exceptions\CompanyMemberAlreadyActive;
use App\Domains\Company\Exceptions\CompanyMemberNotFound;
use App\Domains\Company\Exceptions\CompanyNotFound;
use App\Domains\Company\Exceptions\CompanyNotPublic;
use App\Domains\Company\Exceptions\LastCompanyAdmin;
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
use App\Domains\Vacancy\Exceptions\VacancyNotPublic;
use App\Domains\Vacancy\Exceptions\VacancyProfileIncomplete;
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
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Correlation ID on every request, propagated into logs, jobs and
        // audit_logs.correlation_id (FSD §9.3, API_CONTRACT.md Part I §10).
        $middleware->append(AssignCorrelationId::class);

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
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if (in_array($request->path(), ['me', 'me/password', 'auth/logout'], true) || $request->is('candidate/*') || $request->is('companies/*') || $request->is('companies') || $request->is('vacancies') || $request->is('vacancies/*')) {
                return ContractResponse::error($request, 'UNAUTHENTICATED', 401, 'Sesi autentikasi diperlukan.');
            }
        });
        $exceptions->render(fn (CandidateProfileRequiredException $exception, Request $request) => ContractResponse::error($request, 'CANDIDATE_PROFILE_REQUIRED', 422, 'Profil kandidat tidak tersedia.'));
        $exceptions->render(fn (LastCompanyAdmin $exception, Request $request) => ContractResponse::error($request, 'MEMBER_LAST_ADMIN', 409, 'Setidaknya satu Company Admin aktif harus dipertahankan.'));
        $exceptions->render(fn (CompanyMemberAlreadyActive $exception, Request $request) => ContractResponse::error($request, 'MEMBER_ALREADY_ACTIVE', 409, 'Anggota ini sudah aktif di perusahaan tersebut.'));
        // Out of COMPANY_SCOPE and absent answer identically: 403 would confirm
        // the row exists to an actor who must not know it does (matrix §1).
        $exceptions->render(fn (CompanyNotFound $exception, Request $request) => ContractResponse::error($request, 'NOT_FOUND', 404, 'Perusahaan tidak ditemukan.'));
        // PD-2 public company detail: a nonexistent slug and a non-VERIFIED
        // company are indistinguishable — both are a plain 404.
        $exceptions->render(fn (CompanyNotPublic $exception, Request $request) => ContractResponse::error($request, 'NOT_FOUND', 404, 'Perusahaan tidak ditemukan.'));
        $exceptions->render(fn (CompanyMemberNotFound $exception, Request $request) => ContractResponse::error($request, 'NOT_FOUND', 404, 'Anggota perusahaan tidak ditemukan.'));
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
