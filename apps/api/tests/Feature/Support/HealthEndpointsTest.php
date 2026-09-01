<?php

declare(strict_types=1);

namespace Tests\Feature\Support;

use Tests\TestCase;

/**
 * Operational health endpoints (DEPLOYMENT_ARCHITECTURE.md §5) — unauthenticated,
 * pass/fail per dependency, no disclosure of host / credential / path / driver.
 */
final class HealthEndpointsTest extends TestCase
{
    public function test_liveness_is_a_dependency_free_ok(): void
    {
        $this->getJson('/health/live')
            ->assertOk()
            ->assertExactJson(['status' => 'ok']);
    }

    public function test_readiness_reports_per_dependency_status(): void
    {
        $response = $this->getJson('/health/ready')->assertOk();

        $response->assertJsonPath('status', 'ready')
            ->assertJsonPath('checks.database', 'ok')
            ->assertJsonPath('checks.redis', 'ok')
            ->assertJsonPath('checks.storage', 'ok');
    }

    public function test_health_endpoints_are_unauthenticated_and_leak_nothing(): void
    {
        $body = $this->getJson('/health/ready')->getContent();

        foreach (['password', 'DB_', 'REDIS_', 'AWS_', 'pgsql', '127.0.0.1', 'Exception', base_path()] as $needle) {
            $this->assertStringNotContainsString($needle, $body);
        }
    }
}
