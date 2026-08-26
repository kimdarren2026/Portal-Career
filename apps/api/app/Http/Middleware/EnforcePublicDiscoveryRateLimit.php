<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domains\Shared\Support\PublicDiscoveryRateLimiter;
use App\Http\Responses\ContractResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Public vacancy discovery is rate-limited per IP (API_CONTRACT.md). */
final class EnforcePublicDiscoveryRateLimit
{
    public function __construct(private readonly PublicDiscoveryRateLimiter $limiter) {}

    public function handle(Request $request, Closure $next): Response
    {
        $rate = $this->limiter->checkAndRecord($request);
        if ($rate['blocked']) {
            return ContractResponse::error(
                $request,
                'RATE_LIMITED',
                429,
                'Terlalu banyak permintaan. Silakan coba kembali nanti.',
                retryAfter: $rate['retry_after'],
            );
        }

        return $next($request);
    }
}
