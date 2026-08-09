<?php

namespace Tests\Feature\Storefront;

use App\Models\Organization;
use App\Models\Storefront\StorefrontCategory;
use App\Models\Storefront\StorefrontDeliveryZone;
use App\Models\Storefront\StorefrontListing;
use App\Models\Storefront\StorefrontOrder;
use App\Models\Storefront\StorefrontSetting;
use App\Storefront\Services\CartService;
use App\Storefront\Services\CheckoutService;
use App\Storefront\Services\Exceptions\CartException;
use App\Storefront\Services\Exceptions\CheckoutException;
use App\Storefront\Support\CheckoutData;
use App\Support\Features\FeatureCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Phase 8 — do the settings actually control anything? (Blueprint §59
 * acceptance: "storefront settings control public category/payment/delivery
 * behaviour".)
 *
 * This is the file that matters. Phase 1 shipped thirteen `storefront.*`
 * feature flags and enforced none of them: a farm could switch Manure Sales off
 * and keep selling manure, switch Paystack off and keep taking card payments,
 * and switch the whole shop off and stay open. Every test here fails if that
 * regresses.
 */
class StorefrontSettingsEnforcementTest extends StorefrontTestCase
{
    use RefreshDatabase;

    /* ---------------------------------------------------------------- */
    /* Categories                                                       */
    /* ---------------------------------------------------------------- */

    public function test_a_switched_off_category_disappears_from_the_shop(): void
    {
        $this->enableStorefrontFeature(FeatureCatalog::STOREFRONT_MANURE_SALES);

        $pig = $this->makePublishedListing([
            'title' => 'Weaner boar', 'kind' => StorefrontListing::KIND_LIVE_PIG,
        ]);
        $manure = $this->makePublishedListing([
            'title' => 'Composted manure', 'kind' => StorefrontListing::KIND_MANURE,
        ]);

        // Both on sale while manure is switched on.
        $this->get(route('storefront.shop'))->assertOk()
            ->assertSee('Weaner boar')->assertSee('Composted manure');

        $this->disableStorefrontFeature(FeatureCatalog::STOREFRONT_MANURE_SALES);

        $this->get(route('storefront.shop'))->assertOk()
            ->assertSee('Weaner boar')->assertDontSee('Composted manure');

        $this->assertSame(StorefrontListing::STATUS_PUBLISHED, $manure->fresh()->status);
        $this->assertSame(StorefrontListing::STATUS_PUBLISHED, $pig->fresh()->status);
    }

    /**
     * The direct URL is the part that gets forgotten. Removing the links while
     * leaving the pages is how a "hidden" surface stops being hidden — the
     * first search result finds one.
     */
    public function test_a_switched_off_category_is_not_reachable_by_direct_url(): void
    {
        $this->enableStorefrontFeature(FeatureCatalog::STOREFRONT_MANURE_SALES);

        $manure = $this->makePublishedListing([
            'title' => 'Composted manure',
            'slug'  => 'composted-manure',
            'kind'  => StorefrontListing::KIND_MANURE,
        ]);

        $this->get(route('storefront.listing', 'composted-manure'))->assertOk();

        $this->disableStorefrontFeature(FeatureCatalog::STOREFRONT_MANURE_SALES);

        $this->get(route('storefront.listing', 'composted-manure'))->assertNotFound();
    }

    public function test_a_switched_off_category_is_not_offered_in_the_category_list(): void
    {
        StorefrontCategory::create([
            'organization_id' => $this->org->id,
            'code' => 'live-pigs', 'name' => 'Live Pigs', 'slug' => 'live-pigs',
            'kind' => StorefrontCategory::KIND_LIVE_PIG, 'is_active' => true,
        ]);
        StorefrontCategory::create([
            'organization_id' => $this->org->id,
            'code' => 'manure', 'name' => 'Manure & Compost', 'slug' => 'manure',
            'kind' => StorefrontCategory::KIND_MANURE, 'is_active' => true,
        ]);

        // Manure Sales defaults to off for a new farm.
        $this->get(route('storefront.home'))->assertOk()
            ->assertSee('Live Pigs')
            ->assertDontSee('Manure &amp; Compost', false);
    }

    public function test_a_switched_off_category_cannot_be_added_to_a_cart(): void
    {
        $manure = $this->makePublishedListing([
            'kind'          => StorefrontListing::KIND_MANURE,
            'pricing_basis' => StorefrontListing::BASIS_FIXED,
            'fixed_price'   => '40.00',
            'quantity_available' => '5',
        ]);

        $this->expectException(CartException::class);
        $this->expectExceptionMessage('no longer offered');

        $this->app->make(CartService::class)->addItem($this->makeUser('customer'), $manure, '1');
    }

    /* ---------------------------------------------------------------- */
    /* Quote requests                                                   */
    /* ---------------------------------------------------------------- */

    public function test_quote_requests_close_when_the_farm_does_not_take_them(): void
    {
        $this->get(route('storefront.quote.create'))->assertOk();

        $this->disableStorefrontFeature(FeatureCatalog::STOREFRONT_QUOTES);

        // The form AND the endpoint. Hiding the link and leaving the endpoint
        // is how a switched-off surface keeps accepting work.
        $this->get(route('storefront.quote.create'))->assertNotFound();
        $this->post(route('storefront.quote.store'), [
            'contact_name'  => 'A Buyer',
            'contact_phone' => '0244000000',
        ])->assertNotFound();
    }

    /* ---------------------------------------------------------------- */
    /* The open / closed sign                                           */
    /* ---------------------------------------------------------------- */

    public function test_closing_the_shop_closes_the_public_pages(): void
    {
        $this->makePublishedListing(['title' => 'Weaner boar']);

        $this->get(route('storefront.shop'))->assertOk();

        StorefrontSetting::create([
            'organization_id'  => $this->org->id,
            'public_shop_open' => false,
            'store_name'       => 'Chipper Farms Shop',
            'support_phone'    => '0300000000',
        ]);

        $response = $this->get(route('storefront.shop'));

        $response->assertStatus(503);
        $response->assertSee('Chipper Farms Shop');
        $response->assertSee('0300000000');
        $response->assertDontSee('Weaner boar');
    }

    /**
     * Money already in flight must still settle. A closed shop that also closed
     * the webhook would strand a payment the buyer has already made.
     */
    public function test_closing_the_shop_leaves_the_paystack_webhook_reachable(): void
    {
        StorefrontSetting::create([
            'organization_id'  => $this->org->id,
            'public_shop_open' => false,
        ]);

        // No signature, so the controller rejects it — but with its own answer,
        // not with the closed-shop page. What matters is that the request
        // reaches the controller at all.
        $response = $this->postJson(route('storefront.paystack.webhook'), ['event' => 'charge.success']);

        $this->assertNotSame(503, $response->status());
    }

    public function test_a_closed_shop_refuses_a_new_order(): void
    {
        $customer = $this->makeUser('customer');
        $listing  = $this->makePublishedListing(['fixed_price' => '500.00', 'quantity_available' => '2']);

        $this->app->make(CartService::class)->addItem($customer, $listing, '1');

        StorefrontSetting::create([
            'organization_id'  => $this->org->id,
            'public_shop_open' => false,
        ]);

        $this->expectException(CheckoutException::class);
        $this->expectExceptionMessage('not accepting orders');

        $this->app->make(CheckoutService::class)->checkout($customer, CheckoutData::fromArray([
            'payment_method' => 'cod', 'fulfilment_method' => 'pickup',
        ]));
    }

    /* ---------------------------------------------------------------- */
    /* Payment methods                                                  */
    /* ---------------------------------------------------------------- */

    public function test_a_switched_off_payment_method_is_refused_by_the_service(): void
    {
        $this->disableStorefrontFeature(FeatureCatalog::STOREFRONT_PAYSTACK);

        $customer = $this->makeUser('customer');
        $listing  = $this->makePublishedListing(['fixed_price' => '500.00', 'quantity_available' => '2']);
        $this->app->make(CartService::class)->addItem($customer, $listing, '1');

        $this->expectException(CheckoutException::class);
        $this->expectExceptionMessage('payment method is not available');

        $this->app->make(CheckoutService::class)->checkout($customer, CheckoutData::fromArray([
            'payment_method' => 'paystack', 'fulfilment_method' => 'pickup',
        ]));
    }

    /**
     * The HTTP path has to refuse it too — a hand-posted form is the realistic
     * way this is exercised, and a form request that accepts every enum value
     * makes the settings screen a decoration.
     */
    public function test_a_switched_off_payment_method_is_refused_over_http(): void
    {
        $this->disableStorefrontFeature(FeatureCatalog::STOREFRONT_COD);

        $customer = $this->makeUser('customer');
        $listing  = $this->makePublishedListing(['fixed_price' => '500.00', 'quantity_available' => '2']);
        $this->app->make(CartService::class)->addItem($customer, $listing, '1');

        $this->actingAs($customer)
            ->post(route('storefront.checkout.place'), [
                'payment_method' => 'cod', 'fulfilment_method' => 'pickup',
            ])
            ->assertSessionHasErrors('payment_method');

        $this->assertSame(0, StorefrontOrder::count());
    }

    public function test_an_enabled_payment_method_still_works(): void
    {
        $customer = $this->makeUser('customer');
        $listing  = $this->makePublishedListing(['fixed_price' => '500.00', 'quantity_available' => '2']);
        $this->app->make(CartService::class)->addItem($customer, $listing, '1');

        $order = $this->app->make(CheckoutService::class)->checkout($customer, CheckoutData::fromArray([
            'payment_method' => 'cod', 'fulfilment_method' => 'pickup',
        ]));

        $this->assertSame(StorefrontOrder::METHOD_COD, $order->payment_method);
    }

    /* ---------------------------------------------------------------- */
    /* Delivery                                                         */
    /* ---------------------------------------------------------------- */

    public function test_delivery_is_refused_when_the_farm_does_not_deliver(): void
    {
        // Delivery defaults to off.
        $customer = $this->makeUser('customer');
        $listing  = $this->makePublishedListing(['fixed_price' => '500.00', 'quantity_available' => '2']);
        $this->app->make(CartService::class)->addItem($customer, $listing, '1');

        $this->expectException(CheckoutException::class);
        $this->expectExceptionMessage('does not offer delivery');

        $this->app->make(CheckoutService::class)->checkout($customer, CheckoutData::fromArray([
            'payment_method'      => 'cod',
            'fulfilment_method'   => 'delivery',
            'ship_address_line_1' => '12 Farm Road',
            'ship_city'           => 'Accra',
        ]));
    }

    public function test_the_checkout_page_offers_only_what_the_farm_supports(): void
    {
        $this->disableStorefrontFeature(FeatureCatalog::STOREFRONT_PAYSTACK);

        $customer = $this->makeUser('customer');
        $listing  = $this->makePublishedListing(['fixed_price' => '500.00', 'quantity_available' => '2']);
        $this->app->make(CartService::class)->addItem($customer, $listing, '1');

        $response = $this->actingAs($customer)->get(route('storefront.checkout.show'))->assertOk();

        $response->assertSee('value="cod"', false);
        $response->assertDontSee('value="paystack"', false);
        // Delivery defaults off, so pickup is the only fulfilment offered.
        $response->assertSee('value="pickup"', false);
        $response->assertDontSee('value="delivery"', false);
    }

    /* ---------------------------------------------------------------- */
    /* Delivery zones and what they cost                                */
    /* ---------------------------------------------------------------- */

    public function test_the_zone_fee_prices_the_delivery(): void
    {
        $this->enableStorefrontFeature(FeatureCatalog::STOREFRONT_DELIVERY);

        $zone = StorefrontDeliveryZone::create([
            'organization_id' => $this->org->id,
            'name' => 'Kumasi metro', 'fee' => '85.50',
        ]);

        $customer = $this->makeUser('customer');
        $listing  = $this->makePublishedListing([
            'fixed_price' => '500.00', 'quantity_available' => '2',
            'delivery_allowed' => true, 'delivery_fee' => '20.00',
        ]);
        $this->app->make(CartService::class)->addItem($customer, $listing, '1');

        $order = $this->app->make(CheckoutService::class)->checkout($customer, CheckoutData::fromArray([
            'payment_method'      => 'cod',
            'fulfilment_method'   => 'delivery',
            'delivery_zone_id'    => $zone->id,
            'ship_address_line_1' => '12 Farm Road',
            'ship_city'           => 'Kumasi',
        ]));

        // The zone fee, NOT the zone fee plus the listing's own 20.00. One
        // journey is charged once.
        $this->assertSame('85.50', $order->delivery_fee);
        $this->assertSame('585.50', $order->total);
        $this->assertSame($zone->id, $order->delivery_zone_id);
        $this->assertSame('Kumasi metro', $order->delivery_zone_name);
    }

    public function test_per_listing_delivery_fees_still_apply_when_no_zones_are_configured(): void
    {
        $this->enableStorefrontFeature(FeatureCatalog::STOREFRONT_DELIVERY);

        $customer = $this->makeUser('customer');
        $listing  = $this->makePublishedListing([
            'fixed_price' => '500.00', 'quantity_available' => '2',
            'delivery_allowed' => true, 'delivery_fee' => '20.00',
        ]);
        $this->app->make(CartService::class)->addItem($customer, $listing, '1');

        $order = $this->app->make(CheckoutService::class)->checkout($customer, CheckoutData::fromArray([
            'payment_method'      => 'cod',
            'fulfilment_method'   => 'delivery',
            'ship_address_line_1' => '12 Farm Road',
            'ship_city'           => 'Accra',
        ]));

        $this->assertSame('20.00', $order->delivery_fee);
        $this->assertNull($order->delivery_zone_id);
    }

    public function test_a_delivery_order_must_name_a_zone_once_zones_exist(): void
    {
        $this->enableStorefrontFeature(FeatureCatalog::STOREFRONT_DELIVERY);

        StorefrontDeliveryZone::create([
            'organization_id' => $this->org->id, 'name' => 'Accra', 'fee' => '40.00',
        ]);

        $customer = $this->makeUser('customer');
        $listing  = $this->makePublishedListing(['fixed_price' => '500.00', 'quantity_available' => '2']);
        $this->app->make(CartService::class)->addItem($customer, $listing, '1');

        $this->expectException(CheckoutException::class);
        $this->expectExceptionMessage('Choose a delivery area');

        $this->app->make(CheckoutService::class)->checkout($customer, CheckoutData::fromArray([
            'payment_method'      => 'cod',
            'fulfilment_method'   => 'delivery',
            'ship_address_line_1' => '12 Farm Road',
            'ship_city'           => 'Accra',
        ]));
    }

    /**
     * A posted zone id is buyer input. Another farm's zone must not price this
     * farm's delivery, and an inactive one must stop being chargeable.
     */
    public function test_another_organisations_zone_cannot_price_this_farms_delivery(): void
    {
        $this->enableStorefrontFeature(FeatureCatalog::STOREFRONT_DELIVERY);

        StorefrontDeliveryZone::create([
            'organization_id' => $this->org->id, 'name' => 'Accra', 'fee' => '40.00',
        ]);

        $other = Organization::create(['slug' => 'other-farm', 'name' => 'Other Farm']);
        $foreign = StorefrontDeliveryZone::create([
            'organization_id' => $other->id, 'name' => 'Free ride', 'fee' => '0.00',
        ]);

        $customer = $this->makeUser('customer');
        $listing  = $this->makePublishedListing(['fixed_price' => '500.00', 'quantity_available' => '2']);
        $this->app->make(CartService::class)->addItem($customer, $listing, '1');

        $this->expectException(CheckoutException::class);
        $this->expectExceptionMessage('no longer available');

        $this->app->make(CheckoutService::class)->checkout($customer, CheckoutData::fromArray([
            'payment_method'      => 'cod',
            'fulfilment_method'   => 'delivery',
            'delivery_zone_id'    => $foreign->id,
            'ship_address_line_1' => '12 Farm Road',
            'ship_city'           => 'Accra',
        ]));
    }

    public function test_an_inactive_zone_cannot_be_chosen(): void
    {
        $this->enableStorefrontFeature(FeatureCatalog::STOREFRONT_DELIVERY);

        $active = StorefrontDeliveryZone::create([
            'organization_id' => $this->org->id, 'name' => 'Accra', 'fee' => '40.00',
        ]);
        $retired = StorefrontDeliveryZone::create([
            'organization_id' => $this->org->id, 'name' => 'Old route', 'fee' => '5.00',
            'is_active' => false,
        ]);

        $customer = $this->makeUser('customer');
        $listing  = $this->makePublishedListing(['fixed_price' => '500.00', 'quantity_available' => '2']);
        $this->app->make(CartService::class)->addItem($customer, $listing, '1');

        $this->expectException(CheckoutException::class);

        $this->app->make(CheckoutService::class)->checkout($customer, CheckoutData::fromArray([
            'payment_method'      => 'cod',
            'fulfilment_method'   => 'delivery',
            'delivery_zone_id'    => $retired->id,
            'ship_address_line_1' => '12 Farm Road',
            'ship_city'           => 'Accra',
        ]));
    }
}
