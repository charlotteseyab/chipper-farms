<?php

namespace Tests\Feature\Storefront;

use App\Models\PigManagement;
use App\Models\Storefront\StorefrontListing;
use App\Models\Storefront\StorefrontOrder;
use App\Storefront\Services\CartService;
use App\Storefront\Services\CheckoutService;
use App\Storefront\Services\Exceptions\InvalidOrderTransitionException;
use App\Storefront\Services\OrderService;
use App\Storefront\Support\CheckoutData;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Order confirmation integrity (CF-P0-006, CF-P2-023).
 *
 * The register frames CF-P0-006 as a concurrency defect requiring
 * multi-connection tests to demonstrate. It is that, but it is also something
 * simpler and more easily triggered, which no concurrency is needed to reach:
 *
 *   assertTransitionAllowed() returns EARLY WITHOUT THROWING when the order is
 *   already at the target status. confirm() then falls straight through to
 *   applyInventoryEffect(reserve: true) and decrements stock a SECOND time.
 *
 * So a double-clicked confirm button, a retried request, or any duplicate
 * delivery decrements inventory twice for one order. That is reachable by one
 * user on one connection.
 *
 * These tests cover the single-process integrity properties. True multi-process
 * contention is covered separately in
 * tests/Concurrency/OrderConfirmationConcurrencyTest.php, because SQLite cannot
 * demonstrate row locking.
 */
class OrderConfirmationIntegrityTest extends StorefrontTestCase
{
    use RefreshDatabase;

    /**
     * @return array{0:\App\Models\User, 1:StorefrontListing, 2:StorefrontOrder}
     */
    protected function placeProductOrder(string $available = '10', string $qty = '1'): array
    {
        $user    = $this->makeUser('customer');
        $listing = $this->makePublishedListing([
            'kind'               => StorefrontListing::KIND_FEED,
            'pricing_basis'      => StorefrontListing::BASIS_FIXED,
            'fixed_price'        => '100.00',
            'quantity_available' => $available,
        ]);

        $this->app->make(CartService::class)->addItem($user, $listing, $qty);
        $order = $this->app->make(CheckoutService::class)->checkout(
            $user,
            CheckoutData::fromArray(['payment_method' => 'cod', 'fulfilment_method' => 'pickup']),
        );

        return [$user, $listing, $order];
    }

    /**
     * The double-submit case. Confirming twice must decrement stock once.
     */
    public function test_repeated_confirmation_does_not_decrement_stock_twice(): void
    {
        [, $listing, $order] = $this->placeProductOrder(available: '10', qty: '3');

        $staff   = $this->makeUser('admin');
        $service = $this->app->make(OrderService::class);

        $service->confirm($order, $staff);
        $afterFirst = (string) $listing->fresh()->quantity_available;

        // Second confirmation of an already-confirmed order. Whether this throws
        // or is a silent no-op is a design choice; what must NOT happen is a
        // second inventory decrement.
        try {
            $service->confirm($order->fresh(), $staff);
        } catch (InvalidOrderTransitionException) {
            // Acceptable outcome — rejecting the duplicate is also correct.
        }

        $this->assertSame(
            $afterFirst,
            (string) $listing->fresh()->quantity_available,
            'Confirming an already-confirmed order decremented stock a second time. '
            . 'assertTransitionAllowed() returns early when status === target, so confirm() '
            . 'fell through to applyInventoryEffect() again.',
        );
    }

