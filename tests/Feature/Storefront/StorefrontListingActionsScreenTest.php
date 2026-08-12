<?php

namespace Tests\Feature\Storefront;

use App\Models\Storefront\StorefrontListing;
use Illuminate\Foundation\Testing\RefreshDatabase;

class StorefrontListingActionsScreenTest extends StorefrontTestCase
{
    use RefreshDatabase;

    public function test_listing_index_exposes_status_aware_safe_actions(): void
    {
        $this->withoutVite();
        $this->seedStorefrontPermissions();

        $draft = $this->makeListing([
            'title' => 'Draft feed listing',
            'kind' => StorefrontListing::KIND_FEED,
            'status' => StorefrontListing::STATUS_DRAFT,
        ]);
        $published = $this->makePublishedListing([
            'title' => 'Published feed listing',
            'kind' => StorefrontListing::KIND_FEED,
        ]);
        $archived = $this->makeListing([
            'title' => 'Archived feed listing',
            'kind' => StorefrontListing::KIND_FEED,
            'status' => StorefrontListing::STATUS_ARCHIVED,
        ]);

        $response = $this->actingAs($this->makeUser('admin'))
            ->get(route('storefront.admin.listings.index'));

        $response->assertOk()
            ->assertSee('Actions')
            ->assertSee($draft->title)
            ->assertSee($published->title)
            ->assertSee($archived->title)
            ->assertSee('View')
            ->assertSee('Edit')
            ->assertSee('Publish')
            ->assertSee('Unpublish')
            ->assertSee('Archive')
            ->assertSee('Restore unavailable')
            ->assertDontSee('Delete');
    }

    public function test_listing_actions_are_not_exposed_to_read_only_staff(): void
    {
        $this->withoutVite();
        $this->seedStorefrontPermissions();

        $listing = $this->makePublishedListing([
            'title' => 'Read only catalog item',
            'kind' => StorefrontListing::KIND_FEED,
        ]);

        $response = $this->actingAs($this->makeUser('accountant'))
            ->get(route('storefront.admin.listings.index'));

        $response->assertOk()
            ->assertSee('Actions')
            ->assertSee($listing->title)
            ->assertSee('View')
            ->assertDontSee('>Edit</a>', false)
            ->assertDontSee('>Publish</button>', false)
            ->assertDontSee('>Unpublish</button>', false)
            ->assertDontSee('>Archive</button>', false)
            ->assertDontSee('>Delete</button>', false);
    }
}
