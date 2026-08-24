<?php

declare(strict_types=1);

namespace Tests\Feature\Bootstrap;

use Tests\TestCase;

/**
 * Guards against leaking environment or framework internals through HTTP.
 * SECURITY_ARCHITECTURE.md §8: debug mode off outside local, stack traces never
 * rendered to users, framework default routes not exposed in production.
 */
final class DebugExposureTest extends TestCase
{
    public function test_health_endpoint_discloses_no_environment_detail(): void
    {
        $body = $this->get('/up')->getContent() ?: '';

        foreach (['APP_KEY', 'DB_PASSWORD', 'REDIS_PASSWORD', 'AWS_SECRET', 'SMTP_CONFIG_ENCRYPTION_KEY'] as $secret) {
            $this->assertStringNotContainsString($secret, $body);
        }

        $appKey = (string) config('app.key');
        if ($appKey !== '') {
            $this->assertStringNotContainsString($appKey, $body);
        }
    }

    public function test_unknown_route_returns_404_without_a_stack_trace(): void
    {
        config()->set('app.debug', false);

        $response = $this->get('/this-route-does-not-exist');
        $body = $response->getContent() ?: '';

        $response->assertNotFound();
        $this->assertStringNotContainsString('Stack trace', $body);
        $this->assertStringNotContainsString('vendor/laravel/framework', $body);
    }

    public function test_application_key_is_set(): void
    {
        $this->assertNotEmpty(config('app.key'));
    }
}
