<?php

namespace Tests\Feature\Routes;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Feature\Finance\FinanceTestSetup;
use Database\Seeders\FarmOperationsPermissionsSeeder;
use Tests\TestCase;

class FinanceRouteViewRegressionTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    protected function setUp(): void
    {
        parent::setUp();

        // Farm-operations permissions gate the feed, raw-material and
        // weight-alert routes exercised here (CF-P0-002). Without this the
        // admin fixture holds no livestock/feed permission and gets 403.
        $this->seed(FarmOperationsPermissionsSeeder::class);
        $this->setUpFinanceFixtures();
    }

    public function test_finance_routes_are_not_nested_under_api_prefix(): void
    {
        $dashboard = Route::getRoutes()->getByName('finance.dashboard');

        $this->assertNotNull($dashboard);
        $this->assertSame('finance', $dashboard->uri());

        foreach (Route::getRoutes() as $route) {
            $name = $route->getName();
            if ($name !== null && str_starts_with($name, 'finance.')) {
                $this->assertFalse(
                    str_starts_with($route->uri(), 'api/finance'),
                    "Finance route {$name} is still nested under api/finance",
                );
            }
        }
    }

    public function test_weight_alert_create_route_renders_and_broken_edit_update_routes_are_absent(): void
    {
        $this->assertTrue(Route::has('weight-alerts.create'));
        $this->assertTrue(Route::has('weight-alerts.store'));
        $this->assertFalse(Route::has('weight-alerts.edit'));
        $this->assertFalse(Route::has('weight-alerts.update'));

        $this->actingAs($this->user);

        $response = $this->get(route('weight-alerts.create'));

        $response->assertOk();
        $response->assertViewIs('weight-alerts.create');
    }
}
