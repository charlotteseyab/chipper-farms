<?php

namespace Tests\Feature\Storefront;

use App\Models\Storefront\StorefrontListing;
use App\Models\Storefront\StorefrontQuote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

/**
 * Phase 2 HTTP behaviour. One authenticated actor per method (see the Phase 1
 * note on sanctum session bleed).
 */
class StorefrontPhase2RouteTest extends StorefrontTestCase
{
    use RefreshDatabase;

    public function test_published_listing_detail_renders(): void
    {
        $listing = $this->makePublishedListing(['slug' => 'shiny-pig']);

        $this->get(route('storefront.listing', 'shiny-pig'))
            ->assertOk()
            ->assertSee($listing->title);
    }

    public function test_draft_listing_detail_is_not_public(): void
    {
        $this->makeListing(['slug' => 'hidden-draft', 'status' => StorefrontListing::STATUS_DRAFT]);

        $this->get(route('storefront.listing', 'hidden-draft'))->assertNotFound();
    }

    public function test_detail_increments_view_count(): void
    {
        $listing = $this->makePublishedListing(['slug' => 'count-me', 'view_count' => 0]);

        $this->get(route('storefront.listing', 'count-me'))->assertOk();

        $this->assertSame(1, $listing->fresh()->view_count);
    }

    public function test_guest_can_submit_a_quote_request(): void
    {
        Notification::fake();

        $this->post(route('storefront.quote.store'), [
            'contact_name'  => 'Ama',
            'contact_phone' => '0240000000',
            'source'        => 'bulk',
            'items'         => [
                ['description' => '10 piglets', 'quantity' => '10', 'quantity_unit' => 'head'],
                ['description' => '', 'quantity' => ''], // blank row must be ignored
            ],
        ])->assertRedirect();

        $this->assertSame(1, StorefrontQuote::count());
        $this->assertSame(1, StorefrontQuote::first()->items()->count());
    }

    public function test_quote_request_requires_contact_method(): void
    {
        $this->post(route('storefront.quote.store'), [
            'contact_name' => 'NoContact',
            'items'        => [['description' => 'x', 'quantity' => '1']],
        ])->assertSessionHasErrors(['contact_email', 'contact_phone']);
    }

    public function test_favourite_toggle_requires_auth(): void
    {
        $listing = $this->makePublishedListing();

        $this->post(route('storefront.account.favourites.toggle', $listing))
            ->assertRedirect(route('login'));
    }

    public function test_customer_can_toggle_favourite(): void
    {
        $this->seedStorefrontPermissions();
        $listing = $this->makePublishedListing();

        $this->actingAs($this->makeUser('customer'))
            ->post(route('storefront.account.favourites.toggle', $listing))
            ->assertRedirect();

        $this->assertSame(1, \App\Models\Storefront\StorefrontFavourite::count());
    }

    public function test_salesperson_can_view_quotes_workspace(): void
    {
        $this->seedStorefrontPermissions();
        $this->seedStorefrontQuotePermissions();

        $this->actingAs($this->makeUser('salesperson'))
            ->get(route('storefront.admin.quotes.index'))
            ->assertOk();
    }

    public function test_customer_cannot_view_quotes_workspace(): void
    {
        $this->seedStorefrontPermissions();
        $this->seedStorefrontQuotePermissions();

        $this->actingAs($this->makeUser('customer'))
            ->get(route('storefront.admin.quotes.index'))
            ->assertForbidden();
    }
}
