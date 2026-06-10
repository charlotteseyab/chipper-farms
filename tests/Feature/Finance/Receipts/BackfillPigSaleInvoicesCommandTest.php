<?php

namespace Tests\Feature\Finance\Receipts;

use App\Models\Counterparty;
use App\Models\Finance\Account;
use App\Models\Finance\Invoice;
use App\Models\Finance\JournalBatch;
use App\Models\Finance\JournalPosting;
use App\Models\Finance\Receipt;
use App\Models\Finance\ReceiptAllocation;
use App\Models\PaymentMethod;
use App\Models\PigManagement;
use App\Models\PigSale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Finance\FinanceTestSetup;
use Tests\TestCase;

/**
 * BackfillPigSaleInvoicesCommand: synthesise invoice records for
 * historical pig sales WITHOUT producing new GL postings.
 */
class BackfillPigSaleInvoicesCommandTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    protected Counterparty $customer;
    protected PaymentMethod $cashMethod;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFinanceFixtures();

        if (! Account::where('organization_id', $this->org->id)->where('code', '1910')->exists()) {
            Account::create([
                'organization_id' => $this->org->id, 'code' => '1910',
                'name' => 'Suspense - Receipts', 'account_type' => Account::TYPE_ASSET,
                'currency_code' => 'GHS', 'is_active' => true, 'allow_manual_journal' => false,
            ]);
        }

        $this->customer = Counterparty::create([
            'organization_id' => $this->org->id,
            'name' => 'Acme', 'code' => 'CUST-A',
            'type' => 'customer', 'currency_code' => 'GHS',
        ]);
        $this->cashMethod = PaymentMethod::create([
            'organization_id' => $this->org->id,
            'code' => 'CASH', 'name' => 'Cash',
            'is_cash' => true, 'gl_account_hint' => 'CASH_ON_HAND', 'is_active' => true,
        ]);
    }

    public function test_dry_run_changes_nothing(): void
    {
        $sale = $this->makePostedLegacySale(paymentStatus: 'paid');

        $this->artisan('finance:backfill-pig-sale-invoices', [
            '--dry-run' => true,
            '--user'    => $this->user->id,
        ])->assertSuccessful();

        $sale->refresh();
        $this->assertNull($sale->invoice_id, 'Dry run must not link invoice');
        $this->assertSame(0, Invoice::count());
        $this->assertSame(0, Receipt::count());
    }

    public function test_paid_sale_creates_invoice_receipt_and_allocation(): void
    {
        $sale = $this->makePostedLegacySale(paymentStatus: 'paid');
        $journalBatch = $this->createLegacyJournalBatchForSale($sale);

        $this->artisan('finance:backfill-pig-sale-invoices', [
            '--user' => $this->user->id,
        ])->assertSuccessful();

        $sale->refresh();
        $this->assertNotNull($sale->invoice_id);

        $invoice = Invoice::find($sale->invoice_id);
        $this->assertNotNull($invoice);
        $this->assertSame(Invoice::STATUS_POSTED, $invoice->status);
        $this->assertSame((int) $journalBatch->id, (int) $invoice->journal_batch_id, 'Invoice must link to existing journal batch');
        $this->assertSame(PigSale::class, $invoice->source_type);
        $this->assertSame((int) $sale->id, (int) $invoice->source_id);
        $this->assertSame((string) (float) $sale->sale_price, (string) (float) $invoice->total);

        // For paid sales: receipt + allocation should also exist.
        $receipt = Receipt::where('counterparty_id', $this->customer->id)->first();
        $this->assertNotNull($receipt);
        $this->assertTrue((bool) $receipt->is_backfilled);
        $this->assertNull($receipt->journal_batch_id, 'Backfilled receipt must NOT have journal batch');

        $alloc = ReceiptAllocation::where('invoice_id', $invoice->id)->first();
        $this->assertNotNull($alloc);
        $this->assertSame((string) (float) $invoice->total, (string) (float) $alloc->amount);
        $this->assertSame(ReceiptAllocation::STATUS_ACTIVE, $alloc->status);

        $invoice->refresh();
        $this->assertSame('0.00', $invoice->balance_due);
        $this->assertNotNull($invoice->fully_paid_at);
    }

    public function test_partial_sale_creates_invoice_but_no_receipt(): void
    {
        $sale = $this->makePostedLegacySale(paymentStatus: 'partial');
        $this->createLegacyJournalBatchForSale($sale);

        $this->artisan('finance:backfill-pig-sale-invoices', [
            '--user' => $this->user->id,
        ])->assertSuccessful();

        $sale->refresh();
        $this->assertNotNull($sale->invoice_id);

        $invoice = Invoice::find($sale->invoice_id);
        $this->assertSame((string) (float) $sale->sale_price, (string) (float) $invoice->balance_due,
            'Partial sale: balance_due = total (legacy didn\'t track partial amount)');
        $this->assertSame('0.00', $invoice->amount_paid);

        $this->assertSame(0, Receipt::count(), 'No receipt should be created for partial sales');
    }

    public function test_pending_sale_creates_invoice_only(): void
    {
        $sale = $this->makePostedLegacySale(paymentStatus: 'pending');
        $this->createLegacyJournalBatchForSale($sale);

        $this->artisan('finance:backfill-pig-sale-invoices', [
            '--user' => $this->user->id,
        ])->assertSuccessful();

        $invoice = Invoice::where('source_id', $sale->id)->first();
        $this->assertNotNull($invoice);
        $this->assertSame((string) (float) $sale->sale_price, (string) (float) $invoice->balance_due);

        $this->assertSame(0, Receipt::count());
    }

    public function test_rerun_is_idempotent(): void
    {
        $sale = $this->makePostedLegacySale(paymentStatus: 'paid');
        $this->createLegacyJournalBatchForSale($sale);

        $this->artisan('finance:backfill-pig-sale-invoices', ['--user' => $this->user->id])->assertSuccessful();
        $invoiceCountAfterFirst = Invoice::count();
        $receiptCountAfterFirst = Receipt::count();

        // Re-run.
        $this->artisan('finance:backfill-pig-sale-invoices', ['--user' => $this->user->id])->assertSuccessful();

        $this->assertSame($invoiceCountAfterFirst, Invoice::count(), 'No new invoices created on re-run');
        $this->assertSame($receiptCountAfterFirst, Receipt::count(), 'No new receipts created on re-run');
    }

    public function test_skips_unposted_sales(): void
    {
        $sale = $this->makePostedLegacySale(paymentStatus: 'paid');
        $sale->update(['is_posted' => false]);

        $this->artisan('finance:backfill-pig-sale-invoices', ['--user' => $this->user->id])->assertSuccessful();

        $sale->refresh();
        $this->assertNull($sale->invoice_id);
        $this->assertSame(0, Invoice::count());
    }

    /* helpers */

    protected function makePostedLegacySale(string $paymentStatus = 'pending'): PigSale
    {
        static $counter = 0;
        $pig = PigManagement::create([
            'organization_id' => $this->org->id,
            'ear_tag_number'  => 'EAR-' . (++$counter),
            'gender'          => 'male',
            'date_of_birth'   => '2025-12-01',
        ]);

        return PigSale::create([
            'organization_id'              => $this->org->id,
            'pig_id'                       => $pig->id,
            'customer_id'                  => $this->customer->id,
            'sale_date'                    => '2026-04-15',
            'sale_weight'                  => 50.0,
            'price_per_kg'                 => 12.50,
            'sale_price'                   => 625.00,
            'weight_type'                  => 'live',
            'document_number'              => 'SALE-' . $counter,
            'payment_status'               => $paymentStatus,
            'payment_method_id'            => null,  // credit sale by default
            'is_posted'                    => true,
            'payment_status_updated_at'    => $paymentStatus === 'paid' ? '2026-04-20 10:00:00' : null,
        ]);
    }

    protected function createLegacyJournalBatchForSale(PigSale $sale): JournalBatch
    {
        // Mimic what the Phase 1c subscriber would have written.
        $batch = JournalBatch::create([
            'organization_id'     => $this->org->id,
            'fiscal_period_id'    => $this->period->id,
            'batch_number'        => 'JB-LEG-' . $sale->id,
            'batch_type'          => 'auto_pig_sale',
            'posting_date'        => $sale->sale_date,
            'description'         => 'Legacy pig sale ' . $sale->id,
            'source_module'       => 'pig_sales',
            'source_type'         => PigSale::class,
            'source_id'           => $sale->id,
            'total_debit'         => $sale->sale_price,
            'total_credit'        => $sale->sale_price,
            'status'              => 'posted',
            'created_by_user_id'  => $this->user->id,
            'posted_by_user_id'   => $this->user->id,
            'posted_at'           => now(),
        ]);

        // Stub journal_postings row so findExistingJournalBatchId works.
        JournalPosting::create([
            'organization_id'   => $this->org->id,
            'journal_batch_id'  => $batch->id,
            'posting_purpose'   => JournalPosting::PURPOSE_REVENUE,
            'idempotency_key'   => JournalPosting::buildIdempotencyKey(
                'pig_sale.recorded',
                PigSale::class,
                $sale->id,
                JournalPosting::PURPOSE_REVENUE,
            ),
            'subscriber_class'  => 'App\\Finance\\Subscribers\\Sales\\PigSaleRecordedSubscriber',
            'posted_at'         => now(),
        ]);

        return $batch;
    }
}
