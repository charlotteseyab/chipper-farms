<?php

namespace Tests\Feature\Finance\Phase2d;

use App\Models\Counterparty;
use App\Models\Finance\Invoice;
use App\Models\Finance\Receipt;
use App\Services\Finance\Statements\StatementService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Finance\FinanceTestSetup;
use Tests\TestCase;

class StatementServiceTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    protected Counterparty $customer;
    protected StatementService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFinanceFixtures();

        $this->customer = Counterparty::create([
            'organization_id' => $this->org->id,
            'name' => 'Acme', 'code' => 'CUST-A',
            'type' => 'customer', 'currency_code' => 'GHS',
        ]);

        $this->service = $this->app->make(StatementService::class);
    }

    public function test_opening_balance_reflects_pre_period_activity(): void
    {
        // Pre-period: 1000 invoiced, 200 received → 800 carried into period
        $this->makeInvoice('1000.00', invoiceDate: '2026-04-15', invoiceNumber: 'INV-PRE-1');
        $this->makeReceipt('200.00',  receiptDate: '2026-04-20', receiptNumber: 'RCT-PRE-1');

        $statement = $this->service->build(
            $this->customer,
            CarbonImmutable::parse('2026-05-01'),
            CarbonImmutable::parse('2026-05-31'),
        );

        $this->assertSame('800.00', $statement->openingBalance);
        $this->assertEmpty($statement->entries, 'No within-period activity');
        $this->assertSame('800.00', $statement->closingBalance, 'Closing = opening when no period activity');
    }

    public function test_period_activity_recorded_chronologically(): void
    {
        $this->makeInvoice('500.00', invoiceDate: '2026-05-10', invoiceNumber: 'INV-1');
        $this->makeReceipt('300.00', receiptDate: '2026-05-15', receiptNumber: 'RCT-1');
        $this->makeInvoice('200.00', invoiceDate: '2026-05-20', invoiceNumber: 'INV-2');

        $statement = $this->service->build(
            $this->customer,
            CarbonImmutable::parse('2026-05-01'),
            CarbonImmutable::parse('2026-05-31'),
        );

        $this->assertSame('0.00', $statement->openingBalance);
        $this->assertCount(3, $statement->entries);
        $this->assertSame('400.00', $statement->closingBalance, '500 - 300 + 200 = 400');
        $this->assertSame(2, $statement->invoicesCount);
        $this->assertSame(1, $statement->receiptsCount);
    }

    public function test_handles_pre_period_carry_plus_within_period_activity(): void
    {
        $this->makeInvoice('1000.00', invoiceDate: '2026-04-15', invoiceNumber: 'INV-PRE');
        $this->makeReceipt('500.00',  receiptDate: '2026-05-10', receiptNumber: 'RCT-IN');
        $this->makeInvoice('200.00',  invoiceDate: '2026-05-20', invoiceNumber: 'INV-IN');

        $statement = $this->service->build(
            $this->customer,
            CarbonImmutable::parse('2026-05-01'),
            CarbonImmutable::parse('2026-05-31'),
        );

        $this->assertSame('1000.00', $statement->openingBalance);
        $this->assertCount(2, $statement->entries);
        $this->assertSame('700.00', $statement->closingBalance, '1000 - 500 + 200 = 700');
    }

    public function test_open_invoices_loaded_as_of_end_date(): void
    {
        $this->makeInvoice('500.00', invoiceDate: '2026-05-10', invoiceNumber: 'INV-1', balanceDue: '500.00');
        $this->makeInvoice('300.00', invoiceDate: '2026-06-15', invoiceNumber: 'INV-2', balanceDue: '300.00');

        // Statement period ends mid-May; June invoice should NOT appear in openInvoices.
        $statement = $this->service->build(
            $this->customer,
            CarbonImmutable::parse('2026-05-01'),
            CarbonImmutable::parse('2026-05-31'),
        );

        $this->assertCount(1, $statement->openInvoices);
        $this->assertSame('INV-1', $statement->openInvoices->first()->invoice_number);
    }

    public function test_excludes_cash_invoices_from_open(): void
    {
        $this->makeInvoice('500.00', invoiceDate: '2026-05-10', invoiceNumber: 'INV-CASH', isCash: true);

        $statement = $this->service->build(
            $this->customer,
            CarbonImmutable::parse('2026-05-01'),
            CarbonImmutable::parse('2026-05-31'),
        );

        $this->assertCount(0, $statement->openInvoices);
    }

    public function test_empty_statement_when_no_activity(): void
    {
        $statement = $this->service->build(
            $this->customer,
            CarbonImmutable::parse('2026-05-01'),
            CarbonImmutable::parse('2026-05-31'),
        );

        $this->assertTrue($statement->isEmpty());
        $this->assertSame('0.00', $statement->openingBalance);
        $this->assertSame('0.00', $statement->closingBalance);
    }

    public function test_suggested_filename(): void
    {
        $statement = $this->service->build(
            $this->customer,
            CarbonImmutable::parse('2026-05-01'),
            CarbonImmutable::parse('2026-05-31'),
        );

        $this->assertSame('statement_CUST-A_20260501_20260531.pdf', $statement->suggestedFilename());
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
            'organization_id'  => $this->org->id,
            'invoice_number'   => $invoiceNumber,
            'invoice_date'     => $invoiceDate,
            'counterparty_id'  => $this->customer->id,
            'currency_code'    => 'GHS', 'exchange_rate' => '1',
            'status'           => Invoice::STATUS_POSTED,
            'is_cash_invoice'  => $isCash,
            'subtotal'         => $total, 'discount_total' => '0',
            'tax_total'        => '0',    'total'         => $total,
            'amount_paid'      => '0',
            'balance_due'      => $balanceDue ?? ($isCash ? '0' : $total),
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
