<?php

namespace Tests\Feature\Finance\SalesReturns;

use App\Domain\Events\Sales\FeedSaleRecorded;
use App\Domain\Events\Sales\SalesReturnRecorded;
use App\Finance\Subscribers\Sales\FeedSaleCogsSubscriber;
use App\Finance\Subscribers\Sales\FeedSaleReturnCogsSubscriber;
use App\Finance\Subscribers\Sales\FeedSaleRevenueSubscriber;
use App\Models\DomainEvent;
use App\Models\FeedSale;
use App\Models\FeedType;
use App\Models\Finance\Account;
use App\Models\Finance\InventoryAccountMapping;
use App\Models\Finance\InventoryMovement;
use App\Models\SalesReturn;
use App\Models\Finance\InventoryBalance;
use App\Models\Finance\Journal;
use App\Models\Finance\JournalLine;
use App\Services\Finance\Inventory\InventoryService;
use App\Services\Sales\RecordFeedSaleService;
use App\Services\Sales\RecordSalesReturnService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Finance\FinanceTestSetup;
use Tests\TestCase;

/**
 * Feed sales returns — CF-P0-008, phase 2.
 *
 * The property under test is NOT "a return posts a journal". It is that the
 * general ledger and the inventory subledger stay equal to the pesewa after a
 * return, because GL/subledger drift is exactly what the original
 * StorefrontOrderRefundService refused to risk when it handed this work to
 * finance as a note.
 *
 * The cost-basis assertions are the sharpest ones here. Weighted-average cost
 * moves between a sale and its return, so a return that re-enters stock at
 * TODAY's WAC silently books an inventory valuation gain or loss that never
 * happened. test_return_uses_original_cost_basis_not_current_wac deliberately
 * moves the WAC in between to catch that.
 */
class FeedSaleReturnTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFinanceFixtures();

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

    /**
     * Produce feed, sell some of it, and run both sale subscribers.
     *
     * @return array{0:FeedType, 1:FeedSale}
     */
    protected function soldFeed(
        float $produceQty = 100.0,
        float $produceCost = 800.0,
        float $sellQty = 40.0,
    ): array {
        $feedType = FeedType::create(['name' => 'Grower Feed ' . uniqid()]);

        app(InventoryService::class)->recordFeedProduction(
            feedTypeId: $feedType->id,
            quantity: $produceQty,
            totalCost: $produceCost,
            feedBatchId: random_int(1000, 9999),
            occurredAt: CarbonImmutable::parse('2026-05-01'),
        );

        $sale = app(RecordFeedSaleService::class)->execute($feedType, $this->user, [
            'quantity_kg' => $sellQty, 'unit_price' => 12.00, 'sale_date' => '2026-05-15',
            'payment_status' => 'pending', 'channel' => 'direct', 'buyer_name' => 'Agro Ltd',
        ]);

        $event = DomainEvent::where('event_type', FeedSaleRecorded::TYPE)
            ->where('source_id', $sale->id)->firstOrFail();

        app(FeedSaleRevenueSubscriber::class)->handle($event->id);
        $event->update(['status' => DomainEvent::STATUS_PENDING]);
        app(FeedSaleCogsSubscriber::class)->handle($event->id);

        return [$feedType, $sale->fresh()];
    }

    protected function runReturn(SalesReturn $return): SalesReturn
    {
        $event = DomainEvent::where('event_type', SalesReturnRecorded::TYPE)
            ->where('source_id', $return->id)->firstOrFail();

        app(FeedSaleReturnCogsSubscriber::class)->handle($event->id);

        return $return->fresh();
    }

    /**
     * Read the subledger balance directly rather than through a reader service.
     * The point of these tests is that the subledger row itself is correct, so
     * asserting against the stored balance is the strongest available check.
     */
    protected function balance(FeedType $feedType): ?InventoryBalance
    {
        return InventoryBalance::query()
            ->where('item_kind', InventoryMovement::ITEM_KIND_FINISHED_FEED)
            ->where('item_id', $feedType->id)
            ->first();
    }

    protected function onHand(FeedType $feedType): string
    {
        return (string) ($this->balance($feedType)?->on_hand_qty ?? '0');
    }

    protected function onHandValue(FeedType $feedType): string
    {
        return (string) ($this->balance($feedType)?->on_hand_value ?? '0');
    }

    /* ------------------------------------------------------------------ */
    /* Full return                                                        */
    /* ------------------------------------------------------------------ */

    public function test_full_return_restores_quantity_to_the_subledger(): void
    {
        [$feedType] = $this->soldFeed(produceQty: 100, produceCost: 800, sellQty: 40);

        $this->assertSame('60.0000', $this->onHand($feedType), 'Sale should leave 60kg.');

        $sale = FeedSale::firstOrFail();
        $return = app(RecordSalesReturnService::class)->record(
            sale: $sale, actor: $this->user, quantity: '40', reason: 'customer returned goods',
        );

        $this->runReturn($return);

        $this->assertSame(
            '100.0000',
            $this->onHand($feedType),
            'A full return must put all 40kg back, restoring the original 100kg.',
        );
    }

    public function test_full_return_reverses_cogs_in_the_general_ledger(): void
    {
        [, $sale] = $this->soldFeed();

        // Sale: 40kg at WAC 8.00 = 320.00 COGS.
        $this->assertSame('320.00', $sale->cogs_amount);

        $return = app(RecordSalesReturnService::class)->record(
            sale: $sale, actor: $this->user, quantity: '40', reason: 'returned',
        );

        $return = $this->runReturn($return);

        $this->assertNotNull($return->cogs_reversal_batch_id, 'A reversal batch must be posted.');

        $lines = $this->batchLines($return->cogs_reversal_batch_id);
        $this->assertBalanced($lines);

        // Exact mirror of the sale: Dr Inventory / Cr COGS.
        $this->assertTrue(
            $this->has($lines, '1220', debit: '320.00'),
            'Inventory must be debited with the original cost.',
        );
        $this->assertTrue(
            $this->has($lines, '5100', credit: '320.00'),
            'COGS must be credited with the original cost.',
        );
    }

    /**
     * The property that matters most: after a full return, the GL inventory
     * movement and the subledger value agree.
     */
    public function test_gl_and_subledger_agree_after_a_full_return(): void
    {
        [$feedType, $sale] = $this->soldFeed();

        $return = app(RecordSalesReturnService::class)->record(
            sale: $sale, actor: $this->user, quantity: '40', reason: 'returned',
        );
        $return = $this->runReturn($return);

        // Subledger: back to the full 800.00 produced.
        $this->assertSame(
            '800.0000',
            $this->onHandValue($feedType),
            'Subledger value must return to the original 800.00 after a full return.',
        );

        // GL: the reversal debited inventory by exactly what the sale credited.
        $this->assertSame(
            (string) $sale->cogs_amount,
            number_format((float) $return->cogs_reversed_amount, 2, '.', ''),
            'The amount reversed in the GL must equal the amount the sale posted. '
            . 'Any difference is GL/subledger drift.',
        );
    }

    /* ------------------------------------------------------------------ */
    /* Cost basis — the subtle one                                        */
    /* ------------------------------------------------------------------ */

    /**
     * Between the sale and the return, more feed is produced at a DIFFERENT
     * cost, moving the weighted average. The return must still use the cost the
     * goods left at, not the new WAC.
     *
     * If this fails, every return silently books a valuation gain or loss into
     * cost of sales.
     */
    public function test_return_uses_original_cost_basis_not_current_wac(): void
    {
        [$feedType, $sale] = $this->soldFeed(produceQty: 100, produceCost: 800, sellQty: 40);
        // Sold 40kg at WAC 8.00 → COGS 320.00. 60kg left, valued 480.00.

        // Now produce 60kg at 20.00/kg (1200.00). WAC becomes
        // (480 + 1200) / 120 = 14.00 — nearly double the sale's cost.
        app(InventoryService::class)->recordFeedProduction(
            feedTypeId: $feedType->id,
            quantity: 60.0,
            totalCost: 1200.0,
            feedBatchId: 7777,
            occurredAt: CarbonImmutable::parse('2026-05-20'),
        );

        $return = app(RecordSalesReturnService::class)->record(
            sale: $sale, actor: $this->user, quantity: '40', reason: 'returned late',
        );
        $return = $this->runReturn($return);

        $movement = InventoryMovement::find($return->inventory_movement_id);
        $this->assertNotNull($movement);

        $this->assertSame(
            0,
            bccomp((string) $movement->event_unit_cost, '8', 4),
            'The return re-entered stock at the CURRENT weighted average (14.00) instead of '
            . 'the 8.00 the goods left at. That books an inventory valuation gain that never '
            . 'happened, straight into cost of sales.',
        );

        $lines = $this->batchLines($return->cogs_reversal_batch_id);
        $this->assertTrue(
            $this->has($lines, '5100', credit: '320.00'),
            'COGS must be credited 320.00 — what the sale charged — not a WAC-derived figure.',
        );
    }

    /* ------------------------------------------------------------------ */
    /* Partial returns                                                    */
    /* ------------------------------------------------------------------ */

    public function test_partial_return_reverses_a_proportional_share(): void
    {
        [$feedType, $sale] = $this->soldFeed(produceQty: 100, produceCost: 800, sellQty: 40);

        // Return 10 of the 40kg sold → 25% → COGS reversal 80.00.
        $return = app(RecordSalesReturnService::class)->record(
            sale: $sale, actor: $this->user, quantity: '10', reason: 'partial return',
        );
        $return = $this->runReturn($return);

        // Compared numerically: the column's stored scale is a schema detail,
        // and asserting its string form makes the test brittle to it.
        $this->assertSame(0, bccomp((string) $return->proportion, '0.25', 8));

        $lines = $this->batchLines($return->cogs_reversal_batch_id);
        $this->assertBalanced($lines);
        $this->assertTrue($this->has($lines, '1220', debit: '80.00'));
        $this->assertTrue($this->has($lines, '5100', credit: '80.00'));

        $this->assertSame('70.0000', $this->onHand($feedType), '60kg remaining + 10kg returned.');
    }

    public function test_multiple_partial_returns_cannot_exceed_the_sale(): void
    {
        [, $sale] = $this->soldFeed(sellQty: 40);
        $svc = app(RecordSalesReturnService::class);

        $svc->record(sale: $sale, actor: $this->user, quantity: '25', reason: 'first');
        $svc->record(sale: $sale, actor: $this->user, quantity: '10', reason: 'second');

        // 35 of 40 returned; 10 more would be 45.
        $this->expectException(\InvalidArgumentException::class);
        $svc->record(sale: $sale, actor: $this->user, quantity: '10', reason: 'over-return');
    }

    public function test_each_partial_return_records_its_own_inventory_movement(): void
    {
        [, $sale] = $this->soldFeed(sellQty: 40);
        $svc = app(RecordSalesReturnService::class);

        $first  = $this->runReturn($svc->record(sale: $sale, actor: $this->user, quantity: '10', reason: 'a'));
        $second = $this->runReturn($svc->record(sale: $sale, actor: $this->user, quantity: '10', reason: 'b'));

        // Movements are unique on (source_type, source_id). Keying on the RETURN
        // rather than the sale is what lets a second partial return record its
        // own movement instead of colliding with the first.
        $this->assertNotNull($first->inventory_movement_id);
        $this->assertNotNull($second->inventory_movement_id);
        $this->assertNotSame($first->inventory_movement_id, $second->inventory_movement_id);
    }

    /* ------------------------------------------------------------------ */
    /* Restock = false                                                    */
    /* ------------------------------------------------------------------ */

    /**
     * Goods that do not come back (spoiled, or kept by the customer) must NOT
     * reverse COGS. The cost stays consumed; only revenue is refunded.
     */
    public function test_non_restock_return_does_not_reverse_cogs_or_inventory(): void
    {
        [$feedType, $sale] = $this->soldFeed(sellQty: 40);

        $return = app(RecordSalesReturnService::class)->record(
            sale: $sale, actor: $this->user, quantity: '40',
            reason: 'spoiled in transit', restock: false,
        );
        $return = $this->runReturn($return);

        $this->assertNull(
            $return->cogs_reversal_batch_id,
            'Goods that never came back must not credit COGS — that would understate cost of '
            . 'sales and overstate inventory.',
        );
        $this->assertNull($return->inventory_movement_id);
        $this->assertSame('60.0000', $this->onHand($feedType), 'Stock must stay at 60kg.');
    }

    /* ------------------------------------------------------------------ */
    /* Idempotency                                                        */
    /* ------------------------------------------------------------------ */

    /**
     * The outbox can redeliver an event. Replaying a return must not put stock
     * back twice or post the reversal twice.
     */
    public function test_replaying_the_return_event_is_idempotent(): void
    {
        [$feedType, $sale] = $this->soldFeed(sellQty: 40);

        $return = app(RecordSalesReturnService::class)->record(
            sale: $sale, actor: $this->user, quantity: '40', reason: 'returned',
        );

        $event = DomainEvent::where('event_type', SalesReturnRecorded::TYPE)
            ->where('source_id', $return->id)->firstOrFail();

        app(FeedSaleReturnCogsSubscriber::class)->handle($event->id);
        $qtyAfterFirst   = $this->onHand($feedType);
        $valueAfterFirst = $this->onHandValue($feedType);

        // Redeliver.
        $event->update(['status' => DomainEvent::STATUS_PENDING]);
        app(FeedSaleReturnCogsSubscriber::class)->handle($event->id);

        $this->assertSame(
            $qtyAfterFirst,
            $this->onHand($feedType),
            'A replayed return event put stock back a second time.',
        );
        $this->assertSame(
            $valueAfterFirst,
            $this->onHandValue($feedType),
            'A replayed return event double-valued the inventory.',
        );

        $this->assertSame(
            1,
            InventoryMovement::where('source_type', SalesReturn::class)
                ->where('source_id', $return->id)->count(),
            'Exactly one inventory movement may exist per return.',
        );
    }

    /* ------------------------------------------------------------------ */
    /* Guards                                                             */
    /* ------------------------------------------------------------------ */

    public function test_zero_or_negative_quantity_is_rejected(): void
    {
        [, $sale] = $this->soldFeed();

        $this->expectException(\InvalidArgumentException::class);
        app(RecordSalesReturnService::class)->record(
            sale: $sale, actor: $this->user, quantity: '0', reason: 'nonsense',
        );
    }

    public function test_return_is_allocated_a_sequential_number(): void
    {
        [, $sale] = $this->soldFeed();

        $return = app(RecordSalesReturnService::class)->record(
            sale: $sale, actor: $this->user, quantity: '5', reason: 'returned',
        );

        $this->assertMatchesRegularExpression('/^SRT-\d{4}-\d{5}$/', (string) $return->return_number);
    }

    /* ------------------------------------------------------------------ */
    /* Journal assertion helpers                                          */
    /*                                                                    */
    /* Same shape as FeedSaleFinanceTest's, so the sale and its return are */
    /* asserted with identical machinery — if the two used different       */
    /* comparisons, a mismatch between them could hide in the test rather  */
    /* than in the code.                                                   */
    /* ------------------------------------------------------------------ */

    protected function batchLines(?int $batchId)
    {
        return JournalLine::whereIn(
            'journal_id',
            Journal::whereIn('journal_batch_id', [$batchId])->pluck('id'),
        )->get();
    }

    protected function assertBalanced($lines): void
    {
        $this->assertEqualsWithDelta(
            $lines->sum(fn ($l) => (float) $l->debit),
            $lines->sum(fn ($l) => (float) $l->credit),
            0.001,
            'The reversal batch does not balance.',
        );
    }

    protected function has($lines, string $code, ?string $debit = null, ?string $credit = null): bool
    {
        $acctId = (int) Account::where('organization_id', $this->org->id)
            ->where('code', $code)->value('id');

        foreach ($lines as $l) {
            if ((int) $l->account_id !== $acctId) {
                continue;
            }
            if ($debit !== null && bccomp((string) $l->debit, $debit, 2) === 0) {
                return true;
            }
            if ($credit !== null && bccomp((string) $l->credit, $credit, 2) === 0) {
                return true;
            }
        }

        return false;
    }
}
