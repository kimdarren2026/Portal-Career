<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Accepts or generates a correlation ID, exposes it to the logging context, and
 * echoes it on every response.
 *
 * Required by FSD §9.3 (error messages carry a correlation ID for support) and
 * API_CONTRACT.md Part I §10. The same value is later stored in
 * audit_logs.correlation_id, so one identifier ties a UI error to its server
 * logs, its queued jobs, and its audit trail.
 */
final class AssignCorrelationId
{
    public const HEADER = 'X-Request-Id';

    public function handle(Request $request, Closure $next): Response
    {
        $correlationId = $request->headers->get(self::HEADER);

        if (! is_string($correlationId) || $correlationId === '' || strlen($correlationId) > 128) {
            $correlationId = (string) Str::uuid();
        }

        $request->headers->set(self::HEADER, $correlationId);
        $request->attributes->set('correlation_id', $correlationId);

        \Illuminate\Support\Facades\Log::shareContext(['correlation_id' => $correlationId]);

        $response = $next($request);
        $response->headers->set(self::HEADER, $correlationId);

        return $response;
    }
}