    /**
     * A pig can be sold once. If another order already reserved the listing, the
     * second confirmation must fail loudly rather than silently succeed.
     */
    public function test_confirming_an_already_reserved_pig_listing_is_rejected(): void
    {
        $pig     = $this->makePig();
        $listing = $this->makePublishedListing([
            'kind'               => StorefrontListing::KIND_LIVE_PIG,
            'pricing_basis'      => StorefrontListing::BASIS_FIXED,
            'fixed_price'        => '800.00',
            'quantity_available' => '1',
            'source_type'        => PigManagement::class,
            'source_id'          => $pig->id,
        ]);

        // Two customers, two orders, one pig.
        //
        // PHASE 10 CHANGED THE SETUP, NOT THE POINT. Checkout now places a hold,
        // so the second buyer can no longer reach checkout at all while the
        // first buyer's hold is live — which is the new first line of defence
        // and is tested in HoldServiceTest.
        //
        // What this test guards is the SECOND line: reservePigListing() at
        // confirmation. To reach it, the first hold is released between the two
        // checkouts, exactly as it would be if the first buyer's payment window
        // lapsed and the second bought in the gap. Both orders then exist and
        // both are presented for confirmation.
        $orders = [];
        $holds  = $this->app->make(\App\Storefront\Services\HoldService::class);

        foreach (['customer', 'customer'] as $i => $role) {
            $buyer = $this->makeUser($role);
            $this->app->make(CartService::class)->addItem($buyer, $listing, '1');
            $order = $this->app->make(CheckoutService::class)->checkout(
                $buyer,
                CheckoutData::fromArray(['payment_method' => 'cod', 'fulfilment_method' => 'pickup']),
            );

            $orders[] = $order;

            // Free the pig for the next checkout in the loop.
            $holds->releaseForOrder($order, 'Test: simulating a lapsed hold.');
        }

        $staff   = $this->makeUser('admin');
        $service = $this->app->make(OrderService::class);

        $service->confirm($orders[0], $staff);
        $this->assertSame(
            StorefrontListing::STATUS_RESERVED,
            $listing->fresh()->status,
            'The first confirmation should reserve the pig listing.',
        );

        // The second must not silently succeed: reservePigListing() previously
        // returned without error when the listing was no longer published, so
        // BOTH orders reached 'confirmed' for one animal.
        $this->expectException(\RuntimeException::class);
        $service->confirm($orders[1], $staff);
    }

    /**
     * Insufficient stock must be rejected, not clamped to zero.
     */
    public function test_insufficient_stock_is_rejected_rather_than_clamped(): void
    {
        [, $listing, $order] = $this->placeProductOrder(available: '5', qty: '5');

        // Another sale consumes stock after checkout validated it but before
        // staff confirmation — the window the register describes.
        $listing->forceFill(['quantity_available' => '2'])->save();

        $staff   = $this->makeUser('admin');
        $service = $this->app->make(OrderService::class);

        try {
            $service->confirm($order, $staff);
            $this->fail(
                'Confirmation succeeded with insufficient stock. adjustProductQuantity() '
                . 'clamped the negative result to zero, silently overselling.',
            );
        } catch (\RuntimeException) {
            // expected
        }

        $this->assertSame(
            '2.0000',
            (string) $listing->fresh()->quantity_available,
            'A rejected confirmation must leave stock untouched.',
        );

        $this->assertNotSame(
            StorefrontOrder::STATUS_CONFIRMED,
            $order->fresh()->status,
            'A rejected confirmation must leave the order unconfirmed.',
        );
    }

    /**
     * Stock can never go negative through the confirmation path.
     */
    public function test_stock_never_becomes_negative(): void
    {
        [, $listing, $order] = $this->placeProductOrder(available: '1', qty: '1');

        $listing->forceFill(['quantity_available' => '0'])->save();

        $staff = $this->makeUser('admin');

        try {
            $this->app->make(OrderService::class)->confirm($order, $staff);
        } catch (\RuntimeException) {
            // expected
        }

        $this->assertGreaterThanOrEqual(
            0,
            (float) $listing->fresh()->quantity_available,
            'quantity_available went negative.',
        );
    }

    /**
     * CF-P2-023: cancelling a confirmed order restores stock, and a listing that
     * had gone sold_out must return to a state consistent with what the code
     * claims it does.
     *
     * The comment said "if it had been sold_out, re-publish" while the code
     * transitioned to UNPUBLISHED — so restored stock silently became
     * unsellable and needed manual staff intervention to sell again.
     */
    public function test_cancellation_restores_sold_out_listing_to_published(): void
    {
        [, $listing, $order] = $this->placeProductOrder(available: '2', qty: '2');

        $staff   = $this->makeUser('admin');
        $service = $this->app->make(OrderService::class);

        $service->confirm($order, $staff);

        $this->assertSame(
            StorefrontListing::STATUS_SOLD_OUT,
            $listing->fresh()->status,
            'Consuming all stock should mark the listing sold_out.',
        );

        $service->cancel($order->fresh(), $staff, 'customer changed their mind');

        $restored = $listing->fresh();

        $this->assertSame(
            '2.0000',
            (string) $restored->quantity_available,
            'Cancellation must restore the reserved quantity.',
        );

        $this->assertSame(
            StorefrontListing::STATUS_PUBLISHED,
            $restored->status,
            'Restored stock must return to published. Transitioning to unpublished '
            . 'contradicted the code comment and left sellable stock invisible.',
        );
    }
}
