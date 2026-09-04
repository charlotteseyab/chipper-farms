<?php

namespace Tests\Feature\Storefront;

use App\Models\PigManagement;
use App\Models\Storefront\StorefrontListing;
use App\Models\Storefront\StorefrontListingPig;
use App\Storefront\Services\Exceptions\InvalidListingTransitionException;
use App\Storefront\Services\ListingService;
use App\Storefront\Services\StorefrontPigPoolService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ListingServiceTest extends StorefrontTestCase
{
    use RefreshDatabase;

    protected function service(): ListingService
    {
        return $this->app->make(ListingService::class);
    }

    protected function makeListingForPig(PigManagement $pig, string $status = StorefrontListing::STATUS_DRAFT): StorefrontListing
    {
        return StorefrontListing::create([
            'organization_id'    => $this->org->id,
            'kind'               => StorefrontListing::KIND_LIVE_PIG,
            'source_type'        => PigManagement::class,
            'source_id'          => $pig->id,
            'title'              => 'Test listing',
            'slug'               => 'test-listing-' . uniqid(),
            'reference'          => 'LIST-' . uniqid(),
            'sale_mode'          => StorefrontListing::MODE_FIXED,
            'pricing_basis'      => StorefrontListing::BASIS_FIXED,
            'fixed_price'        => '500.00',
            'quantity_available' => '1',
            'status'             => $status,
        ]);
    }

    public function test_draft_can_be_published_then_unpublished(): void
    {
        $actor   = $this->makeUser('admin');
        $listing = $this->makeListingForPig($this->makePig());

        $published = $this->service()->publish($listing, $actor);
        $this->assertSame(StorefrontListing::STATUS_PUBLISHED, $published->status);
        $this->assertNotNull($published->published_at);

        $unpublished = $this->service()->unpublish($published, $actor);
        $this->assertSame(StorefrontListing::STATUS_UNPUBLISHED, $unpublished->status);
    }

    public function test_illegal_transition_throws(): void
    {
        $actor   = $this->makeUser('admin');
        $listing = $this->makeListingForPig($this->makePig(), StorefrontListing::STATUS_ARCHIVED);

        $this->expectException(InvalidListingTransitionException::class);
        $this->service()->publish($listing, $actor);
    }

    public function test_cannot_publish_when_source_pig_is_sold(): void
    {
        $actor = $this->makeUser('admin');
        $pig   = $this->makePig();
        $listing = $this->makeListingForPig($pig);

        // Simulate the operational sale: pig is soft-deleted.
        $pig->delete();

        $this->expectException(InvalidListingTransitionException::class);
        $this->service()->publish($listing, $actor);
    }

    public function test_transition_is_idempotent_to_same_status(): void
    {
        $actor   = $this->makeUser('admin');
        $listing = $this->makeListingForPig($this->makePig(), StorefrontListing::STATUS_PUBLISHED);

        // publish() on an already-published listing is a no-op, not an error.
        $result = $this->service()->publish($listing, $actor);
        $this->assertSame(StorefrontListing::STATUS_PUBLISHED, $result->status);
    }

    public function test_archiving_a_pooled_listing_releases_unsold_pigs_for_other_listings(): void
    {
        $actor = $this->makeUser('admin');
        $pig = $this->makePig(['ear_tag_number' => 'REL-ARCHIVE']);

        $first = $this->makePublishedListing([
            'title' => 'First listing',
            'kind' => StorefrontListing::KIND_LIVE_PIG,
        ]);

        $pool = $this->app->make(StorefrontPigPoolService::class);
        $pool->attachPigs($first, [$pig->id], $actor);

        $this->service()->archive($first, $actor);

        $this->assertSame(
            StorefrontListingPig::STATUS_REMOVED,
            StorefrontListingPig::where('storefront_listing_id', $first->id)
                ->where('pig_id', $pig->id)
                ->value('status'),
        );

        $second = $this->makePublishedListing([
            'title' => 'Second listing',
            'kind' => StorefrontListing::KIND_LIVE_PIG,
        ]);

        $pool->attachPigs($second, [$pig->id], $actor);

        $this->assertDatabaseHas('storefront_listing_pigs', [
            'storefront_listing_id' => $second->id,
            'pig_id' => $pig->id,
            'status' => StorefrontListingPig::STATUS_AVAILABLE,
        ]);
    }

    public function test_platform_superuser_cannot_delete_a_farms_listing(): void
    {
        $this->seedStorefrontPermissions();

        $superuser = $this->makeUser('superuser');
        $pig = $this->makePig(['ear_tag_number' => 'REL-DELETE']);
        $listing = $this->makePublishedListing([
            'title' => 'Delete me',
            'kind' => StorefrontListing::KIND_LIVE_PIG,
        ]);

        $pool = $this->app->make(StorefrontPigPoolService::class);
        $pool->attachPigs($listing, [$pig->id], $superuser);

        $this->actingAs($superuser)
            ->delete(route('storefront.admin.listings.destroy', $listing))
            ->assertForbidden();

        $this->assertNotSoftDeleted('storefront_listings', ['id' => $listing->id]);
        $this->assertDatabaseHas('storefront_listing_pigs', [
            'storefront_listing_id' => $listing->id,
            'pig_id' => $pig->id,
            'status' => StorefrontListingPig::STATUS_AVAILABLE,
        ]);
    }

    public function test_non_superuser_cannot_delete_a_listing(): void
    {
        $this->seedStorefrontPermissions();

        $listing = $this->makePublishedListing([
            'title' => 'Protected listing',
            'kind' => StorefrontListing::KIND_LIVE_PIG,
        ]);

        $this->actingAs($this->makeUser('admin'))
            ->delete(route('storefront.admin.listings.destroy', $listing))
            ->assertForbidden();

        $this->assertNotSoftDeleted('storefront_listings', ['id' => $listing->id]);
    }
}
