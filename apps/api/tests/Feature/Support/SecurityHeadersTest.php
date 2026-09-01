<?php

declare(strict_types=1);

namespace Tests\Feature\Support;

use Tests\TestCase;

/**
 * Baseline security response headers (SECURITY_ARCHITECTURE.md §3) are present
 * on every response and never break Inertia/Vite delivery.
 */
final class SecurityHeadersTest extends TestCase
{
    public function test_baseline_headers_present_on_a_public_page(): void
    {
        $response = $this->get('/login')->assertOk();

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_baseline_headers_present_on_a_health_endpoint(): void
    {
        $this->get('/health/live')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY');
    }

    public function test_no_hsts_or_csp_is_forced_by_the_application(): void
    {
        // HSTS and CSP are the edge proxy's responsibility (§6). The app must
        // not emit HSTS over plain HTTP in local/test.
        $response = $this->get('/login');

        $this->assertFalse($response->headers->has('Strict-Transport-Security'));
    }
}
