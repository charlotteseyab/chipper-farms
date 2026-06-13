<?php

namespace Tests\Feature\Storefront;

use App\Models\PigManagement;
use App\Models\Setting;
use App\Models\Storefront\StorefrontListing;
use App\Storefront\Services\Exceptions\DuplicateListingException;
use App\Storefront\Services\PushToStorefrontService;
use App\Storefront\Support\PushToStorefrontData;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PushToStorefrontServiceTest extends StorefrontTestCase
{
    use RefreshDatabase;

    protected function service(): PushToStorefrontService
    {
        return $this->app->make(PushToStorefrontService::class);
    }

    public function test_it_creates_a_draft_listing_from_a_pig_without_mutating_the_pig(): void
    {
        $actor = $this->makeUser('admin');
        $pig   = $this->makePig();

        $data = PushToStorefrontData::fromArray([
            'kind'          => StorefrontListing::KIND_LIVE_PIG,
            'sale_mode'     => StorefrontListing::MODE_FIXED,
            'pricing_basis' => StorefrontListing::BASIS_LIVE,
            'title'         => 'Large White sow',
            'price_per_kg'  => '35.00',
            'quantity_available' => '1',
            'quantity_unit' => 'head',
        ]);

        $listing = $this->service()->execute($pig, $actor, $data);

        $this->assertSame(StorefrontListing::STATUS_DRAFT, $listing->status);
        $this->assertSame(PigManagement::class, $listing->source_type);
        $this->assertSame($pig->id, $listing->source_id);
        $this->assertSame('35.0000', $listing->price_per_kg);
        $this->assertSame($actor->id, $listing->created_by_user_id);
        $this->assertNotNull($listing->reference);
        $this->assertSame($this->org->id, $listing->organization_id);

        // Operational truth untouched: the pig is still alive (not soft-deleted).
        $this->assertNotNull(PigManagement::find($pig->id));
        $this->assertFalse($pig->fresh()->trashed());
    }

    public function test_it_defaults_price_from_system_settings_when_left_blank(): void
    {
        Setting::set('sales.pricing.live_weight_price', '42.00');
        $actor = $this->makeUser('admin');
        $pig   = $this->makePig();

        $data = PushToStorefrontData::fromArray([
            'kind'          => StorefrontListing::KIND_LIVE_PIG,
            'sale_mode'     => StorefrontListing::MODE_FIXED,
            'pricing_basis' => StorefrontListing::BASIS_LIVE,
            'title'         => 'Pig with default price',
            'quantity_available' => '1',
        ]);

        $listing = $this->service()->execute($pig, $actor, $data);

        $this->assertSame('42.0000', $listing->price_per_kg);
    }

    public function test_quote_mode_listing_carries_no_price(): void
    {
        $actor = $this->makeUser('admin');
        $pig   = $this->makePig();

        $data = PushToStorefrontData::fromArray([
            'kind'          => StorefrontListing::KIND_BREEDING_STOCK,
            'sale_mode'     => StorefrontListing::MODE_QUOTE,
            'pricing_basis' => StorefrontListing::BASIS_FIXED,
            'title'         => 'Breeding boar — POA',
            'quantity_available' => '1',
        ]);

        $listing = $this->service()->execute($pig, $actor, $data);

        $this->assertNull($listing->price_per_kg);
        $this->assertNull($listing->fixed_price);
        $this->assertTrue($listing->isQuoteOnly());
    }

    public function test_it_blocks_double_listing_the_same_pig(): void
    {
        $actor = $this->makeUser('admin');
        $pig   = $this->makePig();

        $data = PushToStorefrontData::fromArray([
            'kind' => StorefrontListing::KIND_LIVE_PIG,
            'sale_mode' => StorefrontListing::MODE_FIXED,
            'pricing_basis' => StorefrontListing::BASIS_FIXED,
            'title' => 'First listing',
            'fixed_price' => '500.00',
            'quantity_available' => '1',
        ]);

        $this->service()->execute($pig, $actor, $data);

        $this->expectException(DuplicateListingException::class);
        $this->service()->execute($pig, $actor, $data);
    }

    public function test_publish_now_publishes_when_source_available(): void
    {
        $actor = $this->makeUser('admin');
        $pig   = $this->makePig();

        $data = PushToStorefrontData::fromArray([
            'kind' => StorefrontListing::KIND_LIVE_PIG,
            'sale_mode' => StorefrontListing::MODE_FIXED,
            'pricing_basis' => StorefrontListing::BASIS_FIXED,
            'title' => 'Publish me',
            'fixed_price' => '600.00',
            'quantity_available' => '1',
            'publish_now' => true,
        ]);

        $listing = $this->service()->execute($pig, $actor, $data);

        $this->assertSame(StorefrontListing::STATUS_PUBLISHED, $listing->status);
        $this->assertNotNull($listing->published_at);
    }
}
