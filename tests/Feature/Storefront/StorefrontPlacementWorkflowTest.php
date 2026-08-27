<?php

namespace Tests\Feature\Storefront;

use App\Models\Storefront\StorefrontPlacement;
use Database\Seeders\StorefrontCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

class StorefrontPlacementWorkflowTest extends StorefrontTestCase
{
    use RefreshDatabase;

    public function test_seeded_empty_spotlight_row_does_not_hide_the_pinned_listing(): void
    {
        $this->withoutVite();
        $this->seed(StorefrontCatalogSeeder::class);

        $listing = $this->makePublishedListing(['title' => 'Homepage spotlight pig']);

        StorefrontPlacement::create([
            'organization_id' => $this->org->id,
            'code' => StorefrontPlacement::CODE_HOME_HERO,
            'listing_id' => $listing->id,
            'position' => 0,
            'is_active' => true,
        ]);

        $this->get(route('storefront.home'))
            ->assertOk()
            ->assertSee('Homepage spotlight pig')
            ->assertDontSee('mx-auto hidden w-full max-w-xl', false);
    }

    public function test_spotlight_listing_is_also_visible_on_the_public_homepage(): void
    {
        $this->withoutVite();
        $this->seed(StorefrontCatalogSeeder::class);

        $listing = $this->makePublishedListing(['title' => 'Public homepage pig']);

        StorefrontPlacement::create([
            'organization_id' => $this->org->id,
            'code' => StorefrontPlacement::CODE_HOME_HERO,
            'listing_id' => $listing->id,
            'position' => 0,
            'is_active' => true,
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Featured from Chipper Farms')
            ->assertSee('Public homepage pig')
            ->assertSee(route('storefront.listing', $listing->slug), false);
    }

    public function test_every_selectable_featured_section_is_rendered_by_the_homepage(): void
    {
        $this->withoutVite();

        $listing = $this->makePublishedListing(['title' => 'Featured bulk bundle']);

        foreach ([StorefrontPlacement::CODE_BEST_SELLERS, StorefrontPlacement::CODE_BULK_ORDERS] as $code) {
            StorefrontPlacement::create([
                'organization_id' => $this->org->id,
                'code' => $code,
                'listing_id' => $listing->id,
                'position' => 1,
                'is_active' => true,
            ]);
        }

        $this->get(route('storefront.home'))
            ->assertOk()
            ->assertSee('Best sellers')
            ->assertSee('Bulk orders')
            ->assertSee('Featured bulk bundle');
    }

    public function test_pinning_a_new_spotlight_replaces_the_previous_one(): void
    {
        $this->seedStorefrontPermissions();
        $this->actingAs($this->makeUser('admin'));

        $old = $this->makePublishedListing(['title' => 'Old spotlight']);
        $new = $this->makePublishedListing(['title' => 'New spotlight']);

        StorefrontPlacement::create([
            'organization_id' => $this->org->id,
            'code' => StorefrontPlacement::CODE_HOME_HERO,
            'listing_id' => $old->id,
            'position' => 0,
            'is_active' => true,
        ]);

        $this->post(route('storefront.admin.placements.store'), [
            'code' => StorefrontPlacement::CODE_HOME_HERO,
            'listing_id' => $new->id,
        ])->assertRedirect();

        $this->assertDatabaseMissing('storefront_placements', [
            'code' => StorefrontPlacement::CODE_HOME_HERO,
            'listing_id' => $old->id,
        ]);
        $this->assertDatabaseHas('storefront_placements', [
            'code' => StorefrontPlacement::CODE_HOME_HERO,
            'listing_id' => $new->id,
            'position' => 0,
        ]);
    }
}
