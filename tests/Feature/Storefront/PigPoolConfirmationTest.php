<?php

namespace Tests\Feature\Storefront;

use App\Models\PigManagement;
use App\Models\PigSale;
use App\Models\Storefront\StorefrontListing;
use App\Models\Storefront\StorefrontOrder;
use App\Models\Storefront\StorefrontOrderItem;
use App\Storefront\Services\Exceptions\InvalidOrderTransitionException;
use App\Storefront\Services\OrderService;
use App\Storefront\Services\StorefrontPigAllocationService;
use App\Storefront\Services\StorefrontPigPoolService;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Confirming an order that draws pigs from a pool.
 *
 * Confirmation is the moment stock moves and money is owed, so it is where the
 * "exact animals chosen" rule has to bite. Deferring instead — letting the order
 * confirm and sorting the animals out later — would promise a customer stock the
 * farm has not committed.
 *
 * The other half is that the sale must happen exactly once. Approval sells the
 * animals through `RecordPigSaleService`; the storefront finance subscriber must
 * then recognise the line as handled rather than trying again or reporting it as
 * a deferred problem.
 */
class PigPoolConfirmationTest extends StorefrontTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedStorefrontPermissions();
        $this->seedStorefrontOrderPermissions();
    }

    protected function pool(): StorefrontPigPoolService
    {
        return $this->app->make(StorefrontPigPoolService::class);
    }

    protected function allocations(): StorefrontPigAllocationService
    {
        return $this->app->make(StorefrontPigAllocationService::class);
    }

    protected function orders(): OrderService
    {
        return $this->app->make(OrderService::class);
    }

    /** @return list<PigManagement> */
    protected function makeStandardPigs(int $count): array
    {
        $pigs = [];

        for ($i = 0; $i < $count; $i++) {
            $pigs[] = PigManagement::create([
                'organization_id' => $this->org->id,
                'ear_tag_number'  => 'CONF-' . strtoupper(uniqid()),
                'breed'           => 'Large White',
                'gender'          => 'female',
                'stock_type'      => 'standard',
                'date_of_birth'   => now()->subMonths(9)->toDateString(),
                'code'            => 'PIG-' . uniqid(),
            ]);
        }

        return $pigs;
    }

    /**
     * @return array{0: StorefrontOrder, 1: StorefrontOrderItem, 2: StorefrontListing, 3: list<PigManagement>}
     */
    protected function pooledOrder(int $poolSize, int $quantity): array
    {
        $listing = $this->makePublishedListing([
            'kind'               => StorefrontListing::KIND_LIVE_PIG,
            'title'              => 'Market-ready live pigs',
            'pricing_basis'      => StorefrontListing::BASIS_FIXED,
            'fixed_price'        => '900.00',
            'quantity_available' => '0',
            'quantity_unit'      => 'head',
        ]);

        $pigs  = $this->makeStandardPigs($poolSize);
        $staff = $this->makeUser('admin');

        $this->pool()->attachPigs($listing, array_column($pigs, 'id'), $staff);

        $order = StorefrontOrder::create([
            'organization_id'   => $this->org->id,
            'reference'         => 'SO-CONF-' . strtoupper(uniqid()),
            'status'            => StorefrontOrder::STATUS_PENDING_CONFIRMATION,
            'payment_status'    => StorefrontOrder::PAY_COD,
            'payment_method'    => StorefrontOrder::METHOD_COD,
            'fulfilment_method' => StorefrontOrder::FULFIL_PICKUP,
            'subtotal'          => (900 * $quantity) . '.00',
            'delivery_fee'      => '0.00',
            'total'             => (900 * $quantity) . '.00',
            'currency_code'     => 'GHS',
            'placed_at'         => now(),
        ]);

        $item = StorefrontOrderItem::create([
            'organization_id' => $this->org->id,
            'order_id'        => $order->id,
            'listing_id'      => $listing->id,
            'kind'            => StorefrontListing::KIND_LIVE_PIG,
            'description'     => $listing->title,
            'quantity'        => (string) $quantity,
            'quantity_unit'   => 'head',
            'price_basis'     => 'fixed',
            'unit_price'      => '900.00',
            'line_total'      => (900 * $quantity) . '.00',
        ]);

        return [$order, $item, $listing, $pigs];
    }

    protected function orderForExistingListing(StorefrontListing $listing, int $quantity, string $suffix = ''): array
    {
        $order = StorefrontOrder::create([
            'organization_id'   => $this->org->id,
            'reference'         => 'SO-POOL-NEXT-' . strtoupper(uniqid()) . $suffix,
            'status'            => StorefrontOrder::STATUS_PENDING_CONFIRMATION,
            'payment_status'    => StorefrontOrder::PAY_COD,
            'payment_method'    => StorefrontOrder::METHOD_COD,
            'fulfilment_method' => StorefrontOrder::FULFIL_PICKUP,
            'subtotal'          => (900 * $quantity) . '.00',
            'delivery_fee'      => '0.00',
            'total'             => (900 * $quantity) . '.00',
            'currency_code'     => 'GHS',
            'placed_at'         => now(),
        ]);

        $item = StorefrontOrderItem::create([
            'organization_id' => $this->org->id,
            'order_id'        => $order->id,
            'listing_id'      => $listing->id,
            'kind'            => StorefrontListing::KIND_LIVE_PIG,
            'description'     => $listing->title,
            'quantity'        => (string) $quantity,
            'quantity_unit'   => 'head',
            'price_basis'     => 'fixed',
            'unit_price'      => '900.00',
            'line_total'      => (900 * $quantity) . '.00',
        ]);

        return [$order, $item];
    }

    /* ================================================================== */

    /**
     * The rule that protects the customer.
     */
    public function test_a_pooled_order_cannot_be_confirmed_before_the_pigs_are_chosen(): void
    {
        [$order] = $this->pooledOrder(poolSize: 5, quantity: 3);

        $staff = $this->makeUser('admin');

        $this->expectException(InvalidOrderTransitionException::class);
        $this->expectExceptionMessage('Select the exact pigs');

        $this->orders()->confirm($order, $staff);
    }

    public function test_the_refusal_says_how_many_are_still_needed(): void
    {
        [$order, $item] = $this->pooledOrder(poolSize: 5, quantity: 3);

        $staff = $this->makeUser('admin');

        // Nothing chosen yet — the message must be actionable, not generic.
        try {
            $this->orders()->confirm($order, $staff);
            $this->fail('Confirmation should have been refused.');
        } catch (InvalidOrderTransitionException $e) {
            $this->assertStringContainsString('0 of 3 chosen', $e->getMessage());
        }
    }

    public function test_confirming_with_the_pigs_chosen_sells_exactly_those_animals(): void
    {
        [$order, $item, $listing, $pigs] = $this->pooledOrder(poolSize: 5, quantity: 3);

        $staff  = $this->makeUser('admin');
        $chosen = [$pigs[0]->id, $pigs[1]->id, $pigs[2]->id];

        $this->allocations()->selectForOrderItem($item, $chosen, $staff);

        $confirmed = $this->orders()->confirm($order, $staff);

        $this->assertSame(StorefrontOrder::STATUS_CONFIRMED, $confirmed->status);

        // /sales is the source of truth.
        $this->assertSame(3, PigSale::whereIn('pig_id', $chosen)->count());

        foreach ($chosen as $pigId) {
            $this->assertSoftDeleted('pigs_management', ['id' => $pigId]);
        }

        // The animals nobody chose are still on the farm.
        $this->assertNotNull(PigManagement::find($pigs[3]->id));
        $this->assertNotNull(PigManagement::find($pigs[4]->id));
    }

    public function test_partial_pooled_confirmation_keeps_remaining_pigs_public_and_orderable(): void
    {
        [$order, $item, $listing, $pigs] = $this->pooledOrder(poolSize: 5, quantity: 3);

        $staff  = $this->makeUser('admin');
        $chosen = [$pigs[0]->id, $pigs[1]->id, $pigs[2]->id];

        $this->allocations()->selectForOrderItem($item, $chosen, $staff);
        $this->orders()->confirm($order, $staff);

        $listing->refresh();

        $this->assertSame(3, PigSale::whereIn('pig_id', $chosen)->count());
        $this->assertSame(2, $this->pool()->availableCount($listing));
        $this->assertSame('2.0000', (string) $listing->quantity_available);
        $this->assertSame(StorefrontListing::STATUS_PUBLISHED, $listing->status);
        $this->assertTrue($listing->isOrderable());
        $this->assertSame(1, StorefrontListing::visible()->whereKey($listing->id)->count());

        $this->get(route('storefront.shop'))
            ->assertOk()
            ->assertSee($listing->title);

        $this->get(route('storefront.listing', $listing->slug))
            ->assertOk()
            ->assertSee('2 pigs available')
            ->assertDontSee('Source record no longer available');

        [$nextOrder, $nextItem] = $this->orderForExistingListing($listing, 2);
        $this->allocations()->selectForOrderItem($nextItem, [$pigs[3]->id, $pigs[4]->id], $staff);

        $this->assertSame(0, $this->allocations()->outstandingFor($nextItem));
        $this->assertSame(StorefrontOrder::STATUS_PENDING_CONFIRMATION, $nextOrder->status);
    }

    public function test_completing_a_partial_pooled_order_does_not_close_the_remaining_pool(): void
    {
        [$order, $item, $listing, $pigs] = $this->pooledOrder(poolSize: 5, quantity: 3);

        $staff = $this->makeUser('admin');
        $this->allocations()->selectForOrderItem($item, [$pigs[0]->id, $pigs[1]->id, $pigs[2]->id], $staff);
        $this->orders()->confirm($order, $staff);

        $this->orders()->markReadyOrDispatched($order->fresh(), $staff);
        $this->orders()->complete($order->fresh(), $staff);

        $listing->refresh();

        $this->assertSame(2, $this->pool()->availableCount($listing));
        $this->assertSame('2.0000', (string) $listing->quantity_available);
        $this->assertSame(StorefrontListing::STATUS_PUBLISHED, $listing->status);
        $this->assertTrue($listing->isOrderable());
        $this->assertSame(1, StorefrontListing::visible()->whereKey($listing->id)->count());
    }

    public function test_the_listing_sells_out_only_after_the_final_pool_pigs_are_sold(): void
    {
        [$order, $item, $listing, $pigs] = $this->pooledOrder(poolSize: 5, quantity: 3);

        $staff = $this->makeUser('admin');
        $this->allocations()->selectForOrderItem($item, [$pigs[0]->id, $pigs[1]->id, $pigs[2]->id], $staff);
        $this->orders()->confirm($order, $staff);

        [$finalOrder, $finalItem] = $this->orderForExistingListing($listing->fresh(), 2, '-FINAL');
        $this->allocations()->selectForOrderItem($finalItem, [$pigs[3]->id, $pigs[4]->id], $staff);
        $this->orders()->confirm($finalOrder, $staff);

        $listing->refresh();

        $this->assertSame(5, PigSale::whereIn('pig_id', array_column($pigs, 'id'))->count());
        $this->assertSame(0, $this->pool()->availableCount($listing));
        $this->assertSame('0.0000', (string) $listing->quantity_available);
        $this->assertSame(StorefrontListing::STATUS_SOLD_OUT, $listing->status);
        $this->assertFalse($listing->isOrderable());
        $this->assertSame(0, StorefrontListing::visible()->whereKey($listing->id)->count());
    }

    /**
     * Double-clicked Confirm.
     */
    public function test_confirming_twice_does_not_sell_the_animals_twice(): void
    {
        [$order, $item, , $pigs] = $this->pooledOrder(poolSize: 3, quantity: 2);

        $staff = $this->makeUser('admin');
        $this->allocations()->selectForOrderItem($item, [$pigs[0]->id, $pigs[1]->id], $staff);

        $this->orders()->confirm($order, $staff);
        $this->orders()->confirm($order->fresh(), $staff);

        $this->assertSame(
            2,
            PigSale::count(),
            'Two animals, two sales, however many times Confirm is pressed.',
        );
    }

    /**
     * The de-duplication that stops a second sale.
     *
     * Approval already sold the animals through `RecordPigSaleService`. The
     * storefront finance subscriber must treat the line as HANDLED — not sell
     * it again, and not report it as a deferred problem, which would put a
     * false alarm on the order's finance panel.
     */
    public function test_the_finance_subscriber_does_not_sell_a_pooled_line_again(): void
    {
        [$order, $item, , $pigs] = $this->pooledOrder(poolSize: 3, quantity: 2);

        $staff = $this->makeUser('admin');
        $this->allocations()->selectForOrderItem($item, [$pigs[0]->id, $pigs[1]->id], $staff);
        $this->orders()->confirm($order, $staff);

        $before = PigSale::count();

        // Drain the outbox: the confirmation dispatched StorefrontOrderConfirmed
        // and the subscriber will now see this line.
        \App\Jobs\ProcessPendingDomainEventsJob::dispatchSync();

        $this->assertSame(
            $before,
            PigSale::count(),
            'The subscriber sold the pooled line a second time.',
        );

        // The assertion that actually catches a missing guard.
        //
        // "No second sale" is NOT enough on its own: without the guard the line
        // is deferred rather than re-sold, so the sale count is unchanged
        // either way and the test would pass against the defect. The real
        // difference is the order's posting state — `posted` when the
        // subscriber recognises the line as handled, `skipped` with
        // "pig_awaiting_pig_selection" when it does not.
        //
        // A line that sold correctly being reported as an unresolved problem is
        // the false alarm Phase 15 removed from this very panel.
        $order->refresh();

        $this->assertSame(
            'posted',
            $order->finance_posting_status,
            'A pooled line whose animals were sold at approval must post cleanly, not be '
            . 'reported as a deferred problem: ' . (string) $order->finance_posting_error,
        );
    }

    /**
     * Cancelling before confirmation returns the animals to the shop.
     */
    public function test_cancelling_an_unconfirmed_order_releases_the_animals(): void
    {
        [$order, $item, $listing, $pigs] = $this->pooledOrder(poolSize: 4, quantity: 2);

        $staff = $this->makeUser('admin');
        $this->allocations()->selectForOrderItem($item, [$pigs[0]->id, $pigs[1]->id], $staff);

        $this->assertSame(2, $this->pool()->availableCount($listing));

        $this->orders()->cancel($order, $staff, 'Customer changed their mind.');

        $this->assertSame(4, $this->pool()->availableCount($listing));
        $this->assertSame(0, PigSale::count(), 'Cancelling must never sell anything.');
    }

    /**
     * A legacy single-pig listing has no pool and must be untouched by any of
     * this — it keeps confirming and selling through the old subscriber path.
     */
    public function test_a_listing_without_a_pool_still_confirms_the_old_way(): void
    {
        $listing = $this->makePublishedListing([
            'kind'               => StorefrontListing::KIND_FEED,
            'pricing_basis'      => StorefrontListing::BASIS_FIXED,
            'fixed_price'        => '50.00',
            'quantity_available' => '10',
        ]);

        $order = StorefrontOrder::create([
            'organization_id'   => $this->org->id,
            'reference'         => 'SO-LEGACY-' . strtoupper(uniqid()),
            'status'            => StorefrontOrder::STATUS_PENDING_CONFIRMATION,
            'payment_status'    => StorefrontOrder::PAY_COD,
            'payment_method'    => StorefrontOrder::METHOD_COD,
            'fulfilment_method' => StorefrontOrder::FULFIL_PICKUP,
            'subtotal' => '50.00', 'delivery_fee' => '0.00', 'total' => '50.00',
            'currency_code' => 'GHS', 'placed_at' => now(),
        ]);

        StorefrontOrderItem::create([
            'organization_id' => $this->org->id,
            'order_id'        => $order->id,
            'listing_id'      => $listing->id,
            'kind'            => StorefrontListing::KIND_FEED,
            'description'     => 'A bag of feed',
            'quantity'        => '1',
            'quantity_unit'   => 'bag',
            'price_basis'     => 'fixed',
            'unit_price'      => '50.00',
            'line_total'      => '50.00',
        ]);

        $confirmed = $this->orders()->confirm($order, $this->makeUser('admin'));

        $this->assertSame(StorefrontOrder::STATUS_CONFIRMED, $confirmed->status);
    }
}
