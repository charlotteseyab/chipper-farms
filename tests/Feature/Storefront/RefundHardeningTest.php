<?php

namespace Tests\Feature\Storefront;

use App\Models\PigSale;
use App\Models\Storefront\StorefrontListing;
use App\Models\Storefront\StorefrontOrder;
use App\Storefront\Services\StorefrontOrderRefundService;
use Database\Seeders\StorefrontOrderPermissionsSeeder;
use Database\Seeders\StorefrontRefundPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Phase 6 — refund scope/gating + public-endpoint throttling.
 *
 * Refund GL mechanics live in JournalReversalService (covered by the finance
 * suite); these tests exercise the storefront orchestration: the state machine,
 * treasurer-grade gating, the clean (unposted) path, the finance hand-off note,
 * operational restock, and the rate limiters.
 */
class RefundHardeningTest extends StorefrontTestCase
{
    use RefreshDatabase;

    protected function makeRefundableOrder(array $overrides = []): StorefrontOrder
    {
        return StorefrontOrder::create(array_merge([
            'organization_id'        => $this->org->id,
            'reference'              => 'SO-' . strtoupper(uniqid()),
            'status'                 => StorefrontOrder::STATUS_COMPLETED,
            'payment_status'         => StorefrontOrder::PAY_PAID,
            'payment_method'         => StorefrontOrder::METHOD_COD,
            'fulfilment_method'      => StorefrontOrder::FULFIL_PICKUP,
            'subtotal'               => '120.00',
            'delivery_fee'           => '0.00',
            'total'                  => '120.00',
            'currency_code'          => 'GHS',
            'finance_posting_status' => 'pending',
            'placed_at'              => now(),
        ], $overrides));
    }

    public function test_salesperson_cannot_refund(): void
    {
        $this->seed(StorefrontOrderPermissionsSeeder::class);
        $this->seed(StorefrontRefundPermissionsSeeder::class);

        $order = $this->makeRefundableOrder();
        $salesperson = $this->makeUser('salesperson');

        $this->actingAs($salesperson)
            ->post(route('storefront.admin.orders.refund', $order), ['reason' => 'change of mind'])
            ->assertForbidden();

        $this->assertNotSame(StorefrontOrder::STATUS_REFUNDED, $order->fresh()->status);
    }

    public function test_admin_can_refund_unposted_order_cleanly(): void
    {
        $this->seed(StorefrontOrderPermissionsSeeder::class);
        $this->seed(StorefrontRefundPermissionsSeeder::class);

        $listing = $this->makePublishedListing([
            'kind' => StorefrontListing::KIND_FEED, 'quantity_unit' => 'kg',
            'quantity_available' => '90', 'status' => StorefrontListing::STATUS_SOLD_OUT,
        ]);
        $order = $this->makeRefundableOrder(['finance_posting_status' => 'pending']);
        $order->items()->create([
            'organization_id' => $this->org->id, 'listing_id' => $listing->id,
            'kind' => StorefrontListing::KIND_FEED, 'description' => 'Feed',
            'quantity' => '10', 'quantity_unit' => 'kg', 'price_basis' => 'fixed',
            'unit_price' => '12.00', 'line_total' => '120.00',
        ]);

        $admin = $this->makeUser('admin');
        $this->actingAs($admin)
            ->post(route('storefront.admin.orders.refund', $order), ['reason' => 'wrong item'])
            ->assertRedirect();

        $order->refresh();
        $this->assertSame(StorefrontOrder::STATUS_REFUNDED, $order->status);
        $this->assertSame(StorefrontOrder::PAY_REFUNDED, $order->payment_status);
        $this->assertStringContainsString('clean refund', $order->finance_reversal_note);

        // Operational restock: quantity returned + republished.
        $listing->refresh();
        $this->assertEquals(100.0, (float) $listing->quantity_available);
        $this->assertSame(StorefrontListing::STATUS_PUBLISHED, $listing->status);
    }

    /**
     * Renamed and re-purposed (CF-P0-008).
     *
     * This was test_posted_pig_line_is_handed_to_finance_not_auto_reversed, and
     * it asserted that a pig line produced a "Handed to finance" note instead of
     * being reversed. That contract is gone: pig lines ARE now auto-reversed by
     * the sales-return subscribers, so asserting the handoff would pin the
     * defect in place.
     *
     * The fixture points at PigSale#999, which does not exist, so what it
     * genuinely covers is the DANGLING REFERENCE path: an order line referencing
     * a sale that is no longer there. The refund must still complete — the
     * customer is owed their money regardless — while reporting that one line's
     * accounting could not be reversed. Silently dropping it is what the old
     * note effectively did.
     */
    public function test_refund_reports_a_line_whose_sale_no_longer_exists(): void
    {
        $order = $this->makeRefundableOrder(['finance_posting_status' => 'posted']);
        $order->items()->create([
            'organization_id'  => $this->org->id,
            'kind'             => StorefrontListing::KIND_LIVE_PIG,
            'description'      => 'Pig',
            'quantity'         => '1', 'quantity_unit' => 'head', 'price_basis' => 'live',
            'unit_price'       => '120.00', 'line_total' => '120.00',
            'posted_sale_type' => PigSale::class, 'posted_sale_id' => 999,
        ]);

        $refunded = $this->app->make(StorefrontOrderRefundService::class)
            ->refund($order, $this->makeUser('admin'), 'sick animal');

        // The refund completes: the customer is owed their money whatever the
        // state of the accounting behind it.
        $this->assertSame(StorefrontOrder::STATUS_REFUNDED, $refunded->status);

        // But the unreversed line is reported rather than lost.
        $this->assertStringContainsString('Requires attention', $refunded->finance_reversal_note);
        $this->assertStringContainsString('no longer exists', $refunded->finance_reversal_note);
    }

    public function test_refund_rejected_from_non_refundable_status(): void
    {
        $order = $this->makeRefundableOrder(['status' => StorefrontOrder::STATUS_CANCELLED]);

        $this->expectException(\App\Storefront\Services\Exceptions\InvalidOrderTransitionException::class);
        $this->app->make(StorefrontOrderRefundService::class)
            ->refund($order, $this->makeUser('admin'), 'late');
    }

    public function test_quote_endpoint_is_rate_limited(): void
    {
        $sawThrottle = false;
        for ($i = 0; $i < 7; $i++) {
            $res = $this->post(route('storefront.quote.store'), []); // invalid is fine; throttle runs first
            if ($res->status() === 429) {
                $sawThrottle = true;
                break;
            }
        }
        $this->assertTrue($sawThrottle, 'The public quote endpoint should be throttled.');
    }
}
