<?php

namespace Tests\Feature\Finance\SalesReturns;

use App\Domain\Events\Sales\FeedSaleRecorded;
use App\Domain\Events\Sales\SalesReturnRecorded;
use App\Finance\Subscribers\Sales\FeedSaleCogsSubscriber;
use App\Finance\Subscribers\Sales\FeedSaleRevenueSubscriber;
use App\Finance\Subscribers\Sales\SalesReturnRevenueSubscriber;
use App\Models\DomainEvent;
use App\Models\FeedSale;
use App\Models\FeedType;
use App\Models\Finance\Account;
use App\Models\Finance\CreditNote;
use App\Models\Finance\InventoryAccountMapping;
use App\Models\Finance\Journal;
use App\Models\Finance\JournalBatch;
use App\Models\Finance\JournalLine;
use App\Models\SalesReturn;
use App\Services\Finance\Inventory\InventoryService;
use App\Services\Sales\RecordFeedSaleService;
use App\Services\Sales\RecordSalesReturnService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Finance\FinanceTestSetup;
use Tests\TestCase;

/**
 * Revenue reversal on a sales return — CF-P0-008, phase 4.
 *
 * The COGS subscribers give the goods back; this gives the MONEY back. It runs
 * for every return, including restock=false, where the goods stay consumed but
 * the customer is still owed a refund — a case the COGS path deliberately skips.
 *
 * The account-selection tests are the ones that matter. Crediting the wrong side
 * is not cosmetic: crediting AR on a cash sale leaves a negative receivable for a
 * customer who never owed anything, and leaves cash overstated by the refund.
 */
