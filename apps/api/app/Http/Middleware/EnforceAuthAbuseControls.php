<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domains\Identity\Support\AuthAbuseControls;
use App\Http\Responses\ContractResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnforceAuthAbuseControls
{
    public function __construct(private readonly AuthAbuseControls $controls) {}

    public function handle(Request $request, Closure $next, string $operation): Response
    {
        $rate = $this->controls->checkAndRecord($operation, $request);
        $lockRetry = $operation === 'login'
            ? $this->controls->loginLockRetryAfter((string) $request->input('email', ''))
            : 0;

        if ($rate['blocked'] || $lockRetry > 0) {
            $retryAfter = max($rate['retry_after'], $lockRetry);
            if ($lockRetry > 0) {
                return ContractResponse::error(
                    $request,
                    'AUTH_ACCOUNT_LOCKED',
                    429,
                    'Terlalu banyak percobaan masuk. Silakan coba kembali nanti.',
                    retryAfter: $retryAfter,
                );
            }

            return ContractResponse::error(
                $request,
                'RATE_LIMITED',
                429,
                'Terlalu banyak permintaan. Silakan coba kembali nanti.',
                retryAfter: $retryAfter,
            );
        }

        return $next($request);
    }
}
