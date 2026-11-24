<?php

namespace Tests\Feature\Features;

use App\Support\Features\FeatureCatalog;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Every finance switch on Modules & Features must close something. A switch
 * that only hides menu links leaves its pages open to anyone who types the
 * address, which for a paid add-on means giving it away.
 */
class FeatureEnforcementTest extends TestCase
{
    /**
     * Procurement Approvals is the one exception: purchase requests and goods
     * receiving are how every farm's feed purchases reach stock, so gating
     * them on a feature that starts off would stop free farms buying feed.
     */
    private const NOT_ROUTE_GATED = [FeatureCatalog::FINANCE_PROCUREMENT_APPROVALS];

    public function test_every_finance_switch_closes_at_least_one_page(): void
    {
        $gated = [];
        foreach (Route::getRoutes()->getRoutes() as $route) {
            foreach ($route->gatherMiddleware() as $middleware) {
                if (is_string($middleware) && str_starts_with($middleware, 'feature:')) {
                    foreach (explode(',', substr($middleware, 8)) as $key) {
                        $gated[$key] = true;
                    }
                }
            }
        }

        $dead = array_values(array_filter(
            FeatureCatalog::inDomain(FeatureCatalog::DOMAIN_FINANCE),
            fn ($key) => ! isset($gated[$key]) && ! in_array($key, self::NOT_ROUTE_GATED, true),
        ));

        $this->assertSame([], $dead, 'These switches close no page: ' . implode(', ', $dead));
    }
}
