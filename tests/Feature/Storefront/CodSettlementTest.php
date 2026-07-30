<?php

namespace Tests\Feature\Storefront;

use App\Models\Storefront\StorefrontListing;
use App\Models\Storefront\StorefrontOrder;
use App\Storefront\Services\CartService;
use App\Storefront\Services\CheckoutService;
use App\Storefront\Services\Exceptions\CodSettlementException;
use App\Storefront\Services\OrderService;
use App\Storefront\Support\CheckoutData;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * COD settlement integrity (CF-P0-009).
 *
 * complete() used to call settleCodPayments() and then set payment_status =
 * 'paid' UNCONDITIONALLY. settleCodPayments() skipped any line without a posted
 * sale, counted them, wrote a note, and completion carried on regardless — so an
 * order whose lines had ALL failed to settle still ended up paid and completed.
 *
 * The note is the giveaway: the code knew something had gone wrong and recorded
 * it somewhere nothing acts on. The consequence is a cash discrepancy — the
 * business believes it has been paid, no receipt exists, and the receivable is
 * closed against nothing.
 *
 * These tests deliberately do NOT run the finance orchestrator, so order lines
 * have no posted sale. That is the exact condition the register describes.
 */
class CodSettlementTest extends StorefrontTestCase
{
    use RefreshDatabase;

    /**
     * A confirmed COD order whose lines were never posted to finance.
     */
    protected function unpostedCodOrder(): StorefrontOrder
    {
        $buyer   = $this->makeUser('customer');
        $listing = $this->makePublishedListing([
            'kind'               => StorefrontListing::KIND_FEED,
            'pricing_basis'      => StorefrontListing::BASIS_FIXED,
            'fixed_price'        => '30.00',
            'quantity_available' => '20',
        ]);

        $this->app->make(CartService::class)->addItem($buyer, $listing, '2');
        $order = $this->app->make(CheckoutService::class)->checkout(
            $buyer,
            CheckoutData::fromArray(['payment_method' => 'cod', 'fulfilment_method' => 'pickup']),
        );

        $staff = $this->makeUser('admin');
        $svc   = $this->app->make(OrderService::class);

        $svc->confirm($order->fresh(), $staff);
        $svc->markReadyOrDispatched($order->fresh(), $staff);

        return $order->fresh();
    }

    public function test_completion_is_refused_when_lines_have_no_posted_sale(): void
    {
        $order = $this->unpostedCodOrder();
        $staff = $this->makeUser('admin');

        $this->expectException(CodSettlementException::class);

        $this->app->make(OrderService::class)->complete($order, $staff);
    }

    public function test_refused_completion_leaves_the_order_unpaid_and_incomplete(): void
    {
        $order         = $this->unpostedCodOrder();
        $staff         = $this->makeUser('admin');
        $statusBefore  = $order->status;
        $paymentBefore = $order->payment_status;

        try {
            $this->app->make(OrderService::class)->complete($order, $staff);
        } catch (CodSettlementException) {
            // expected
        }

        $after = $order->fresh();

        $this->assertNotSame(
            StorefrontOrder::STATUS_COMPLETED,
            $after->status,
            'The order was completed despite settlement failing.',
        );

        $this->assertNotSame(
            StorefrontOrder::PAY_PAID,
            $after->payment_status,
            'The order was marked PAID with no settled sale behind it. This is the '
            . 'CF-P0-009 cash discrepancy: no receipt, no receivable cleared, but the '
            . 'business believes it has the money.',
        );

        $this->assertSame($statusBefore, $after->status);
        $this->assertSame($paymentBefore, $after->payment_status);
    }

    /**
     * The failure must be visible, not merely thrown. Staff need to see why an
     * order will not complete, and the event must survive the rolled-back
     * transaction — which is why it is recorded before the transaction opens.
     */
    public function test_settlement_failure_is_recorded_as_a_visible_event(): void
    {
        $order = $this->unpostedCodOrder();
        $staff = $this->makeUser('admin');

        try {
            $this->app->make(OrderService::class)->complete($order, $staff);
        } catch (CodSettlementException) {
            // expected
        }

        $this->assertDatabaseHas('storefront_order_events', [
            'order_id' => $order->id,
            'type'     => 'system',
        ]);

        $event = \App\Models\Storefront\StorefrontOrderEvent::query()
            ->where('order_id', $order->id)
            ->where('type', 'system')
            ->latest('id')
            ->first();

        $this->assertNotNull($event);
        $this->assertStringContainsString(
            'BLOCKED',
            (string) $event->note,
            'The recorded event must make it obvious that completion was refused.',
        );
    }

    /**
     * The exception must carry per-line reasons so staff can act, rather than a
     * bare "settlement failed".
     */
    public function test_exception_reports_each_unsettleable_line(): void
    {
        $order = $this->unpostedCodOrder();
        $staff = $this->makeUser('admin');

        try {
            $this->app->make(OrderService::class)->complete($order, $staff);
            $this->fail('Expected CodSettlementException.');
        } catch (CodSettlementException $e) {
            $this->assertNotEmpty($e->reasons());
            $this->assertStringContainsString('no posted sale', $e->reasons()[0]);
        }
    }
}
