<?php

namespace Tests\Feature\Storefront;

use App\Models\Storefront\StorefrontListing;
use App\Models\Storefront\StorefrontQuote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Storefront\StorefrontBuyer;
use App\Models\Storefront\StorefrontOrder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use App\Storefront\Payments\PaystackClient;
use Tests\Feature\Storefront\Support\FakePaystackClient;

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

    public function test_delivery_quote_requires_a_city_and_region(): void
    {
        $this->post(route('storefront.quote.store'), [
            'contact_name' => 'Delivery Customer',
            'contact_phone' => '0240000000',
            'fulfilment_preference' => 'delivery',
            'items' => [['kind' => 'live_pig', 'description' => 'Two growers', 'quantity' => '2', 'quantity_unit' => 'head']],
        ])->assertSessionHasErrors(['delivery_city', 'delivery_region']);
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

    public function test_quote_workspace_shows_lifecycle_actions_and_keeps_accepted_quotes_open(): void
    {
        $this->seedStorefrontPermissions();
        $this->seedStorefrontQuotePermissions();

        $requested = $this->makeQuote('requested', 'REQ-TEST');
        $quoted = $this->makeQuote('quoted', 'QUO-TEST', ['quoted_total' => '1500.00']);
        $accepted = $this->makeQuote('accepted', 'ACC-TEST', ['quoted_total' => '2000.00']);

        $this->actingAs($this->makeUser('salesperson'))
            ->get(route('storefront.admin.quotes.index'))
            ->assertOk()
            ->assertSee($requested->reference)
            ->assertSee($quoted->reference)
            ->assertSee($accepted->reference)
            ->assertSee(route('storefront.admin.quotes.review', $requested), false)
            ->assertSee(route('storefront.admin.quotes.quote', $requested), false)
            ->assertSee('Awaiting buyer')
            ->assertSee(route('storefront.admin.quotes.convert', $accepted), false)
            ->assertSee('Decline');
    }

    public function test_customer_cannot_view_quotes_workspace(): void
    {
        $this->seedStorefrontPermissions();
        $this->seedStorefrontQuotePermissions();

        $this->actingAs($this->makeUser('customer'))
            ->get(route('storefront.admin.quotes.index'))
            ->assertForbidden();
    }

    public function test_buyer_can_monitor_only_quotes_requested_with_their_email(): void
    {
        $buyer = StorefrontBuyer::create([
            'organization_id' => $this->org->id,
            'name' => 'Kofi Buyer',
            'email' => 'kofi@example.com',
            'password' => Hash::make('password123'),
        ]);
        $mine = $this->makeQuote(StorefrontQuote::STATUS_QUOTED, 'SQ-MINE', [
            'contact_email' => 'KOFI@example.com',
            'quoted_total' => '1250.00',
        ]);
        $other = $this->makeQuote(StorefrontQuote::STATUS_REQUESTED, 'SQ-OTHER', [
            'contact_email' => 'other@example.com',
        ]);

        $this->withSession(['storefront_buyer_id' => $buyer->id])
            ->get(route('storefront.buyer.quotes.index'))
            ->assertOk()
            ->assertSee($mine->reference)
            ->assertDontSee($other->reference);

        $this->withSession(['storefront_buyer_id' => $buyer->id])
            ->get(route('storefront.buyer.quotes.show', $mine))
            ->assertOk()
            ->assertSee('GHS 1,250.00');

        $this->withSession(['storefront_buyer_id' => $buyer->id])
            ->get(route('storefront.buyer.quotes.show', $other))
            ->assertNotFound();
    }

    public function test_buyer_accepting_price_creates_one_attached_order(): void
    {
        Notification::fake();
        $buyer = StorefrontBuyer::create([
            'organization_id' => $this->org->id,
            'name' => 'Kofi Buyer',
            'email' => 'kofi@example.com',
            'password' => Hash::make('password123'),
        ]);
        $quote = $this->makeQuote(StorefrontQuote::STATUS_QUOTED, 'SQ-ACCEPT', [
            'contact_email' => 'kofi@example.com',
            'quoted_total' => '1250.00',
            'valid_until' => now()->addWeek(),
        ]);

        $response = $this->withSession(['storefront_buyer_id' => $buyer->id])
            ->post(route('storefront.buyer.quotes.accept', $quote), ['payment_method' => 'cod']);

        $order = StorefrontOrder::sole();
        $response->assertRedirect(route('storefront.buyer.orders.show', $order));
        $this->assertSame($buyer->id, $order->storefront_buyer_id);
        $this->assertSame(StorefrontOrder::STATUS_PENDING_CONFIRMATION, $order->status);
        $this->assertSame(StorefrontQuote::STATUS_CONVERTED, $quote->fresh()->status);

        $this->withSession(['storefront_buyer_id' => $buyer->id])
            ->post(route('storefront.buyer.quotes.accept', $quote), ['payment_method' => 'cod'])
            ->assertRedirect(route('storefront.buyer.orders.show', $order));
        $this->assertSame(1, StorefrontOrder::count());
    }

    public function test_buyer_can_accept_quote_and_continue_to_paystack(): void
    {
        Notification::fake();
        $paystack = new FakePaystackClient();
        $this->app->instance(PaystackClient::class, $paystack);
        $buyer = StorefrontBuyer::create([
            'organization_id' => $this->org->id,
            'name' => 'Ama Buyer',
            'email' => 'ama@example.com',
            'password' => Hash::make('password123'),
        ]);
        $quote = $this->makeQuote(StorefrontQuote::STATUS_QUOTED, 'SQ-PAYSTACK', [
            'contact_email' => $buyer->email,
            'quoted_total' => '975.50',
            'valid_until' => now()->addWeek(),
        ]);

        $response = $this->withSession(['storefront_buyer_id' => $buyer->id])
            ->post(route('storefront.buyer.quotes.accept', $quote), ['payment_method' => 'paystack']);

        $order = StorefrontOrder::sole();
        $response->assertRedirectContains('https://checkout.paystack.test/');
        $this->assertSame(StorefrontOrder::METHOD_PAYSTACK, $order->payment_method);
        $this->assertSame(StorefrontOrder::PAY_PAYSTACK_PEND, $order->payment_status);
        $this->assertSame(StorefrontOrder::STATUS_PENDING_PAYMENT, $order->status);
        $this->assertSame(97550, $paystack->initialised[0]['amountMinor']);

        $this->withSession(['storefront_buyer_id' => $buyer->id])
            ->post(route('storefront.buyer.orders.pay', $order))
            ->assertRedirectContains('https://checkout.paystack.test/');
        $this->assertCount(2, $paystack->initialised);
        $this->assertSame(1, StorefrontOrder::count());
    }

    protected function makeQuote(string $status, string $reference, array $overrides = []): StorefrontQuote
    {
        return StorefrontQuote::create(array_merge([
            'organization_id'          => $this->org->id,
            'reference'                => $reference,
            'contact_name'             => 'Quote Customer',
            'contact_phone'            => '0240000000',
            'source'                   => 'bulk',
            'fulfilment_preference'    => 'pickup',
            'status'                   => $status,
            'currency_code'            => 'GHS',
        ], $overrides));
    }
}
