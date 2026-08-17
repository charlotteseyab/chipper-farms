<?php

namespace Tests\Feature\Storefront;

use App\Models\PigManagement;
use App\Models\Storefront\StorefrontListing;
use App\Models\Storefront\StorefrontListingMedia;
use App\Models\Storefront\StorefrontListingPig;
use App\Storefront\Services\ListingAvailabilitySyncService;
use App\Storefront\Services\ListingEditorService;
use App\Storefront\Services\StorefrontPigPoolService;
use App\Storefront\Services\StorefrontPigPricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ListingMaintenanceTest extends StorefrontTestCase
{
    use RefreshDatabase;

    public function test_sync_demotes_published_listing_whose_pig_was_sold(): void
    {
        $this->makeUser('admin'); // system actor for sync
        $pig = $this->makePig();
        $listing = $this->makePublishedListing([
            'kind'        => StorefrontListing::KIND_LIVE_PIG,
            'source_type' => PigManagement::class,
            'source_id'   => $pig->id,
        ]);

        // Operational sale: pig soft-deleted.
        $pig->delete();

        $result = $this->app->make(ListingAvailabilitySyncService::class)->sync($this->org->id);

        $this->assertSame(1, $result['checked']);
        $this->assertSame(1, $result['demoted']);
        $this->assertSame(StorefrontListing::STATUS_SOLD_OUT, $listing->fresh()->status);
    }

    public function test_sync_leaves_available_listing_published(): void
    {
        $this->makeUser('admin');
        $pig = $this->makePig();
        $listing = $this->makePublishedListing([
            'kind'        => StorefrontListing::KIND_LIVE_PIG,
            'source_type' => PigManagement::class,
            'source_id'   => $pig->id,
        ]);

        $result = $this->app->make(ListingAvailabilitySyncService::class)->sync($this->org->id);

        $this->assertSame(0, $result['demoted']);
        $this->assertSame(StorefrontListing::STATUS_PUBLISHED, $listing->fresh()->status);
    }

    public function test_editor_updates_only_editable_fields(): void
    {
        $actor   = $this->makeUser('salesperson');
        $listing = $this->makeListing(['title' => 'Old title', 'status' => StorefrontListing::STATUS_DRAFT]);

        $service = $this->app->make(ListingEditorService::class);
        $updated = $service->update($listing, [
            'title'              => 'New title',
            'fixed_price'        => '750.00',
            'quantity_available' => '3',
            'status'             => StorefrontListing::STATUS_PUBLISHED, // not in EDITABLE — must be ignored
        ], $actor);

        $this->assertSame('New title', $updated->title);
        $this->assertSame('750.00', $updated->fixed_price);
        // status is NOT editable through the editor — stays draft.
        $this->assertSame(StorefrontListing::STATUS_DRAFT, $updated->status);
        $this->assertSame($actor->id, $updated->updated_by_user_id);
    }

    public function test_media_primary_promotion_on_remove(): void
    {
        $listing = $this->makePublishedListing();
        $service = $this->app->make(ListingEditorService::class);

        $first  = $service->addUrlMedia($listing, 'https://example.com/a.jpg');
        $second = $service->addUrlMedia($listing, 'https://example.com/b.jpg');

        // First added becomes primary.
        $this->assertTrue($first->fresh()->is_primary);
        $this->assertFalse($second->fresh()->is_primary);

        // Removing the primary promotes the next one.
        $service->removeMedia($first);
        $this->assertTrue($second->fresh()->is_primary);
        $this->assertSame(1, StorefrontListingMedia::where('listing_id', $listing->id)->count());
    }

    public function test_edit_page_matches_the_create_flow_for_stock_backed_listings(): void
    {
        $this->seedStorefrontPermissions();
        $this->seedStorefrontQuotePermissions();

        $pig = $this->makePig(['ear_tag_number' => 'EDIT-STOCK-1']);
        $listing = $this->makeListing([
            'kind'               => StorefrontListing::KIND_LIVE_PIG,
            'source_type'        => PigManagement::class,
            'source_id'          => $pig->id,
            'title'              => 'Editable stock listing',
            'quantity_available' => '1',
            'quantity_unit'      => 'head',
        ]);

        $this->actingAs($this->makeUser('farmmanager'))
            ->get(route('storefront.admin.listings.edit', $listing))
            ->assertOk()
            ->assertSee('1. What are you selling?')
            ->assertSee('Standard Stock')
            ->assertSee('Stock type is locked')
            ->assertSee('2. Listing')
            ->assertSee('3. Media')
            ->assertSee('Recommended image size: 1200 x 900 px')
            ->assertSee('4. Stock &amp; price', false)
            ->assertSee('Standard Stock for this listing')
            ->assertSee('Select all visible')
            ->assertSee('Clear selected')
            ->assertSee('Priced from your farm setup')
            ->assertDontSee('Sale mode')
            ->assertDontSee('Pricing basis')
            ->assertDontSee('Fixed price')
            ->assertDontSee('Unit');
    }

    public function test_edit_save_can_add_and_deselect_standard_stock(): void
    {
        $this->seedStorefrontPermissions();
        $this->seedStorefrontQuotePermissions();

        $actor = $this->makeUser('farmmanager');
        $pigs = [
            $this->makePig(['ear_tag_number' => 'EDIT-POOL-1']),
            $this->makePig(['ear_tag_number' => 'EDIT-POOL-2']),
            $this->makePig(['ear_tag_number' => 'EDIT-POOL-3']),
        ];

        $listing = $this->makeListing([
            'kind'               => StorefrontListing::KIND_LIVE_PIG,
            'source_category'    => StorefrontPigPricingService::CATEGORY_STANDARD,
            'title'              => 'Editable pooled stock',
            'quantity_available' => '2',
            'quantity_unit'      => 'head',
        ]);

        $this->app->make(StorefrontPigPoolService::class)
            ->attachPigs($listing, [$pigs[0]->id, $pigs[1]->id], $actor);

        $this->actingAs($actor)
            ->put(route('storefront.admin.listings.update', $listing), [
                'title'                 => 'Edited pooled stock',
                'category_id'           => null,
                'public_description'    => 'Updated public copy',
                'seller_note'           => null,
                'sale_mode'             => $listing->sale_mode,
                'pricing_basis'         => $listing->pricing_basis,
                'price_per_kg'          => $listing->price_per_kg,
                'fixed_price'           => $listing->fixed_price,
                'currency_code'         => $listing->currency_code,
                'pool_pig_ids_present'  => true,
                'pool_pig_ids'          => [$pigs[1]->id, $pigs[2]->id],
                'quantity_available'    => $listing->quantity_available,
                'quantity_unit'         => $listing->quantity_unit,
                'estimated_weight_kg'   => $listing->estimated_weight_kg,
                'pickup_allowed'        => true,
                'delivery_allowed'      => false,
                'delivery_fee'          => null,
                'fulfilment_notes'      => null,
            ])
            ->assertRedirect(route('storefront.admin.listings.show', $listing));

        $this->assertSame('Edited pooled stock', $listing->fresh()->title);
        $this->assertSame('2.0000', (string) $listing->fresh()->quantity_available);

        $this->assertDatabaseHas('storefront_listing_pigs', [
            'storefront_listing_id' => $listing->id,
            'pig_id'                => $pigs[0]->id,
            'status'                => StorefrontListingPig::STATUS_REMOVED,
        ]);
        $this->assertDatabaseHas('storefront_listing_pigs', [
            'storefront_listing_id' => $listing->id,
            'pig_id'                => $pigs[1]->id,
            'status'                => StorefrontListingPig::STATUS_AVAILABLE,
        ]);
        $this->assertDatabaseHas('storefront_listing_pigs', [
            'storefront_listing_id' => $listing->id,
            'pig_id'                => $pigs[2]->id,
            'status'                => StorefrontListingPig::STATUS_AVAILABLE,
        ]);
    }

    public function test_edit_save_can_add_media_like_create(): void
    {
        $this->seedStorefrontPermissions();
        $this->seedStorefrontQuotePermissions();

        $listing = $this->makeListing([
            'title'         => 'Listing before media',
            'quantity_unit' => 'unit',
        ]);

        $this->actingAs($this->makeUser('farmmanager'))
            ->put(route('storefront.admin.listings.update', $listing), [
                'title'              => 'Listing after media',
                'category_id'        => null,
                'public_description' => null,
                'seller_note'        => null,
                'sale_mode'          => $listing->sale_mode,
                'pricing_basis'      => $listing->pricing_basis,
                'price_per_kg'       => $listing->price_per_kg,
                'fixed_price'        => $listing->fixed_price,
                'currency_code'      => $listing->currency_code,
                'quantity_available' => $listing->quantity_available,
                'quantity_unit'      => $listing->quantity_unit,
                'estimated_weight_kg'=> $listing->estimated_weight_kg,
                'pickup_allowed'     => true,
                'delivery_allowed'   => false,
                'delivery_fee'       => null,
                'fulfilment_notes'   => null,
                'media_url'          => 'https://example.com/edit-photo.jpg',
                'alt_text'           => 'Edited listing image',
            ])
            ->assertRedirect(route('storefront.admin.listings.show', $listing));

        $listing->refresh();

        $this->assertSame('Listing after media', $listing->title);
        $this->assertDatabaseHas('storefront_listing_media', [
            'listing_id' => $listing->id,
            'path'       => 'https://example.com/edit-photo.jpg',
            'alt_text'   => 'Edited listing image',
            'is_primary' => true,
        ]);
    }
}
