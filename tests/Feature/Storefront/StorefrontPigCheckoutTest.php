<?php

namespace Tests\Feature\Storefront;

use App\Models\PigManagement;
use App\Models\PigWeight;
use App\Models\Setting;
use App\Models\Storefront\StorefrontInventoryHold;
use App\Models\Storefront\StorefrontListing;
use App\Models\Storefront\StorefrontListingPig;
use App\Models\Storefront\StorefrontOrderItem;
use App\Models\User;
use App\Storefront\Services\CheckoutService;
use App\Storefront\Services\Exceptions\InventoryConflictException;
use App\Storefront\Services\StorefrontPigPoolService;
use App\Storefront\Services\StorefrontPigPricingService as Pricing;
use App\Storefront\Services\StorefrontPurchaseLineService;
use App\Storefront\Support\CartOwner;
use App\Storefront\Support\CheckoutData;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Checking out a pig, and reserving the right thing while you do.
 *
 * A hold is the promise the shop makes between "place order" and "pay". What it
 * reserves depends on what was bought:
 *
 *   a bag of feed  → N of a thing
 *   a named animal → that animal
 *   50 kg of meat  → fifty kilograms, animals to be decided
 *
 * They are counted in different units and must never be added together. The
 * order line also has to carry the whole calculation with it: a cart is
 * converted and eventually cleared, and an invoice that can only explain its
 * prices by reading a basket that no longer exists cannot explain them.
 */
class StorefrontPigCheckoutTest extends StorefrontTestCase
{
    use RefreshDatabase;

    protected StorefrontListing $listing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedStorefrontPermissions();

