<?php

use App\Http\Middleware\AssignCorrelationId;
use App\Http\Middleware\EnforceAuthAbuseControls;
use App\Http\Middleware\EnsureAccountStatus;
use App\Http\Middleware\EnsureVerifiedEmail;
use App\Http\Middleware\EnforceCandidateDocumentUploadRateLimit;
use App\Http\Middleware\EnforceCompanyMemberInviteRateLimit;
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
use App\Domains\Company\Exceptions\LastCompanyAdmin;
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
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if (in_array($request->path(), ['me', 'me/password', 'auth/logout'], true) || $request->is('candidate/*') || $request->is('companies/*') || $request->is('companies')) {
                return ContractResponse::error($request, 'UNAUTHENTICATED', 401, 'Sesi autentikasi diperlukan.');
            }
        });
        $exceptions->render(fn (CandidateProfileRequiredException $exception, Request $request) => ContractResponse::error($request, 'CANDIDATE_PROFILE_REQUIRED', 422, 'Profil kandidat tidak tersedia.'));
        $exceptions->render(fn (LastCompanyAdmin $exception, Request $request) => ContractResponse::error($request, 'MEMBER_LAST_ADMIN', 409, 'Setidaknya satu Company Admin aktif harus dipertahankan.'));
        $exceptions->render(fn (CompanyMemberAlreadyActive $exception, Request $request) => ContractResponse::error($request, 'MEMBER_ALREADY_ACTIVE', 409, 'Anggota ini sudah aktif di perusahaan tersebut.'));
        // Out of COMPANY_SCOPE and absent answer identically: 403 would confirm
        // the row exists to an actor who must not know it does (matrix §1).
        $exceptions->render(fn (CompanyNotFound $exception, Request $request) => ContractResponse::error($request, 'NOT_FOUND', 404, 'Perusahaan tidak ditemukan.'));
        $exceptions->render(fn (CompanyMemberNotFound $exception, Request $request) => ContractResponse::error($request, 'NOT_FOUND', 404, 'Anggota perusahaan tidak ditemukan.'));
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
