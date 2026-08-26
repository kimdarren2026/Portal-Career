<?php

declare(strict_types=1);

namespace Tests\Feature\Bootstrap;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Bootstrap verification only. No domain behaviour is tested here — the business
 * domains are not implemented in this phase.
 */
final class ApplicationBootTest extends TestCase
{
    public function test_application_boots_and_reports_a_version(): void
    {
        $this->assertNotEmpty(app()->version());
        $this->assertSame('Portal Karir Kampus', config('app.name'));
    }

    public function test_health_endpoint_responds(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_inertia_page_renders_through_vue_root_view(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        // Inertia's root view carries the page payload in a data-page attribute.
        $response->assertSee('data-page', escape: false);
        $response->assertSee('Health', escape: false);
    }

    public function test_correlation_id_is_returned_on_every_response(): void
    {
        $response = $this->get('/');

        $this->assertNotEmpty($response->headers->get('X-Request-Id'));
    }

    public function test_supplied_correlation_id_is_echoed_back(): void
    {
        $response = $this->withHeader('X-Request-Id', 'test-correlation-id')->get('/');

        $this->assertSame('test-correlation-id', $response->headers->get('X-Request-Id'));
    }

    public function test_sanctum_authenticated_api_surface_is_not_yet_exposed(): void
    {
        // The Sanctum bearer-token authenticated /api/v1 surface (57 endpoints,
        // API_ENDPOINTS.md) is reserved and activates in a later phase together
        // with Sanctum and its personal_access_tokens table. Declaring those
        // routes before their Actions exist would create a compatibility
        // promise we cannot keep. Only the frozen, unauthenticated public
        // discovery routes (routes/api.php) are active in this phase.
        $authenticatedApiRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => str_starts_with($route->uri(), 'api/v1'))
            ->reject(fn ($route) => str_starts_with($route->uri(), 'api/v1/public'))
            ->all();

        $this->assertEmpty($authenticatedApiRoutes);
    }
}
