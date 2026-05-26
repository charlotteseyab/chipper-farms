<?php

namespace Tests\Feature\Finance\Receipts;

use App\Models\Counterparty;
use App\Models\Finance\Invoice;
use App\Models\Finance\Receipt;
use App\Services\Finance\Allocations\CustomerLedgerService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Finance\FinanceTestSetup;
use Tests\TestCase;

class CustomerLedgerServiceTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    protected Counterparty $customer;
    protected CustomerLedgerService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFinanceFixtures();

        $this->customer = Counterparty::create([
            'organization_id' => $this->org->id,
            'name' => 'Acme', 'code' => 'CUST-A',
            'type' => 'customer', 'currency_code' => 'GHS',
        ]);

        $this->service = $this->app->make(CustomerLedgerService::class);
    }

    public function test_chronological_order_with_running_balance(): void
    {
        // Day 1: invoice for 500
        $this->makeInvoice('500.00', invoiceDate: '2026-05-01', invoiceNumber: 'INV-2026-000001');
        // Day 5: receipt for 200
        $this->makeReceipt('200.00', receiptDate: '2026-05-05', receiptNumber: 'RCT-2026-000001');
        // Day 10: invoice for 300
        $this->makeInvoice('300.00', invoiceDate: '2026-05-10', invoiceNumber: 'INV-2026-000002');
        // Day 15: receipt for 600
        $this->makeReceipt('600.00', receiptDate: '2026-05-15', receiptNumber: 'RCT-2026-000002');

        $entries = $this->service->build($this->customer);

        $this->assertCount(4, $entries);

        $this->assertSame('invoice',  $entries[0]->type);
        $this->assertSame('500.00',   $entries[0]->runningBalance());

        $this->assertSame('receipt',  $entries[1]->type);
        $this->assertSame('300.00',   $entries[1]->runningBalance());

        $this->assertSame('invoice',  $entries[2]->type);
        $this->assertSame('600.00',   $entries[2]->runningBalance());

        $this->assertSame('receipt',  $entries[3]->type);
        $this->assertSame('0.00',     $entries[3]->runningBalance());
    }

    public function test_invoice_before_receipt_on_same_date(): void
    {
        $this->makeInvoice('500.00', invoiceDate: '2026-05-15', invoiceNumber: 'INV-1');
        $this->makeReceipt('500.00', receiptDate: '2026-05-15', receiptNumber: 'RCT-1');

        $entries = $this->service->build($this->customer);

        $this->assertCount(2, $entries);
        $this->assertSame('invoice', $entries[0]->type);
        $this->assertSame('receipt', $entries[1]->type);
    }

    public function test_excludes_cash_invoices(): void
    {
        $this->makeInvoice('500.00', invoiceDate: '2026-05-15', invoiceNumber: 'INV-CASH', isCash: true);

        $entries = $this->service->build($this->customer);

        $this->assertCount(0, $entries);
    }

    public function test_excludes_reversed_invoices(): void
    {
        $inv = $this->makeInvoice('500.00', invoiceDate: '2026-05-15', invoiceNumber: 'INV-REV');
        $inv->update(['status' => Invoice::STATUS_REVERSED]);

        $entries = $this->service->build($this->customer);

        $this->assertCount(0, $entries);
    }

    public function test_excludes_reversed_receipts(): void
    {
        $rec = $this->makeReceipt('500.00', receiptDate: '2026-05-15', receiptNumber: 'RCT-REV');
        $rec->update(['status' => Receipt::STATUS_REVERSED]);

        $entries = $this->service->build($this->customer);

        $this->assertCount(0, $entries);
    }

    public function test_date_range_filter(): void
    {
        $this->makeInvoice('100.00', invoiceDate: '2026-04-01', invoiceNumber: 'INV-1');
        $this->makeInvoice('200.00', invoiceDate: '2026-05-15', invoiceNumber: 'INV-2');
        $this->makeInvoice('300.00', invoiceDate: '2026-06-01', invoiceNumber: 'INV-3');

        $entries = $this->service->build(
            $this->customer,
            CarbonImmutable::parse('2026-05-01'),
            CarbonImmutable::parse('2026-05-31'),
        );

        $this->assertCount(1, $entries);
        $this->assertSame('INV-2', $entries[0]->reference);
    }

    public function test_outstanding_balance_excludes_cash_and_reversed(): void
    {
        // Open: 500
        $this->makeInvoice('500.00', invoiceDate: '2026-05-01', invoiceNumber: 'OPEN', balanceDue: '500.00');
        // Cash: not in AR
        $this->makeInvoice('300.00', invoiceDate: '2026-05-02', invoiceNumber: 'CASH', isCash: true);
        // Partially paid: 100 outstanding
        $this->makeInvoice('400.00', invoiceDate: '2026-05-03', invoiceNumber: 'PART', balanceDue: '100.00');
        // Reversed: not counted
        $rev = $this->makeInvoice('200.00', invoiceDate: '2026-05-04', invoiceNumber: 'REV', balanceDue: '200.00');
        $rev->update(['status' => Invoice::STATUS_REVERSED]);

        $balance = $this->service->outstandingBalance($this->customer);

        $this->assertSame('600.00', $balance, '500 + 100 = 600');
    }

    public function test_other_customer_invoices_excluded(): void
    {
        $other = Counterparty::create([
            'organization_id' => $this->org->id,
            'name' => 'Other', 'code' => 'CUST-O',
            'type' => 'customer', 'currency_code' => 'GHS',
        ]);

        Invoice::create([
            'organization_id' => $this->org->id,
            'invoice_number' => 'INV-OTHER',
            'invoice_date' => '2026-05-15',
            'counterparty_id' => $other->id,
            'currency_code' => 'GHS', 'exchange_rate' => '1',
            'status' => Invoice::STATUS_POSTED, 'is_cash_invoice' => false,
            'subtotal' => '999', 'discount_total' => '0',
            'tax_total' => '0', 'total' => '999',
            'amount_paid' => '0', 'balance_due' => '999',
        ]);

        $entries = $this->service->build($this->customer);

        $this->assertCount(0, $entries);
    }

    /* helpers */

    protected function makeInvoice(
        string $total,
        string $invoiceDate,
        string $invoiceNumber,
        bool $isCash = false,
        ?string $balanceDue = null,
    ): Invoice {
        return Invoice::create([
            'organization_id' => $this->org->id,
            'invoice_number'  => $invoiceNumber,
            'invoice_date'    => $invoiceDate,
            'counterparty_id' => $this->customer->id,
            'currency_code'   => 'GHS', 'exchange_rate' => '1',
            'status'          => Invoice::STATUS_POSTED,
            'is_cash_invoice' => $isCash,
            'subtotal'        => $total, 'discount_total' => '0',
            'tax_total'       => '0',    'total'         => $total,
            'amount_paid'     => '0',
            'balance_due'     => $balanceDue ?? ($isCash ? '0' : $total),
            'created_by_user_id' => $this->user->id,
        ]);
    }

    protected function makeReceipt(string $amount, string $receiptDate, string $receiptNumber): Receipt
    {
        return Receipt::create([
            'organization_id'    => $this->org->id,
            'receipt_number'     => $receiptNumber,
            'receipt_date'       => $receiptDate,
            'counterparty_id'    => $this->customer->id,
            'currency_code'      => 'GHS', 'exchange_rate' => '1',
            'amount'             => $amount, 'allocated_amount' => '0',
            'status'             => Receipt::STATUS_POSTED,
            'is_backfilled'      => false,
            'reference_type'     => 'cash_receipt',
            'created_by_user_id' => $this->user->id,
        ]);
    }
}
