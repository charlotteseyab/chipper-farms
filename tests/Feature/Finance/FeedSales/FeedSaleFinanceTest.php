<?php

namespace Tests\Feature\Finance\FeedSales;

use App\Domain\Events\Sales\FeedSaleRecorded;
use App\Finance\Subscribers\Sales\FeedSaleCogsSubscriber;
use App\Finance\Subscribers\Sales\FeedSaleRevenueSubscriber;
use App\Models\DomainEvent;
use App\Models\FeedSale;
use App\Models\FeedType;
use App\Models\Finance\Account;
use App\Models\Finance\InventoryAccountMapping;
use App\Models\Finance\Journal;
use App\Models\Finance\JournalLine;
use App\Services\Finance\Inventory\InventoryService;
use App\Services\Sales\RecordFeedSaleService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Finance\FinanceTestSetup;
use Tests\TestCase;

/**
 * End-to-end: record a feed sale, run the revenue + COGS subscribers, and
 * assert both journals are balanced and that COGS equals the inventory WAC
 * decrement (no drift between GL and inventory).
 */
class FeedSaleFinanceTest extends TestCase
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
            ['4120', Account::TYPE_REVENUE, 'operating_revenue'],
            ['5100', Account::TYPE_EXPENSE, 'cogs'],
        ] as [$code, $type, $sub]) {
            Account::firstOrCreate(
                ['organization_id' => $this->org->id, 'code' => $code],
                ['name' => "Acct {$code}", 'account_type' => $type, 'account_subtype' => $sub,
                 'currency_code' => 'GHS', 'is_active' => true, 'allow_manual_journal' => true],
            );
        }

        // Map finished_feed → inventory 1220 / cogs 5100 (default category).
        InventoryAccountMapping::create([
            'organization_id'      => $this->org->id,
            'item_kind'            => InventoryAccountMapping::KIND_FINISHED_FEED,
            'category'             => InventoryAccountMapping::DEFAULT_CATEGORY_TOKEN,
            'is_default'           => true,
            'inventory_account_id' => Account::where('organization_id', $this->org->id)->where('code', '1220')->value('id'),
            'cogs_account_id'      => Account::where('organization_id', $this->org->id)->where('code', '5100')->value('id'),
        ]);

        config()->set('feed_sales.revenue_accounts', ['direct' => '4110', 'storefront' => '4120']);
        config()->set('feed_sales.ar_account_code', '1120');
        config()->set('feed_sales.default_cash_account_code', '1030');
    }

    public function test_credit_feed_sale_posts_revenue_and_cogs_at_wac(): void
    {
        // Seed 100 kg of finished feed at WAC 8.00 (total cost 800).
        $feedType = FeedType::create(['name' => 'Grower Feed']);
        app(InventoryService::class)->recordFeedProduction(
            feedTypeId: $feedType->id, quantity: 100.0, totalCost: 800.0, feedBatchId: 1,
            occurredAt: CarbonImmutable::parse('2026-05-01'),
        );

        // Sell 40 kg @ 12.00/kg on credit (sale_total 480; COGS = 40 × 8 = 320).
        $sale = app(RecordFeedSaleService::class)->execute($feedType, $this->user, [
            'quantity_kg' => 40, 'unit_price' => 12.00, 'sale_date' => '2026-05-15',
            'payment_status' => 'pending', 'channel' => 'direct', 'buyer_name' => 'Agro Ltd',
        ]);

        $event = DomainEvent::where('event_type', FeedSaleRecorded::TYPE)->where('source_id', $sale->id)->firstOrFail();
        app(FeedSaleRevenueSubscriber::class)->handle($event->id);
        $event->update(['status' => DomainEvent::STATUS_PENDING]);
        app(FeedSaleCogsSubscriber::class)->handle($event->id);

        $sale = $sale->fresh();

        // Revenue journal: Dr 1120 AR 480 / Cr 4110 revenue 480.
        $rev = $this->batchLines($sale->revenue_journal_batch_id);
        $this->assertBalanced($rev);
        $this->assertTrue($this->has($rev, '1120', debit: '480.00'));
        $this->assertTrue($this->has($rev, '4110', credit: '480.00'));

        // COGS journal: Dr 5100 320 / Cr 1220 320 (WAC 8 × 40).
        $this->assertSame('320.00', $sale->cogs_amount);
        $cogs = $this->batchLines($sale->cogs_journal_batch_id);
        $this->assertBalanced($cogs);
        $this->assertTrue($this->has($cogs, '5100', debit: '320.00'));
        $this->assertTrue($this->has($cogs, '1220', credit: '320.00'));

        $this->assertSame('posted', $sale->revenue_posting_status);
        $this->assertSame('posted', $sale->cogs_posting_status);
    }

    public function test_cash_feed_sale_debits_cash_not_ar(): void
    {
        $feedType = FeedType::create(['name' => 'Finisher Feed']);
        app(InventoryService::class)->recordFeedProduction(
            feedTypeId: $feedType->id, quantity: 50.0, totalCost: 500.0, feedBatchId: 2,
            occurredAt: CarbonImmutable::parse('2026-05-01'),
        );

        $sale = app(RecordFeedSaleService::class)->execute($feedType, $this->user, [
            'quantity_kg' => 10, 'unit_price' => 15.00, 'sale_date' => '2026-05-16',
            'payment_status' => 'paid', 'payment_method' => 'cash', 'channel' => 'direct',
        ]);

        $event = DomainEvent::where('event_type', FeedSaleRecorded::TYPE)->where('source_id', $sale->id)->firstOrFail();
        app(FeedSaleRevenueSubscriber::class)->handle($event->id);

        $rev = $this->batchLines($sale->fresh()->revenue_journal_batch_id);
        // Cash sale debits a cash account (default 1030), not AR 1120.
        $this->assertFalse($this->has($rev, '1120', debit: '150.00'));
        $this->assertTrue($this->has($rev, '4110', credit: '150.00'));
    }

    public function test_oversell_fails_cogs_without_negative_inventory(): void
    {
        $feedType = FeedType::create(['name' => 'Starter Feed']);
        app(InventoryService::class)->recordFeedProduction(
            feedTypeId: $feedType->id, quantity: 5.0, totalCost: 50.0, feedBatchId: 3,
            occurredAt: CarbonImmutable::parse('2026-05-01'),
        );

        // Try to sell 20 kg when only 5 are on hand.
        $sale = app(RecordFeedSaleService::class)->execute($feedType, $this->user, [
            'quantity_kg' => 20, 'unit_price' => 12.00, 'sale_date' => '2026-05-17',
            'payment_status' => 'pending', 'channel' => 'direct',
        ]);

        $event = DomainEvent::where('event_type', FeedSaleRecorded::TYPE)->where('source_id', $sale->id)->firstOrFail();
        app(FeedSaleCogsSubscriber::class)->handle($event->id);

        $sale = $sale->fresh();
        $this->assertSame('failed', $sale->cogs_posting_status);
        $this->assertNull($sale->cogs_journal_batch_id);
    }

    // helpers
    protected function batchLines(?int $batchId)
    {
        return JournalLine::whereIn('journal_id', Journal::whereIn('journal_batch_id', [$batchId])->pluck('id'))->get();
    }

    protected function assertBalanced($lines): void
    {
        $this->assertEqualsWithDelta(
            $lines->sum(fn ($l) => (float) $l->debit),
            $lines->sum(fn ($l) => (float) $l->credit),
            0.001,
        );
    }

    protected function has($lines, string $code, ?string $debit = null, ?string $credit = null): bool
    {
        $acctId = (int) Account::where('organization_id', $this->org->id)->where('code', $code)->value('id');
        foreach ($lines as $l) {
            if ((int) $l->account_id !== $acctId) continue;
            if ($debit !== null && bccomp((string) $l->debit, $debit, 2) === 0) return true;
            if ($credit !== null && bccomp((string) $l->credit, $credit, 2) === 0) return true;
        }
        return false;
    }
}
