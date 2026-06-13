<?php

namespace Tests\Feature\Storefront;

use App\Models\Storefront\StorefrontCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Route-level access control.
 *
 * NOTE: each test exercises a SINGLE authenticated actor. Chaining two
 * actingAs() calls against an auth:sanctum (stateful) route inside one test
 * method bleeds session state between requests — a Laravel testing artifact,
 * not application behaviour — so we keep one actor per method.
 */
class StorefrontRouteAccessTest extends StorefrontTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        StorefrontCategory::create([
            'organization_id' => $this->org->id,
            'code' => 'live-pigs', 'name' => 'Live Pigs', 'slug' => 'live-pigs',
            'kind' => StorefrontCategory::KIND_LIVE_PIG, 'is_active' => true,
        ]);
    }

    public function test_public_home_is_reachable_without_auth(): void
    {
        $this->get(route('storefront.home'))->assertOk();
    }

    public function test_public_shop_is_reachable_without_auth(): void
    {
        $this->get(route('storefront.shop'))->assertOk();
    }

    public function test_salesperson_can_reach_back_office_dashboard(): void
    {
        $this->seedStorefrontPermissions();
        $this->actingAs($this->makeUser('salesperson'))
            ->get(route('storefront.admin.dashboard'))->assertOk();
    }

    public function test_customer_cannot_reach_back_office_dashboard(): void
    {
        $this->seedStorefrontPermissions();
        $this->actingAs($this->makeUser('customer'))
            ->get(route('storefront.admin.dashboard'))->assertForbidden();
    }

    public function test_guest_is_redirected_from_back_office_dashboard(): void
    {
        $this->get(route('storefront.admin.dashboard'))->assertRedirect(route('login'));
    }

    public function test_salesperson_can_reach_push_wizard(): void
    {
        $this->seedStorefrontPermissions();
        $this->actingAs($this->makeUser('salesperson'))
            ->get(route('storefront.admin.push.create'))->assertOk();
    }

    public function test_customer_cannot_reach_push_wizard(): void
    {
        $this->seedStorefrontPermissions();
        $this->actingAs($this->makeUser('customer'))
            ->get(route('storefront.admin.push.create'))->assertForbidden();
    }

    public function test_customer_account_requires_auth(): void
    {
        $this->get(route('storefront.account.dashboard'))->assertRedirect(route('login'));
    }

    public function test_customer_can_reach_their_account(): void
    {
        $this->seedStorefrontPermissions();
        $this->actingAs($this->makeUser('customer'))
            ->get(route('storefront.account.dashboard'))->assertOk();
    }
}
