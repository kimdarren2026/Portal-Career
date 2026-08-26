<?php

declare(strict_types=1);

namespace App\Domains\Shared\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Per-IP throttle for the public/unauthenticated discovery surface
 * (API_CONTRACT.md — "Authorization: PUBLIC. Rate-limited per IP.").
 *
 * The frozen contract requires that a limit exists; it names no numeric
 * threshold. The figure below is TECHNICAL/OPERATIONAL CONFIGURATION, not a
 * Product Owner–approved business rule, and may be tuned without a contract
 * change. Follows the same digested-IP-key convention as AuthAbuseControls.
 */
final class PublicDiscoveryRateLimiter
{
    private const MAX_ATTEMPTS = 120;

    private const DECAY_MINUTES = 1;

    /** @return array{blocked: bool, retry_after: int} */
    public function checkAndRecord(Request $request): array
    {
        $key = 'public-discovery:ip:'.hash('sha256', (string) ($request->ip() ?? 'unknown'));

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            return ['blocked' => true, 'retry_after' => RateLimiter::availableIn($key)];
        }

        RateLimiter::hit($key, self::DECAY_MINUTES * 60);

        return ['blocked' => false, 'retry_after' => 0];
    }
}
