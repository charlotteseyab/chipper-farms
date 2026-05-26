<?php

namespace Tests\Feature\Finance\Invoices;

use App\Finance\Subscribers\Sales\PigSaleInvoicingSubscriber;
use App\Models\Counterparty;
use App\Models\DomainEvent;
use App\Models\Finance\Account;
use App\Models\Finance\Invoice;
use App\Models\PaymentMethod;
use App\Models\PigManagement;
use App\Models\PigSale;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Finance\FinanceTestSetup;
use Tests\TestCase;

/**
 * End-to-end: dispatch a pig_sale.recorded event with the invoice flag
 * enabled, verify the new subscriber creates an invoice, posts it to GL,
 * and links pig_sales.invoice_id back to the new invoice.
 *
 * Critical scenarios:
 *   1. Flag ON, invoice_id NULL  → new subscriber creates + posts invoice
 *   2. Flag ON, invoice_id SET   → subscriber treats as already-handled
 *   3. Flag OFF                  → subscriber returns early; legacy path acts
 *   4. Re-delivered event        → idempotent; no double posting
 */
class PigSaleInvoicingSubscriberTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    protected Counterparty $customer;
    protected PaymentMethod $cashMethod;
    protected PigManagement $pig;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFinanceFixtures();

        // Required accounts for invoice posting.
        Account::create([
            'organization_id' => $this->org->id,
            'code' => '2210', 'name' => 'VAT Control',
            'account_type' => 'liability', 'currency_code' => 'GHS',
            'is_active' => true, 'allow_manual_journal' => false,
        ]);
        Account::create([
            'organization_id' => $this->org->id,
            'code' => '1910', 'name' => 'Suspense - Receipts',
            'account_type' => 'asset', 'currency_code' => 'GHS',
            'is_active' => true, 'allow_manual_journal' => false,
        ]);

        $this->customer = Counterparty::create([
            'organization_id' => $this->org->id,
            'name' => 'Test Customer',
            'code' => 'CUST-T',
            'type' => 'customer',
            'currency_code' => 'GHS',
        ]);

        $this->cashMethod = PaymentMethod::create([
            'organization_id' => $this->org->id,
            'code' => 'CASH',
            'name' => 'Cash',
            'is_cash' => true,
            'gl_account_hint' => 'CASH_ON_HAND',
            'is_active' => true,
        ]);

        $this->pig = PigManagement::create([
            'organization_id' => $this->org->id,
            'ear_tag_number' => 'EAR-001',
            'sex' => 'male',
            'birth_date' => '2025-12-01',
        ]);
    }

    public function test_flag_on_creates_invoice_and_posts(): void
    {
        Setting::set('finance.use_invoices_for_pig_sales', true);

        $sale = $this->makePigSale();
        $event = $this->dispatchEvent($sale);

        $this->app->make(PigSaleInvoicingSubscriber::class)->handle($event->id);

        $sale->refresh();
        $this->assertNotNull($sale->invoice_id, 'pig_sales.invoice_id should be set');
        $this->assertTrue((bool) $sale->is_posted);

        $invoice = Invoice::find($sale->invoice_id);
        $this->assertNotNull($invoice);
        $this->assertSame(Invoice::STATUS_POSTED, $invoice->status);
        $this->assertNotNull($invoice->journal_batch_id, 'invoice should have journal batch');
        $this->assertSame($sale->id, (int) $invoice->source_id);
        $this->assertSame(PigSale::class, $invoice->source_type);

        // Event should be marked processed.
        $event->refresh();
        $this->assertSame(DomainEvent::STATUS_PROCESSED, $event->status);
    }

    public function test_flag_off_subscriber_returns_silently(): void
    {
        Setting::set('finance.use_invoices_for_pig_sales', false);

        $sale = $this->makePigSale();
        $event = $this->dispatchEvent($sale);

        $this->app->make(PigSaleInvoicingSubscriber::class)->handle($event->id);

        $sale->refresh();
        $this->assertNull($sale->invoice_id, 'No invoice should be created when flag is off');
        $this->assertSame(0, Invoice::count());

        // Event should still be pending — legacy subscriber would handle it.
        $event->refresh();
        $this->assertSame(DomainEvent::STATUS_PENDING, $event->status);
    }

    public function test_re_delivered_event_does_not_double_post(): void
    {
        Setting::set('finance.use_invoices_for_pig_sales', true);

        $sale = $this->makePigSale();
        $event = $this->dispatchEvent($sale);

        // First delivery — creates invoice.
        $this->app->make(PigSaleInvoicingSubscriber::class)->handle($event->id);
        $sale->refresh();
        $firstInvoiceId = $sale->invoice_id;
        $this->assertNotNull($firstInvoiceId);
        $this->assertSame(1, Invoice::count());

        // Reset event status to simulate re-delivery (e.g. job retry).
        $event->update(['status' => DomainEvent::STATUS_PENDING]);

        $this->app->make(PigSaleInvoicingSubscriber::class)->handle($event->id);

        $sale->refresh();
        $this->assertSame($firstInvoiceId, $sale->invoice_id, 'Invoice should be the same — not recreated');
        $this->assertSame(1, Invoice::count(), 'Only one invoice should exist');

        $event->refresh();
        $this->assertSame(DomainEvent::STATUS_PROCESSED, $event->status);
    }

    public function test_cash_payment_method_produces_cash_invoice(): void
    {
        Setting::set('finance.use_invoices_for_pig_sales', true);

        $sale = $this->makePigSale(['payment_method_id' => $this->cashMethod->id]);
        $event = $this->dispatchEvent($sale);

        $this->app->make(PigSaleInvoicingSubscriber::class)->handle($event->id);

        $sale->refresh();
        $invoice = Invoice::find($sale->invoice_id);
        $this->assertTrue($invoice->is_cash_invoice);

        // Debit should hit cash (1010), not AR (1110).
        $journal = $invoice->journalBatch->journals->first();
        $debitLine = $journal->lines->firstWhere('debit', '>', 0);
        $this->assertSame($this->cashAccount->id, $debitLine->account_id);
    }

    private function makePigSale(array $overrides = []): PigSale
    {
        return PigSale::create(array_merge([
            'organization_id' => $this->org->id,
            'pig_id'          => $this->pig->id,
            'customer_id'     => $this->customer->id,
            'sale_date'       => '2026-05-15',
            'sale_weight'     => 50.0,
            'price_per_kg'    => 12.50,
            'sale_price'      => 625.00,
            'weight_type'     => 'live',
            'document_number' => 'SALE-001',
            'is_posted'       => false,
        ], $overrides));
    }

    private function dispatchEvent(PigSale $sale): DomainEvent
    {
        return DomainEvent::create([
            'organization_id'    => $this->org->id,
            'event_type'         => 'pig_sale.recorded',
            'source_type'        => PigSale::class,
            'source_id'          => $sale->id,
            'payload'            => [
                'pig_sale_id'      => $sale->id,
                'sale_price'       => (string) $sale->sale_price,
                'sale_date'        => $sale->sale_date->toDateString(),
                'customer_id'      => $sale->customer_id,
                'payment_method_id'=> $sale->payment_method_id,
            ],
            'idempotency_key'    => 'test-evt-' . $sale->id,
            'status'             => DomainEvent::STATUS_PENDING,
            'dispatched_by_user_id' => $this->user->id,
        ]);
    }
}
