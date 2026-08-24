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

    public function test_versioned_api_surface_is_not_yet_exposed(): void
    {
        // /api/v1 is reserved and activated in a later phase. Declaring routes
        // before their Actions exist would create a compatibility promise we
        // cannot keep (API_CONTRACT.md Part I §2b).
        $this->assertEmpty(
            collect(Route::getRoutes()->getRoutes())
                ->filter(fn ($route) => str_starts_with($route->uri(), 'api/v1'))
                ->all()
        );
    }
}
