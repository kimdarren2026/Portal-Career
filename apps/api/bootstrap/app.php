<?php

use App\Http\Middleware\AssignCorrelationId;
use App\Http\Middleware\EnforceAuthAbuseControls;
use App\Http\Middleware\EnsureAccountStatus;
use App\Http\Middleware\EnsureVerifiedEmail;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Responses\ContractResponse;
use Illuminate\Auth\AuthenticationException;
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
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if (in_array($request->path(), ['me', 'me/password', 'auth/logout'], true)) {
                return ContractResponse::error($request, 'UNAUTHENTICATED', 401, 'Sesi autentikasi diperlukan.');
            }
        });
    })->create();
