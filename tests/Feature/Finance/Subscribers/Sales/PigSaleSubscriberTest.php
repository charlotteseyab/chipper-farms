<?php

namespace Tests\Feature\Finance\Subscribers\Sales;

use App\Domain\Events\Sales\PigSaleRecorded;
use App\Domain\Events\Sales\PigSalePaymentStatusChanged;
use App\Domain\Events\Support\SourceReference;
use App\Finance\Outbox\SubscriberRegistry;
use App\Finance\Subscribers\Sales\PigSaleRecordedSubscriber;
use App\Finance\Subscribers\Sales\PigSalePaymentReceivedSubscriber;
use App\Jobs\ProcessPendingDomainEventsJob;
use App\Models\Counterparty;
use App\Models\DomainEvent as DomainEventRecord;
use App\Models\Finance\Account;
use App\Models\Finance\Journal;
use App\Models\Finance\JournalBatch;
use App\Models\Finance\JournalLine;
use App\Models\Finance\JournalPosting;
use App\Models\PaymentMethod;
use App\Models\PigManagement;
use App\Models\PigSale;
use App\Models\Setting;
use App\Services\DomainEvents\DispatchDomainEvent;
use App\Support\CurrentOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Finance\FinanceTestSetup;
use Tests\TestCase;

/**
 * End-to-end integration test for Phase 1c.
 *
 * Exercises the complete pipeline:
 *   1. Set up a pig sale (via direct create, not the controller — we're
 *      testing the subscriber, not the sale flow).
 *   2. Dispatch a PigSaleRecorded domain event via the dispatcher.
 *   3. Run ProcessPendingDomainEventsJob.
 *   4. Assert the journal batch + journal + lines + posting record exist
 *      with correct amounts and accounts.
 *   5. Assert pig_sales.is_posted = true.
 *   6. Assert running the processor a second time produces NO duplicate.
 */
class PigSaleSubscriberTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFinanceFixtures();
        Setting::set('finance.use_invoices_for_pig_sales', false);
        Setting::set('sales.pricing.live_weight_price', 10.00);
        $this->seedAccountsForSubscriber();
        $this->registerSubscribers();
    }

    /**
     * Beyond the FinanceTestSetup defaults, we need the AR account
     * (1110) and a few payment-method-targeted cash accounts.
     */
    protected function seedAccountsForSubscriber(): void
    {
        // The fixtures already created '1010' (Cash), '4010' (Revenue),
        // '1110' (AR Pig Sales), '6010' (Vet). For payment_method
        // resolution we need the suspense receipt account too.
        Account::create([
            'organization_id'      => $this->org->id,
            'code'                 => '1910',
            'name'                 => 'Suspense — Receipts',
            'account_type'         => Account::TYPE_ASSET,
            'currency_code'        => 'GHS',
            'is_active'            => true,
            'allow_manual_journal' => false,
        ]);
    }

    protected function registerSubscribers(): void
    {
        /** @var SubscriberRegistry $registry */
        $registry = $this->app->make(SubscriberRegistry::class);
        $registry->reset();
        $registry->register('pig_sale.recorded', PigSaleRecordedSubscriber::class);
        $registry->register('pig_sale.payment_status_changed', PigSalePaymentReceivedSubscriber::class);
    }

    public function test_cash_pig_sale_posts_dr_cash_cr_revenue(): void
    {
        $sale = $this->makePigSale([
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'sale_weight' => 50.0,
            'price_per_kg' => 10.00,
            'sale_price' => 500.00,
        ]);
        $this->dispatchRecordedEvent($sale);

        $this->processEvents();

        $batch = JournalBatch::first();
        $this->assertNotNull($batch);
        $this->assertSame('posted', $batch->status);
        $this->assertSame('auto_pig_sale', $batch->batch_type);
        $this->assertSame(1, $batch->journals()->count());

        $journal = $batch->journals->first();
        $this->assertSame('500.00', (string) $journal->total_debit);
        $this->assertSame('500.00', (string) $journal->total_credit);
        $this->assertSame(2, $journal->lines->count());

        // Debit hits Cash (1010).
        $debitLine = $journal->lines->firstWhere('debit', '>', 0);
        $this->assertSame($this->cashAccount->id, $debitLine->account_id);
        $this->assertSame('500.00', (string) $debitLine->debit);

        // Credit hits Revenue (4010).
        $creditLine = $journal->lines->firstWhere('credit', '>', 0);
        $this->assertSame($this->revenueAccount->id, $creditLine->account_id);
        $this->assertSame('500.00', (string) $creditLine->credit);
    }

    public function test_credit_pig_sale_posts_dr_ar_cr_revenue(): void
    {
        $sale = $this->makePigSale([
            'payment_status' => 'pending',
            'sale_weight' => 75.0,
            'price_per_kg' => 10.00,
            'sale_price' => 750.00,
        ]);
        $this->dispatchRecordedEvent($sale);

        $this->processEvents();

        $journal = JournalBatch::first()->journals->first();

        $debitLine = $journal->lines->firstWhere('debit', '>', 0);
        $this->assertSame($this->arAccount->id, $debitLine->account_id);
        $this->assertSame('750.00', (string) $debitLine->debit);

        $creditLine = $journal->lines->firstWhere('credit', '>', 0);
        $this->assertSame($this->revenueAccount->id, $creditLine->account_id);
    }

    public function test_subscriber_marks_source_pig_sale_is_posted(): void
    {
        $sale = $this->makePigSale([
            'payment_status' => 'paid',
            'sale_weight' => 10.0,
            'price_per_kg' => 10.00,
            'sale_price' => 100.00,
        ]);
        $this->assertFalse((bool) $sale->is_posted);

        $this->dispatchRecordedEvent($sale);
        $this->processEvents();

        $sale->refresh();
        $this->assertTrue((bool) $sale->is_posted);
        $this->assertNotNull($sale->posted_at);
    }

    public function test_idempotent_re_processing_creates_no_duplicate_batch(): void
    {
        $sale = $this->makePigSale(['payment_status' => 'paid']);
        $this->dispatchRecordedEvent($sale);

        $this->processEvents();
        $countAfterFirst = JournalBatch::count();
        $this->assertSame(1, $countAfterFirst);

        // Force the event back to pending and re-run. Subscriber should detect
        // existing posting via the idempotency_key UNIQUE in journal_postings.
        DomainEventRecord::where('source_id', $sale->id)
            ->update(['status' => DomainEventRecord::STATUS_PENDING]);

        $this->processEvents();

        // No duplicate batch — the duplicate posting was caught and treated as success.
        $this->assertSame(1, JournalBatch::count());
        // Event marked processed again.
        $this->assertSame(
            DomainEventRecord::STATUS_PROCESSED,
            DomainEventRecord::where('source_id', $sale->id)->first()->status
        );
    }

    public function test_payment_received_posts_cash_receipt_clearing_ar(): void
    {
        // Step 1: credit sale.
        $sale = $this->makePigSale([
            'payment_status' => 'pending',
            'sale_weight' => 100.0,
            'price_per_kg' => 10.00,
            'sale_price' => 1000.00,
        ]);
        $this->dispatchRecordedEvent($sale);
        $this->processEvents();

        $this->assertSame(1, JournalBatch::count());
        $this->assertSame(0, JournalBatch::where('batch_type', 'auto_pig_sale_receipt')->count());

        // Step 2: payment is received. Update the sale and dispatch the transition event.
        $sale->update(['payment_status' => 'paid', 'payment_method' => 'cash']);
        $this->dispatchPaymentChangedEvent($sale, 'pending', 'paid');
        $this->processEvents();

        // Two batches now: original revenue+AR, and cash receipt clearing.
        $this->assertSame(2, JournalBatch::count());

        $receiptBatch = JournalBatch::where('batch_type', 'auto_pig_sale_receipt')->first();
        $this->assertNotNull($receiptBatch);

        $receipt = $receiptBatch->journals->first();
        $this->assertSame('cash_receipt', $receipt->journal_type);
        $this->assertSame('1000.00', (string) $receipt->total_debit);
        $this->assertSame('1000.00', (string) $receipt->total_credit);

        $debitLine  = $receipt->lines->firstWhere('debit', '>', 0);
        $creditLine = $receipt->lines->firstWhere('credit', '>', 0);

        $this->assertSame($this->cashAccount->id, $debitLine->account_id);
        $this->assertSame($this->arAccount->id, $creditLine->account_id);
    }

    public function test_paid_to_paid_transition_skipped(): void
    {
        $sale = $this->makePigSale([
            'payment_status' => 'paid',
            'sale_weight' => 10.0,
            'price_per_kg' => 10.00,
            'sale_price' => 100.00,
        ]);
        $this->dispatchRecordedEvent($sale);
        $this->processEvents();

        $beforeCount = JournalBatch::count();

        // Spurious "paid -> paid" event (e.g. user re-saved the sale form
        // without changing anything).
        $this->dispatchPaymentChangedEvent($sale, 'paid', 'paid');
        $this->processEvents();

        $afterCount = JournalBatch::count();
        $this->assertSame($beforeCount, $afterCount, 'No new batch should be created for paid→paid');

        // The event should be marked processed (since it was handled — just skipped).
        $event = DomainEventRecord::where('event_type', 'pig_sale.payment_status_changed')->latest()->first();
        $this->assertContains($event->status, [
            DomainEventRecord::STATUS_PROCESSED,
            DomainEventRecord::STATUS_SKIPPED,
        ]);
    }

    public function test_partial_to_paid_skipped_for_phase_2(): void
    {
        $sale = $this->makePigSale([
            'payment_status' => 'partial',
            'sale_weight' => 20.0,
            'price_per_kg' => 10.00,
            'sale_price' => 200.00,
        ]);
        // No initial revenue posting — partial isn't recorded yet.

        $this->dispatchPaymentChangedEvent($sale, 'partial', 'paid');
        $this->processEvents();

        // No receipt batch.
        $this->assertSame(0, JournalBatch::where('batch_type', 'auto_pig_sale_receipt')->count());
    }

    /* ---- helpers ---- */

    protected function makePigSale(array $overrides = []): PigSale
    {
        $pig = PigManagement::create([
            'ear_tag_number' => 'T-' . uniqid(),
            'code'           => 'PIG-' . uniqid(),
            'breed'          => 'LW',
            'gender'         => 'M',
            'stock_type'     => 'standard',
            'date_of_birth'  => now()->subMonths(8)->toDateString(),
        ]);

        return PigSale::create(array_merge([
            'pig_id'          => $pig->id,
            'sale_date'       => '2026-06-15',
            'sale_price'      => 500.00,
            'sale_weight'     => 50.0,
            'weight_type'     => 'live',
            'price_per_kg'    => 10.00,
            'sale_purpose'    => 'market',
            'payment_method'  => 'cash',
            'payment_status'  => 'paid',
            'sold_by_user_id' => $this->user->id,
            'sold_by_name'    => $this->user->name,
            'organization_id' => $this->org->id,
            'document_number' => 'INV-2026-' . uniqid(),
            'is_posted'       => false,
        ], $overrides));
    }

    protected function dispatchRecordedEvent(PigSale $sale): void
    {
        app(DispatchDomainEvent::class)->dispatch(new PigSaleRecorded(
            source: new SourceReference(
                module:         'sales',
                sourceType:     PigSale::class,
                sourceId:       $sale->id,
                organizationId: $this->org->id,
            ),
            occurredAt: CarbonImmutable::parse('2026-06-15T10:00:00'),
            dispatchedByUserId: $this->user->id,
            pigId:          $sale->pig_id,
            pigCode:        'PIG-' . $sale->pig_id,
            saleWeight:     (float) $sale->sale_weight,
            weightType:     (string) $sale->weight_type,
            pricePerKg:     (float) $sale->price_per_kg,
            saleTotal:      (float) $sale->sale_price,
            salePurpose:    (string) $sale->sale_purpose,
            paymentStatus:  (string) $sale->payment_status,
            paymentMethod:  $sale->payment_method,
            buyerName:      $sale->buyer_name,
            transportCost:  $sale->transport_cost,
        ));
    }

    protected function dispatchPaymentChangedEvent(PigSale $sale, string $from, string $to): void
    {
        app(DispatchDomainEvent::class)->dispatch(new PigSalePaymentStatusChanged(
            source: new SourceReference(
                module:         'sales',
                sourceType:     PigSale::class,
                sourceId:       $sale->id,
                organizationId: $this->org->id,
            ),
            occurredAt: CarbonImmutable::now(),
            dispatchedByUserId: $this->user->id,
            fromStatus: $from,
            toStatus:   $to,
            saleTotal:  (float) $sale->sale_price,
        ));
    }

    protected function processEvents(): void
    {
        ProcessPendingDomainEventsJob::dispatchSync(50);
    }
}
