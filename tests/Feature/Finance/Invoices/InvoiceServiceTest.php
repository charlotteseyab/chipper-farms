<?php

namespace Tests\Feature\Finance\Invoices;

use App\Models\Counterparty;
use App\Models\Finance\Invoice;
use App\Services\Finance\Invoicing\InvoiceLineDraft;
use App\Services\Finance\Invoicing\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Finance\FinanceTestSetup;
use Tests\TestCase;

class InvoiceServiceTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    protected Counterparty $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFinanceFixtures();

        $this->customer = Counterparty::create([
            'organization_id' => $this->org->id,
            'name'            => 'Test Customer',
            'type'            => 'customer',
            'code'            => 'CUST-TEST',
            'currency_code'   => 'GHS',
            'payment_terms_days' => 30,
        ]);
    }

    public function test_create_draft_with_one_line(): void
    {
        $service = $this->app->make(InvoiceService::class);

        $invoice = $service->createDraft(
            customer: $this->customer,
            headerData: [
                'invoice_date' => '2026-05-15',
                'currency_code' => 'GHS',
            ],
            lineDrafts: [
                new InvoiceLineDraft(description: 'Pig', quantity: '50', unitPrice: '12.50'),
            ],
        );

        $this->assertSame(Invoice::STATUS_DRAFT, $invoice->status);
        $this->assertSame('625.00', $invoice->subtotal);
        $this->assertSame('625.00', $invoice->total);
        $this->assertSame(1, $invoice->lines->count());
        $this->assertNotEmpty($invoice->invoice_number);
        $this->assertStringStartsWith('INV-2026-', $invoice->invoice_number);
    }

    public function test_due_date_defaults_to_invoice_date_plus_terms(): void
    {
        $service = $this->app->make(InvoiceService::class);

        $invoice = $service->createDraft(
            customer: $this->customer,
            headerData: ['invoice_date' => '2026-05-15', 'currency_code' => 'GHS'],
            lineDrafts: [
                new InvoiceLineDraft(description: 'Pig', quantity: '1', unitPrice: '100'),
            ],
        );

        // Customer has payment_terms_days = 30
        $this->assertSame('2026-06-14', $invoice->due_date->toDateString());
    }

    public function test_explicit_due_date_respected(): void
    {
        $service = $this->app->make(InvoiceService::class);

        $invoice = $service->createDraft(
            customer: $this->customer,
            headerData: ['invoice_date' => '2026-05-15', 'due_date' => '2026-12-31', 'currency_code' => 'GHS'],
            lineDrafts: [new InvoiceLineDraft(description: 'X', quantity: '1', unitPrice: '100')],
        );

        $this->assertSame('2026-12-31', $invoice->due_date->toDateString());
    }

    public function test_create_with_multiple_lines_aggregates_header(): void
    {
        $service = $this->app->make(InvoiceService::class);

        $invoice = $service->createDraft(
            customer: $this->customer,
            headerData: ['invoice_date' => '2026-05-15', 'currency_code' => 'GHS'],
            lineDrafts: [
                new InvoiceLineDraft(description: 'A', quantity: '1', unitPrice: '100', taxRatePercent: '15'),
                new InvoiceLineDraft(description: 'B', quantity: '2', unitPrice: '50',  discountPercent: '10'),
            ],
        );

        $this->assertSame('200.00', $invoice->subtotal);    // 100 + 100
        $this->assertSame('10.00',  $invoice->discount_total);  // 10% of 100 from line B
        $this->assertSame('15.00',  $invoice->tax_total);   // 15% of 100 from line A
        $this->assertSame('205.00', $invoice->total);       // 200 - 10 + 15
    }

    public function test_zero_lines_rejected(): void
    {
        $service = $this->app->make(InvoiceService::class);

        $this->expectException(\InvalidArgumentException::class);
        $service->createDraft(
            customer: $this->customer,
            headerData: ['invoice_date' => '2026-05-15', 'currency_code' => 'GHS'],
            lineDrafts: [],
        );
    }

    public function test_cancel_draft(): void
    {
        $service = $this->app->make(InvoiceService::class);
        $invoice = $service->createDraft(
            customer: $this->customer,
            headerData: ['invoice_date' => '2026-05-15', 'currency_code' => 'GHS'],
            lineDrafts: [new InvoiceLineDraft(description: 'X', quantity: '1', unitPrice: '100')],
        );

        $cancelled = $service->cancel($invoice, $this->user, 'Wrong customer');

        $this->assertSame(Invoice::STATUS_CANCELLED, $cancelled->status);
        $this->assertNotNull($cancelled->cancelled_at);
        $this->assertSame('Wrong customer', $cancelled->cancellation_reason);
    }

    public function test_cancel_rejects_posted_invoice(): void
    {
        // Manually mark as posted to test the guard.
        $service = $this->app->make(InvoiceService::class);
        $invoice = $service->createDraft(
            customer: $this->customer,
            headerData: ['invoice_date' => '2026-05-15', 'currency_code' => 'GHS'],
            lineDrafts: [new InvoiceLineDraft(description: 'X', quantity: '1', unitPrice: '100')],
        );
        $invoice->update(['status' => Invoice::STATUS_POSTED]);

        $this->expectException(\DomainException::class);
        $service->cancel($invoice, $this->user, 'too late');
    }

    public function test_add_line_to_draft(): void
    {
        $service = $this->app->make(InvoiceService::class);
        $invoice = $service->createDraft(
            customer: $this->customer,
            headerData: ['invoice_date' => '2026-05-15', 'currency_code' => 'GHS'],
            lineDrafts: [new InvoiceLineDraft(description: 'A', quantity: '1', unitPrice: '100')],
        );

        $service->addLine($invoice, new InvoiceLineDraft(description: 'B', quantity: '1', unitPrice: '50'));

        $invoice->refresh();
        $this->assertSame(2, $invoice->lines->count());
        $this->assertSame('150.00', $invoice->subtotal);
    }

    public function test_cannot_remove_last_line(): void
    {
        $service = $this->app->make(InvoiceService::class);
        $invoice = $service->createDraft(
            customer: $this->customer,
            headerData: ['invoice_date' => '2026-05-15', 'currency_code' => 'GHS'],
            lineDrafts: [new InvoiceLineDraft(description: 'A', quantity: '1', unitPrice: '100')],
        );

        $line = $invoice->lines->first();

        $this->expectException(\DomainException::class);
        $service->removeLine($line);
    }

    public function test_address_snapshot_captured_at_creation(): void
    {
        $this->customer->update([
            'address_line_1' => '123 Test Road',
            'city' => 'Accra',
            'country' => 'GH',
        ]);

        $service = $this->app->make(InvoiceService::class);
        $invoice = $service->createDraft(
            customer: $this->customer->fresh(),
            headerData: ['invoice_date' => '2026-05-15', 'currency_code' => 'GHS'],
            lineDrafts: [new InvoiceLineDraft(description: 'X', quantity: '1', unitPrice: '100')],
        );

        $this->assertStringContainsString('123 Test Road', $invoice->billing_address_snapshot);
    }
}
