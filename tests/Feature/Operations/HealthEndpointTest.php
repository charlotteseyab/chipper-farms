<?php

namespace Tests\Feature\Operations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Readiness and metrics endpoints (CF-P1-022).
 *
 * Laravel's /up answers "did PHP boot" and returns 200 while the database is
 * unreachable, so it cannot be used to decide whether an instance should receive
 * traffic. These endpoints answer readiness, and the tests below pin the two
 * properties that make them safe to expose:
 *
 *   - they work without authentication (a readiness probe must answer while
 *     sessions and the database are broken — exactly when auth cannot run);
 *   - they leak nothing without the shared token.
 *
 * They also guard a routing hazard: /health/ready sits alongside the livestock
 * route /health/{pig}. If registration order ever changes, "ready" would be
 * captured as a pig id and the probe would 404 or 403 — a silent monitoring
 * outage that looks like an application fault.
 */
class HealthEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_readiness_endpoint_is_reachable_without_authentication(): void
    {
        $response = $this->getJson('/health/ready');

        $this->assertContains(
            $response->getStatusCode(),
            [200, 503],
            'Readiness must return 200 or 503, never a redirect to login. A probe cannot '
            . 'authenticate, and 302 would read as healthy to most load balancers.',
        );
    }

    public function test_readiness_reports_per_check_status(): void
    {
        $response = $this->getJson('/health/ready');

        $response->assertJsonStructure([
            'status',
            'checks' => ['database', 'cache', 'storage'],
            'timestamp',
        ]);
    }

    /**
     * The routing hazard. /health/ready must not be swallowed by /health/{pig}.
     */
    public function test_readiness_route_is_not_captured_by_the_pig_health_route(): void
    {
        $route = app('router')->getRoutes()->match(
            \Illuminate\Http\Request::create('/health/ready', 'GET')
        );

        $this->assertSame(
            'health.ready',
            $route->getName(),
            '/health/ready resolved to ' . ($route->getName() ?? 'an unnamed route')
            . ' instead of the readiness probe. The literal health routes must be '
            . 'registered before health/{pig}.',
        );
    }

    public function test_detail_is_withheld_without_a_token(): void
    {
        config(['app.health_check_token' => 'super-secret-token']);

        $response = $this->getJson('/health/ready');

        $response->assertJsonMissingPath('detail');
    }

    public function test_detail_is_returned_with_a_valid_token(): void
    {
        config(['app.health_check_token' => 'super-secret-token']);

        $response = $this->getJson('/health/ready?token=super-secret-token');

        $response->assertJsonStructure(['detail' => ['checks', 'outbox', 'app']]);
    }

    /**
     * An unset token must mean "no detail", not "no authentication required".
     * Defaulting to open is how monitoring endpoints become public dashboards.
     */
    public function test_unset_token_refuses_detail_rather_than_defaulting_to_open(): void
    {
        config(['app.health_check_token' => null]);

        $this->getJson('/health/ready?token=')->assertJsonMissingPath('detail');
        $this->getJson('/health/ready')->assertJsonMissingPath('detail');
    }

    public function test_metrics_are_hidden_without_a_token(): void
    {
        config(['app.health_check_token' => 'super-secret-token']);

        // 404 rather than 401: an unauthenticated caller should not learn that a
        // metrics endpoint exists here.
        $this->getJson('/health/metrics')->assertNotFound();
    }

    public function test_metrics_are_returned_with_a_valid_token(): void
    {
        config(['app.health_check_token' => 'super-secret-token']);

        $this->getJson('/health/metrics?token=super-secret-token')
            ->assertOk()
            ->assertJsonStructure(['outbox', 'failed_jobs', 'queue', 'timestamp']);
    }

    /**
     * A failing dependency must not surface connection details.
     */
    public function test_public_response_never_contains_exception_detail(): void
    {
        $body = $this->getJson('/health/ready')->getContent();

        foreach (['password', 'SQLSTATE', 'Connection refused', '127.0.0.1'] as $needle) {
            $this->assertStringNotContainsString(
                $needle,
                (string) $body,
                "The public readiness response contained '{$needle}'. Driver errors carry "
                . 'host, port, database and username.',
            );
        }
    }
}
