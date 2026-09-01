<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline security response headers (SECURITY_ARCHITECTURE.md §3).
 *
 * Only the deterministic, zero-risk headers are set here as defence in depth —
 * the edge proxy is still the primary place for security headers
 * (DEPLOYMENT_ARCHITECTURE.md §6), and remains responsible for HSTS (which
 * must not be emitted before HTTPS is guaranteed) and the Content-Security-
 * Policy (which must be tuned against the compiled Vite/Inertia asset set and
 * self-hosted fonts — §3 "production must compile Tailwind locally and
 * self-host fonts"). Nothing here can break Inertia or Vite.
 */
final class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $headers = $response->headers;
        $headers->set('X-Content-Type-Options', 'nosniff', false);
        // The portal is never framed. Matches SECURITY_ARCHITECTURE.md §3
        // "X-Frame-Options: DENY / CSP frame-ancestors 'none'".
        $headers->set('X-Frame-Options', 'DENY', false);
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin', false);

        return $response;
    }
}
