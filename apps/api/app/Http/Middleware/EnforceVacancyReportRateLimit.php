<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Responses\ContractResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Anti-spam for "Laporkan Lowongan" submissions (PGC-V1 / PD-C):
 *  - anonymous / public: 5 submissions per IP per hour
 *  - authenticated:     10 submissions per account per day
 *
 * These figures are the Product Owner–approved anti-spam thresholds. CSRF
 * still applies (web middleware group).
 */
final class EnforceVacancyReportRateLimit
{
    private const ANON_MAX = 5;
    private const ANON_DECAY_SECONDS = 3600;

    private const AUTH_MAX = 10;
    private const AUTH_DECAY_SECONDS = 86400;

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null) {
            $key = 'vacancy-report:user:'.$user->getKey();
            $max = self::AUTH_MAX;
            $decay = self::AUTH_DECAY_SECONDS;
        } else {
            $key = 'vacancy-report:ip:'.hash('sha256', (string) ($request->ip() ?? 'unknown'));
            $max = self::ANON_MAX;
            $decay = self::ANON_DECAY_SECONDS;
        }

        if (RateLimiter::tooManyAttempts($key, $max)) {
            return ContractResponse::error(
                $request,
                'RATE_LIMITED',
                429,
                'Terlalu banyak laporan dikirim. Silakan coba kembali nanti.',
                retryAfter: RateLimiter::availableIn($key),
            );
        }

        RateLimiter::hit($key, $decay);

        return $next($request);
    }
}