class SalesReturnRevenueTest extends TestCase
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
     * @return array{0:FeedType, 1:FeedSale}
     */
    protected function soldFeed(string $paymentStatus = 'pending', float $sellQty = 40.0): array
    {
        $feedType = FeedType::create(['name' => 'Feed ' . uniqid()]);

        app(InventoryService::class)->recordFeedProduction(
            feedTypeId: $feedType->id, quantity: 100.0, totalCost: 800.0,
            feedBatchId: random_int(1000, 9999), occurredAt: CarbonImmutable::parse('2026-05-01'),
        );

        $sale = app(RecordFeedSaleService::class)->execute($feedType, $this->user, [
            'quantity_kg' => $sellQty, 'unit_price' => 12.00, 'sale_date' => '2026-05-15',
            'payment_status' => $paymentStatus,
            'payment_method' => $paymentStatus === 'paid' ? 'cash' : null,
            'channel' => 'direct', 'buyer_name' => 'Agro Ltd',
        ]);

        $event = DomainEvent::where('event_type', FeedSaleRecorded::TYPE)
            ->where('source_id', $sale->id)->firstOrFail();

        app(FeedSaleRevenueSubscriber::class)->handle($event->id);
        $event->update(['status' => DomainEvent::STATUS_PENDING]);
        app(FeedSaleCogsSubscriber::class)->handle($event->id);

        return [$feedType, $sale->fresh()];
    }

    /**
     * §44 — "credit note should reverse tax according to the original tax
     * snapshot" (Phase 13).
     *
     * WHY THIS TEST EXISTS AT ALL
     * ---------------------------
     * The reversal was first covered by unit tests over `reversedTax()` and
     * `buildPostingDraft()` separately. Reintroducing the defect — hard-coding
     * the reversal back to '0.00' in `ensureForSalesReturn()` — broke NOTHING,
     * because nothing exercised the line that joins the two. A guard nothing
     * exercises is not a guard, so this drives the whole path.
     *
     * The consequence if it regresses: the farm keeps the VAT it collected on
     * goods that came back, and the customer is credited the net having paid
     * the gross.
     */
    public function test_a_return_credits_the_tax_that_was_charged(): void
    {
        [, $sale] = $this->soldFeed(sellQty: 10.0, paymentStatus: 'paid');

        // The sale carried tax, as a storefront sale does since Phase 12b.
        $profile = \App\Models\Finance\TaxProfile::create([
            'organization_id' => $this->org->id,
            'code' => 'GH-2026-RET', 'name' => 'Ghana standard 2026',
            'applies_to' => 'sales', 'treatment' => \App\Models\Finance\TaxProfile::TREATMENT_STANDARD,
            'price_mode' => \App\Models\Finance\TaxProfile::PRICE_EXCLUSIVE,
            'effective_from' => '2020-01-01', 'is_active' => true,
        ]);

        foreach ([['VAT', '15'], ['NHIL', '2.5'], ['GETFUND', '2.5']] as $i => [$code, $rate]) {
            \App\Models\Finance\TaxProfileComponent::create([
                'tax_profile_id' => $profile->id,
                'code' => $code, 'label' => $code,
                'rate_type' => \App\Models\Finance\TaxProfileComponent::RATE_PERCENT,
                'rate' => $rate,
                'base_mode' => \App\Models\Finance\TaxProfileComponent::BASE_NET,
                'calculation_order' => $i + 1,
            ]);
        }

        $calculation = app(\App\Services\Finance\Tax\TaxCalculator::class)
            ->apply($profile->load('components'), (string) $sale->sale_total);

        $sale->forceFill([
            'tax_profile_id' => $profile->id,
            'tax_amount'     => $calculation->totalTax,
            'tax_snapshot'   => $calculation->toSnapshot(),
        ])->save();

        $return = app(RecordSalesReturnService::class)->record(
            sale: $sale->fresh(), actor: $this->user, quantity: '10', reason: 'Spoiled',
        );

        $this->runRevenueReversal($return);

        $note = \App\Models\Finance\CreditNote::where('sales_return_id', $return->id)->firstOrFail();

        // 120.00 sale total, 20% = 24.00 of tax, all of it returned.
        $this->assertSame(
            $calculation->totalTax,
            bcadd((string) $note->tax_total, '0', 2),
            'The credit note must give back the tax the buyer paid. Zero here means the '
            . 'farm keeps VAT on goods that came back.',
        );

        $this->assertSame(
            bcadd((string) $note->subtotal, $calculation->totalTax, 2),
            bcadd((string) $note->total, '0', 2),
            'The customer is credited the gross — what they actually paid.',
        );
    }

    protected function runRevenueReversal(SalesReturn $return): SalesReturn
    {
        $event = DomainEvent::where('event_type', SalesReturnRecorded::TYPE)
            ->where('source_id', $return->id)->firstOrFail();

        app(SalesReturnRevenueSubscriber::class)->handle($event->id);

        return $return->fresh();
    }

    protected function batchLines(?int $batchId)
    {
        return JournalLine::whereIn(
            'journal_id',
            Journal::whereIn('journal_batch_id', [$batchId])->pluck('id'),
        )->get();
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

    /* ------------------------------------------------------------------ */

    public function test_credit_sale_return_credits_accounts_receivable(): void
    {
        // 40kg @ 12.00 on credit = 480.00 revenue, debited to AR.
        [, $sale] = $this->soldFeed(paymentStatus: 'pending');

        $return = app(RecordSalesReturnService::class)->record(
            sale: $sale, actor: $this->user, quantity: '40', reason: 'returned',
        );
        $return = $this->runRevenueReversal($return);

        $this->assertNotNull($return->revenue_reversal_batch_id);
        $this->assertNotNull($return->credit_note_id);
        $this->assertSame(CreditNote::STATUS_POSTED, $return->creditNote->status);
        $this->assertSame(0, bccomp('480.00', (string) $return->creditNote->total, 2));
        $this->assertSame($return->revenue_reversal_batch_id, $return->creditNote->journal_batch_id);

        $lines = $this->batchLines($return->revenue_reversal_batch_id);

        $this->assertTrue(
            $this->has($lines, '4110', debit: '480.00'),
            'Revenue must be debited — income comes back down.',
        );
        $this->assertTrue(
            $this->has($lines, '1120', credit: '480.00'),
            'A credit sale must credit ACCOUNTS RECEIVABLE: the customer simply owes less. '
            . 'No cash moves, because none was ever taken.',
        );
    }

    public function test_cash_sale_return_credits_cash_not_receivable(): void
    {
        [, $sale] = $this->soldFeed(paymentStatus: 'paid');

        $return = app(RecordSalesReturnService::class)->record(
            sale: $sale, actor: $this->user, quantity: '40', reason: 'returned',
        );
        $return = $this->runRevenueReversal($return);

        $lines = $this->batchLines($return->revenue_reversal_batch_id);
        $this->assertSame(0, bccomp('480.00', (string) $return->creditNote->total, 2));

        $this->assertTrue(
            $this->has($lines, '1030', credit: '480.00'),
            'A paid sale must credit CASH — money is physically going back to the customer.',
        );
        $this->assertFalse(
            $this->has($lines, '1120', credit: '480.00'),
            'Crediting receivables on a cash sale leaves a negative balance for a customer '
            . 'who never owed anything, and overstates cash by the refund.',
        );
    }

    public function test_partial_return_reverses_proportional_revenue(): void
    {
        [, $sale] = $this->soldFeed(paymentStatus: 'pending', sellQty: 40.0);

        // Return 10 of 40 → 25% of 480.00 = 120.00.
        $return = app(RecordSalesReturnService::class)->record(
            sale: $sale, actor: $this->user, quantity: '10', reason: 'partial',
        );
        $return = $this->runRevenueReversal($return);

        $lines = $this->batchLines($return->revenue_reversal_batch_id);

        $this->assertTrue($this->has($lines, '4110', debit: '120.00'));
        $this->assertTrue($this->has($lines, '1120', credit: '120.00'));
        $this->assertSame(0, bccomp((string) $return->revenue_reversed_amount, '120', 2));
        $this->assertSame(0, bccomp((string) $return->creditNote->total, '120', 2));
        $this->assertSame(0, bccomp((string) $return->creditNote->lines()->firstOrFail()->quantity, '10', 4));
    }

    /**
     * Goods that never came back still owe the customer their money. The COGS
     * path skips restock=false; the revenue path must not.
     */
    public function test_non_restock_return_still_refunds_the_customer(): void
    {
        [, $sale] = $this->soldFeed(paymentStatus: 'paid');

        $return = app(RecordSalesReturnService::class)->record(
            sale: $sale, actor: $this->user, quantity: '40',
            reason: 'spoiled in transit', restock: false,
        );
        $return = $this->runRevenueReversal($return);

        $this->assertNotNull(
            $return->revenue_reversal_batch_id,
            'restock=false means the GOODS stay consumed, not that the customer keeps paying.',
        );

        $lines = $this->batchLines($return->revenue_reversal_batch_id);
        $this->assertTrue($this->has($lines, '1030', credit: '480.00'));
        $this->assertSame(CreditNote::STATUS_POSTED, $return->creditNote->status);
    }

    public function test_revenue_reversal_is_idempotent_on_replay(): void
    {
        [, $sale] = $this->soldFeed();

        $return = app(RecordSalesReturnService::class)->record(
            sale: $sale, actor: $this->user, quantity: '40', reason: 'returned',
        );

        $event = DomainEvent::where('event_type', SalesReturnRecorded::TYPE)
            ->where('source_id', $return->id)->firstOrFail();

        app(SalesReturnRevenueSubscriber::class)->handle($event->id);
        $firstBatch = $return->fresh()->revenue_reversal_batch_id;

        $event->update(['status' => DomainEvent::STATUS_PENDING]);
        app(SalesReturnRevenueSubscriber::class)->handle($event->id);

        $this->assertSame(
            $firstBatch,
            $return->fresh()->revenue_reversal_batch_id,
            'A replayed event refunded the customer a second time.',
        );

        $this->assertSame(
            1,
            JournalBatch::where('batch_type', 'auto_credit_note')->count(),
            'Exactly one credit-note posting batch may exist per return.',
        );

        $this->assertSame(
            1,
            CreditNote::where('sales_return_id', $return->id)->count(),
            'Exactly one credit note may exist per return.',
        );
    }

    public function test_return_is_failed_when_the_sale_never_posted_revenue(): void
    {
        $feedType = FeedType::create(['name' => 'Unposted ' . uniqid()]);

        app(InventoryService::class)->recordFeedProduction(
            feedTypeId: $feedType->id, quantity: 50.0, totalCost: 400.0,
            feedBatchId: 4242, occurredAt: CarbonImmutable::parse('2026-05-01'),
        );

        // Recorded but the revenue subscriber never ran — the outbox is behind.
        $sale = app(RecordFeedSaleService::class)->execute($feedType, $this->user, [
            'quantity_kg' => 10, 'unit_price' => 12.00, 'sale_date' => '2026-05-15',
            'payment_status' => 'pending', 'channel' => 'direct', 'buyer_name' => 'Agro',
        ]);

        $return = app(RecordSalesReturnService::class)->record(
            sale: $sale->fresh(), actor: $this->user, quantity: '10', reason: 'early',
        );
        $return = $this->runRevenueReversal($return);

        $this->assertSame(SalesReturn::STATUS_FAILED, $return->status);
        $this->assertSame('original_revenue_not_posted', $return->posting_error);
        $this->assertNull($return->revenue_reversal_batch_id);
    }
}
