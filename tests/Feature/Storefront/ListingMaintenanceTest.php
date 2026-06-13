<?php

namespace Tests\Feature\Storefront;

use App\Models\PigManagement;
use App\Models\Storefront\StorefrontListing;
use App\Models\Storefront\StorefrontListingMedia;
use App\Storefront\Services\ListingAvailabilitySyncService;
use App\Storefront\Services\ListingEditorService;
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
}
