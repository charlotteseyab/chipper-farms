<?php

namespace Tests\Feature\Finance\SalesReturns;

use App\Domain\Events\Sales\FeedSaleRecorded;
use App\Domain\Events\Sales\SalesReturnRecorded;
use App\Finance\Subscribers\Sales\FeedSaleCogsSubscriber;
use App\Finance\Subscribers\Sales\FeedSaleReturnCogsSubscriber;
use App\Finance\Subscribers\Sales\FeedSaleRevenueSubscriber;
use App\Finance\Subscribers\Sales\SalesReturnRevenueSubscriber;
use App\Models\DomainEvent;
use App\Models\FeedSale;
use App\Models\FeedType;
use App\Models\Finance\Account;
use App\Models\Finance\CreditNote;
use App\Models\Finance\InventoryAccountMapping;
use App\Models\Finance\InventoryBalance;
use App\Models\Finance\InventoryMovement;
use App\Models\Finance\JournalBatch;
use App\Models\SalesReturn;
use App\Models\Storefront\StorefrontOrder;
use App\Models\User;
use App\Services\Finance\Inventory\InventoryService;
use App\Services\Sales\RecordFeedSaleService;
use App\Storefront\Payments\PaystackClient;
use App\Storefront\Services\StorefrontOrderRefundService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Finance\FinanceTestSetup;
use Tests\Feature\Storefront\Support\FakePaystackClient;
use Tests\TestCase;

/**
 * The seam: a storefront refund raises a real sales return (CF-P0-008, phase 5).
 *
 * Phases 1–4 built returns that reverse a sale completely. Nothing called them.
 * StorefrontOrderRefundService still reversed only the revenue journal and wrote
 * a finance_reversal_note handing COGS, inventory, biological assets and pig
 * restoration to a human — a note nothing consumed.
 *
 * These tests assert the two things that connect them:
 *
 *   1. refunding an order RAISES a return per posted line;
 *   2. the return actually reverses the goods, so inventory comes back.
 *
 * And one thing that must NOT happen: the sales return REPLACED the old journal
 * reversal rather than supplementing it. Running both would reverse revenue
 * twice — once through JournalReversalService and once through the return's
 * subscriber — leaving revenue understated by the value of every refund. That is
 * the specific regression test_revenue_is_reversed_exactly_once guards.
 */
class StorefrontRefundRaisesSalesReturnTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    protected FakePaystackClient $paystack;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFinanceFixtures();

        $this->paystack = new FakePaystackClient();
        $this->app->instance(PaystackClient::class, $this->paystack);

        foreach ([
            ['1120', Account::TYPE_ASSET, 'current_asset'],
            ['1220', Account::TYPE_ASSET, 'current_asset'],
            ['1030', Account::TYPE_ASSET, 'current_asset'],
            ['4110', Account::TYPE_REVENUE, 'operating_revenue'],
            ['5100', Account::TYPE_EXPENSE, 'cogs'],
        ] as [$code, $type, $sub]) {
            Account::firstOrCreate(
                ['organization_id' => $this->org->id, 'code' => $code],
                ['name' => "Acct {$code}", 'account_type' => $type, 'account_subtype' => $sub,
                 'currency_code' => 'GHS', 'is_active' => true, 'allow_manual_journal' => true],
            );
        }

        InventoryAccountMapping::create([
            'organization_id'      => $this->org->id,
            'item_kind'            => InventoryAccountMapping::KIND_FINISHED_FEED,
            'category'             => InventoryAccountMapping::DEFAULT_CATEGORY_TOKEN,
            'is_default'           => true,
            'inventory_account_id' => Account::where('organization_id', $this->org->id)->where('code', '1220')->value('id'),
            'cogs_account_id'      => Account::where('organization_id', $this->org->id)->where('code', '5100')->value('id'),
        ]);

        config()->set('feed_sales.revenue_accounts', ['direct' => '4110', 'storefront' => '4110']);
        config()->set('feed_sales.ar_account_code', '1120');
        config()->set('feed_sales.default_cash_account_code', '1030');
    }

    protected function staff(): User
    {
        return User::forceCreate([
            'name' => 'Admin', 'email' => 'staff_' . uniqid() . '@example.com',
            'password' => bcrypt('x'), 'role' => 'admin',
            'role_id' => \App\Models\Role::firstOrCreate(['name' => 'admin'])->id,
            'organization_id' => $this->org->id, 'active_status' => true,
        ]);
    }

    /**
     * A refundable storefront order with ONE line linked to a fully-posted feed
     * sale — the shape a confirmed, finance-posted storefront order has.
     *
     * @return array{0:StorefrontOrder, 1:FeedType, 2:FeedSale}
     */
    protected function refundableOrderWithPostedFeedSale(): array
    {
        $feedType = FeedType::create(['name' => 'Feed ' . uniqid()]);

        app(InventoryService::class)->recordFeedProduction(
            feedTypeId: $feedType->id, quantity: 100.0, totalCost: 800.0,
            feedBatchId: random_int(1000, 9999), occurredAt: CarbonImmutable::parse('2026-05-01'),
        );

        $sale = app(RecordFeedSaleService::class)->execute($feedType, $this->user, [
            'quantity_kg' => 40, 'unit_price' => 12.00, 'sale_date' => '2026-05-15',
            'payment_status' => 'pending', 'channel' => 'storefront', 'buyer_name' => 'Shopper',
        ]);

        $saleEvent = DomainEvent::where('event_type', FeedSaleRecorded::TYPE)
            ->where('source_id', $sale->id)->firstOrFail();

        app(FeedSaleRevenueSubscriber::class)->handle($saleEvent->id);
        $saleEvent->update(['status' => DomainEvent::STATUS_PENDING]);
        app(FeedSaleCogsSubscriber::class)->handle($saleEvent->id);

        $order = StorefrontOrder::forceCreate([
            'organization_id'        => $this->org->id,
            'reference'              => 'SO-' . str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT),
            'status'                 => StorefrontOrder::STATUS_COMPLETED,
            'payment_method'         => StorefrontOrder::METHOD_COD,
            'payment_status'         => StorefrontOrder::PAY_PAID,
            'finance_posting_status' => 'posted',
            'subtotal'               => '480.00',
            'total'                  => '480.00',
            'currency_code'          => 'GHS',
        ]);

        $order->items()->create([
            'organization_id'  => $this->org->id,
            'kind'             => 'feed',
            'description'      => 'Grower feed',
            'quantity'         => '40',
            'quantity_unit'    => 'kg',
            'price_basis'      => 'fixed',
            'unit_price'       => '12.00',
            'line_total'       => '480.00',
            'posted_sale_type' => FeedSale::class,
            'posted_sale_id'   => $sale->id,
        ]);

        return [$order->fresh(), $feedType, $sale->fresh()];
    }

    /** Drain the return event through both return subscribers. */
    protected function drainReturn(SalesReturn $return): SalesReturn
    {
        $event = DomainEvent::where('event_type', SalesReturnRecorded::TYPE)
            ->where('source_id', $return->id)->firstOrFail();

        app(FeedSaleReturnCogsSubscriber::class)->handle($event->id);
        $event->update(['status' => DomainEvent::STATUS_PENDING]);
        app(SalesReturnRevenueSubscriber::class)->handle($event->id);

        return $return->fresh();
    }

    protected function onHand(FeedType $feedType): string
    {
        return (string) (InventoryBalance::query()
            ->where('item_kind', InventoryMovement::ITEM_KIND_FINISHED_FEED)
            ->where('item_id', $feedType->id)
            ->value('on_hand_qty') ?? '0');
    }

    /* ------------------------------------------------------------------ */

    public function test_refunding_an_order_raises_a_sales_return_per_posted_line(): void
    {
        [$order, , $sale] = $this->refundableOrderWithPostedFeedSale();

        app(StorefrontOrderRefundService::class)
            ->refund($order, $this->staff(), 'customer changed their mind');

        $return = SalesReturn::where('returnable_type', FeedSale::class)
            ->where('returnable_id', $sale->id)->first();

        $this->assertNotNull(
            $return,
            'Refunding a posted order raised no sales return. The reversal work exists but '
            . 'nothing calls it, which is the state CF-P0-008 was left in.',
        );

        $this->assertSame($order->id, $return->storefront_order_id, 'The return must link back to the order.');
        $this->assertSame(0, bccomp((string) $return->quantity, '40', 4));
    }

    /**
     * The handoff note is gone: the note now records what was actually reversed.
     */
    public function test_refund_note_records_the_return_instead_of_a_handoff(): void
    {
        [$order] = $this->refundableOrderWithPostedFeedSale();

        $refunded = app(StorefrontOrderRefundService::class)
            ->refund($order, $this->staff(), 'returned');

        $this->assertStringContainsString('reversed by sales return', $refunded->finance_reversal_note);
        $this->assertStringNotContainsString(
            'Handed to finance',
            (string) $refunded->finance_reversal_note,
            'The refund still hands work to finance instead of doing it.',
        );
    }

    /**
     * End to end: refunding actually gives the goods back once the return drains.
     */
    public function test_refund_restores_inventory_once_the_return_is_processed(): void
    {
        [$order, $feedType, $sale] = $this->refundableOrderWithPostedFeedSale();

        $this->assertSame('60.0000', $this->onHand($feedType), 'The sale should leave 60kg.');

        app(StorefrontOrderRefundService::class)
            ->refund($order, $this->staff(), 'returned');

        $return = SalesReturn::where('returnable_id', $sale->id)->firstOrFail();
        $this->drainReturn($return);

        $this->assertSame(
            '100.0000',
            $this->onHand($feedType),
            'The refund did not put the goods back. A refunded order whose stock never '
            . 'returns leaves inventory permanently understated.',
        );
    }

    /**
     * THE REGRESSION THAT MATTERS.
     *
     * The sales return replaced the old JournalReversalService call rather than
     * being added alongside it. If both ran, revenue would be reversed twice —
     * once by the reversal batch and once by the return's revenue subscriber —
     * understating revenue by the value of every refund, silently.
     */
    public function test_revenue_is_reversed_exactly_once(): void
    {
        [$order, , $sale] = $this->refundableOrderWithPostedFeedSale();

        app(StorefrontOrderRefundService::class)
            ->refund($order, $this->staff(), 'returned');

        $return = SalesReturn::where('returnable_id', $sale->id)->firstOrFail();
        $this->drainReturn($return);

        // Exactly one credit-note posting batch from the return...
        $this->assertSame(
            1,
            JournalBatch::where('batch_type', 'auto_credit_note')->count(),
            'More than one credit-note revenue reversal batch exists for a single refund.',
        );

        $this->assertSame(
            1,
            CreditNote::where('sales_return_id', $return->id)->count(),
            'More than one credit note exists for a single refund return.',
        );

        // ...and the sale's ORIGINAL revenue batch must not also have been
        // reversed through the old mechanism.
        $originalBatch = JournalBatch::find($sale->revenue_journal_batch_id);
        $this->assertNotNull($originalBatch);
        $this->assertNull(
            $originalBatch->reversed_by_batch_id,
            'The original revenue batch was ALSO reversed by JournalReversalService. '
            . 'Revenue has now been reversed twice for one refund.',
        );
    }

    /**
     * An order that never posted to finance has nothing to return.
     */
    public function test_unposted_order_raises_no_sales_return(): void
    {
        [$order] = $this->refundableOrderWithPostedFeedSale();
        $order->update(['finance_posting_status' => 'pending']);

        $refunded = app(StorefrontOrderRefundService::class)
            ->refund($order->fresh(), $this->staff(), 'never posted');

        $this->assertSame(0, SalesReturn::count(), 'Nothing was posted, so nothing can be returned.');
        $this->assertStringContainsString('clean refund', (string) $refunded->finance_reversal_note);
    }
}