        Setting::set('sales.pricing.live_weight_price', 30);
        Setting::set('sales.pricing.carcass_weight_price', 50);
    }

    protected function lines(): StorefrontPurchaseLineService
    {
        return $this->app->make(StorefrontPurchaseLineService::class);
    }

    /** @param array<int, float> $weights */
    protected function poolListing(array $weights): StorefrontListing
    {
        $listing = $this->makePublishedListing([
            'kind'               => StorefrontListing::KIND_LIVE_PIG,
            'source_category'    => Pricing::CATEGORY_STANDARD,
            'title'              => 'Market-ready pigs',
            'pricing_basis'      => StorefrontListing::BASIS_FIXED,
            'fixed_price'        => '0.00',
            'quantity_available' => '0',
            'pickup_allowed'     => true,
        ]);

        $ids = [];

        foreach ($weights as $i => $kg) {
            $pig = PigManagement::create([
                'organization_id' => $this->org->id,
                'ear_tag_number'  => 'CO-' . $i . '-' . strtoupper(uniqid()),
                'breed'           => 'Landrace',
                'gender'          => 'female',
                'stock_type'      => 'standard',
                'date_of_birth'   => now()->subMonths(8)->toDateString(),
                'code'            => 'PIG-' . uniqid(),
            ]);

            PigWeight::create([
                'pig_id'     => $pig->id,
                'weight'     => $kg,
                'weigh_date' => now()->toDateString(),
            ]);

            $ids[] = $pig->id;
        }

        $this->app->make(StorefrontPigPoolService::class)
            ->attachPigs($listing, $ids, $this->makeUser('admin'));

        return $this->listing = $listing->fresh();
    }

    /** @return array<int, int> */
    protected function poolRowIds(): array
    {
        return StorefrontListingPig::where('storefront_listing_id', $this->listing->id)
            ->orderBy('id')->pluck('id')->all();
    }

    protected function checkout(User $buyer): \App\Models\Storefront\StorefrontOrder
    {
        return $this->app->make(CheckoutService::class)->checkout(
            $buyer,
            CheckoutData::fromArray(['payment_method' => 'cod', 'fulfilment_method' => 'pickup']),
        );
    }

    /* ================================================================== */
    /* The order line remembers everything                                */
    /* ================================================================== */

    public function test_a_live_order_line_carries_the_whole_calculation(): void
    {
        $this->poolListing([100]);
        $buyer = $this->makeUser('customer');

        $this->lines()->addLiveLines(CartOwner::from($buyer), $this->listing, $this->poolRowIds());

        $order = $this->checkout($buyer);
        $item  = StorefrontOrderItem::where('order_id', $order->id)->firstOrFail();

        $this->assertSame(Pricing::PURCHASE_LIVE, $item->purchase_type);
        $this->assertSame('30.0000', (string) $item->price_per_kg_snapshot);
        $this->assertSame('100.0000', (string) $item->line_weight_kg);
        $this->assertSame('3000.00', (string) $item->line_total);
        $this->assertSame($this->poolRowIds(), array_map('intval', $item->selected_listing_pig_ids));
        $this->assertSame('live', $item->calculation_snapshot['basis']);
    }

    public function test_a_carcass_order_line_carries_its_kilograms(): void
    {
        $this->poolListing([100, 120]);
        $buyer = $this->makeUser('customer');

        $this->lines()->addCarcassLine(CartOwner::from($buyer), $this->listing, '60');

        $order = $this->checkout($buyer);
        $item  = StorefrontOrderItem::where('order_id', $order->id)->firstOrFail();

        $this->assertSame(Pricing::PURCHASE_CARCASS, $item->purchase_type);
        $this->assertSame('60.0000', (string) $item->requested_kg);
        $this->assertSame('50.0000', (string) $item->price_per_kg_snapshot);
        $this->assertSame('3000.00', (string) $item->line_total);
    }

    /**
     * The listing carries ONE price for a pool of differently-sized animals.
     * Re-pricing a named animal from it at checkout would charge the wrong
     * amount for the specific pig the buyer chose.
     */
    public function test_checkout_does_not_reprice_a_named_animal_from_the_listing(): void
    {
        $this->poolListing([80, 120]);
        $buyer = $this->makeUser('customer');

        // The lighter animal only.
        $this->lines()->addLiveLines(CartOwner::from($buyer), $this->listing, [$this->poolRowIds()[0]]);

        // The listing's single price column says something else entirely.
        $this->listing->forceFill(['fixed_price' => '9999.00'])->save();

        $order = $this->checkout($buyer);
        $item  = StorefrontOrderItem::where('order_id', $order->id)->firstOrFail();

        $this->assertSame(
            '2400.00',
            (string) $item->line_total,
            'The buyer must be charged for the animal they chose, at the rate they were shown.',
        );
    }

    /* ================================================================== */
    /* Holds reserve the right thing                                      */
    /* ================================================================== */

    public function test_choosing_animals_places_a_hold_naming_them(): void
    {
        $this->poolListing([100, 120]);
        $buyer = $this->makeUser('customer');
        $rows  = $this->poolRowIds();

        $this->lines()->addLiveLines(CartOwner::from($buyer), $this->listing, $rows);

        $order = $this->checkout($buyer);
        $hold  = StorefrontInventoryHold::where('order_id', $order->id)->firstOrFail();

        $this->assertSame(StorefrontInventoryHold::TYPE_EXACT_PIGS, $hold->hold_type);
        // Compared numerically: `quantity` has no decimal cast, so SQLite hands
        // back '2' where MariaDB hands back '2.0000'. The number is the claim,
        // not its formatting.
        $this->assertSame(0, bccomp((string) $hold->quantity, '2', 4), 'Two animals is two head.');
        $this->assertEqualsCanonicalizing($rows, array_map('intval', $hold->selected_listing_pig_ids));
    }

    /**
     * The distinction the whole hold-type change exists for.
     */
    public function test_a_carcass_hold_reserves_kilograms_and_no_head(): void
    {
        $this->poolListing([100, 120]);
        $buyer = $this->makeUser('customer');

        $this->lines()->addCarcassLine(CartOwner::from($buyer), $this->listing, '150');

        $order = $this->checkout($buyer);
        $hold  = StorefrontInventoryHold::where('order_id', $order->id)->firstOrFail();

        $this->assertSame(StorefrontInventoryHold::TYPE_CARCASS_KG, $hold->hold_type);
        $this->assertSame('150.0000', (string) $hold->requested_kg);
        $this->assertSame(
            0,
            bccomp((string) $hold->quantity, '0', 4),
            'Which animals cover a carcass request has not been decided, so no head may be reserved.',
        );
    }

    public function test_a_customer_selected_carcass_animal_is_reserved_by_identity(): void
    {
        $this->poolListing([100, 120]);
        $buyer = $this->makeUser('customer');
        $row   = $this->poolRowIds()[0];

        $this->lines()->addSelectedCarcassLines(CartOwner::from($buyer), $this->listing, [$row]);

        $order = $this->checkout($buyer);
        $hold  = StorefrontInventoryHold::where('order_id', $order->id)->firstOrFail();
        $item  = StorefrontOrderItem::where('order_id', $order->id)->firstOrFail();

        $this->assertSame(StorefrontInventoryHold::TYPE_EXACT_PIGS, $hold->hold_type);
        $this->assertSame([$row], array_map('intval', $hold->selected_listing_pig_ids));
        $this->assertSame(Pricing::PURCHASE_CARCASS, $item->purchase_type);
        $this->assertSame('100.0000', (string) $item->requested_kg);
        $this->assertSame('5000.00', (string) $item->line_total);
    }

    public function test_a_failed_selected_carcass_payment_releases_the_animal_and_hold(): void
    {
        $this->poolListing([100]);
        $buyer = $this->makeUser('customer');
        $rowId = $this->poolRowIds()[0];

        $this->lines()->addSelectedCarcassLines(CartOwner::from($buyer), $this->listing, [$rowId]);
        $order = $this->app->make(CheckoutService::class)->checkout(
            $buyer,
            CheckoutData::fromArray(['payment_method' => 'paystack', 'fulfilment_method' => 'pickup']),
        );

        $this->app->make(\App\Storefront\Services\OrderService::class)
            ->markFailed($order, 'Gateway initialization failed.', $buyer);

        $this->assertSame(
            StorefrontListingPig::STATUS_AVAILABLE,
            StorefrontListingPig::findOrFail($rowId)->status,
        );
        $this->assertSame(
            StorefrontInventoryHold::STATUS_RELEASED,
            StorefrontInventoryHold::where('order_id', $order->id)->firstOrFail()->status,
        );
    }

    public function test_an_exact_hold_does_not_hide_a_different_available_pool_animal(): void
    {
        $this->poolListing([100, 120]);
        [$firstRow, $secondRow] = $this->poolRowIds();
        $firstBuyer  = $this->makeUser('customer');
        $secondBuyer = $this->makeUser('customer');

        $this->lines()->addSelectedCarcassLines(CartOwner::from($firstBuyer), $this->listing, [$firstRow]);
        $this->checkout($firstBuyer);

        $this->assertSame('1.0000', (string) $this->listing->fresh()->quantity_available);

        $this->lines()->addSelectedCarcassLines(CartOwner::from($secondBuyer), $this->listing->fresh(), [$secondRow]);
        $secondOrder = $this->checkout($secondBuyer);

        $this->assertDatabaseHas('storefront_inventory_holds', [
            'order_id'  => $secondOrder->id,
            'hold_type' => StorefrontInventoryHold::TYPE_EXACT_PIGS,
            'status'    => StorefrontInventoryHold::STATUS_ACTIVE,
        ]);
    }

    /**
     * A buyer wanting two named animals AND some meat from the same listing
     * gets two holds. Under the old key the second would have overwritten the
     * first, silently losing a reservation.
     */
    public function test_both_kinds_of_hold_can_exist_for_one_listing(): void
    {
        $this->poolListing([100, 120, 90]);
        $buyer = $this->makeUser('customer');
        $rows  = $this->poolRowIds();

        $this->lines()->addLiveLines(CartOwner::from($buyer), $this->listing, [$rows[0]]);
        $this->lines()->addCarcassLine(CartOwner::from($buyer), $this->listing, '50');

        $order = $this->checkout($buyer);
        $holds = StorefrontInventoryHold::where('order_id', $order->id)->get();

        $this->assertCount(2, $holds);
        $this->assertEqualsCanonicalizing(
            [StorefrontInventoryHold::TYPE_EXACT_PIGS, StorefrontInventoryHold::TYPE_CARCASS_KG],
            $holds->pluck('hold_type')->all(),
        );
    }

    /**
     * The farm cannot promise meat it does not have, even across two buyers.
     */
    public function test_a_second_buyer_cannot_reserve_kilograms_already_held(): void
    {
        $this->poolListing([100]);          // 100 kg of capacity
        $first  = $this->makeUser('customer');
        $second = $this->makeUser('customer');

        $this->lines()->addCarcassLine(CartOwner::from($first), $this->listing, '90');
        $this->checkout($first);

        // 100 − 90 = 10 kg left, so this basket cannot be filled at all...
        $this->expectExceptionMessage('Only 10 kg is available');

        $this->lines()->addCarcassLine(CartOwner::from($second), $this->listing->fresh(), '60');
    }

    /**
     * ...and if a basket was filled before the first buyer checked out, the
     * hold is the authority that refuses it.
     */
    public function test_the_hold_refuses_what_the_basket_let_through(): void
    {
        $this->poolListing([100]);
        $first  = $this->makeUser('customer');
        $second = $this->makeUser('customer');

        // Both baskets are filled while the whole 100 kg is still on offer.
        $this->lines()->addCarcassLine(CartOwner::from($first), $this->listing, '90');
        $this->lines()->addCarcassLine(CartOwner::from($second), $this->listing->fresh(), '90');

        $this->checkout($first);

        $this->expectException(InventoryConflictException::class);

        $this->checkout($second);
    }

    public function test_an_ordinary_listing_still_places_a_plain_quantity_hold(): void
    {
        $feed = $this->makePublishedListing([
            'kind'               => StorefrontListing::KIND_FEED,
            'source_category'    => Pricing::CATEGORY_MANUAL,
            'title'              => 'Feed bags',
            'pricing_basis'      => StorefrontListing::BASIS_FIXED,
            'fixed_price'        => '85.00',
            'quantity_available' => '40',
            'pickup_allowed'     => true,
        ]);

        $buyer = $this->makeUser('customer');
        $this->app->make(\App\Storefront\Services\CartService::class)
            ->addItem(CartOwner::from($buyer), $feed, '3');

        $order = $this->checkout($buyer);
        $hold  = StorefrontInventoryHold::where('order_id', $order->id)->firstOrFail();

        $this->assertSame(StorefrontInventoryHold::TYPE_QUANTITY, $hold->hold_type);
        $this->assertSame(0, bccomp((string) $hold->quantity, '3', 4));
        $this->assertNull($hold->requested_kg);
    }
}
