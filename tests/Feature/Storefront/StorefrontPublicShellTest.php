<?php

namespace Tests\Feature\Storefront;

use App\Models\Storefront\StorefrontListing;
use App\Services\Organizations\OrganizationFeatureService;
use App\Support\Features\FeatureCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;

class StorefrontPublicShellTest extends StorefrontTestCase
{
    use RefreshDatabase;

    public function test_public_shop_uses_chipper_farms_shell_without_back_office_links(): void
    {
        $this->withoutVite();

        app(OrganizationFeatureService::class)->enable(FeatureCatalog::STOREFRONT_FEED_SALES, $this->org);

        $this->makePublishedListing([
            'title' => 'Starter feed bundle',
            'kind' => StorefrontListing::KIND_FEED,
            'pricing_basis' => StorefrontListing::BASIS_FIXED,
            'fixed_price' => '250.00',
            'quantity_available' => '5',
        ]);

        $this->get(route('storefront.shop'))
            ->assertOk()
            ->assertSee('Chipper Farms')
            ->assertSee('Chipper Farms logo')
            ->assertSee('Shop')
            ->assertSee('Shop all')
            ->assertSee('Cart')
            ->assertSee('Sign In')
            ->assertSee('Starter feed bundle')
            ->assertDontSee('storefront-admin')
            ->assertDontSee('Modules & Features');
    }
}
