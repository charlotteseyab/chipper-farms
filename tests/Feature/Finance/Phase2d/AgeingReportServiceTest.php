<?php

namespace Tests\Feature\Finance\Phase2d;

use App\Models\Counterparty;
use App\Models\Finance\Invoice;
use App\Services\Finance\Ageing\AgeingReportService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Finance\FinanceTestSetup;
use Tests\TestCase;

class AgeingReportServiceTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    protected Counterparty $customer;
    protected AgeingReportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFinanceFixtures();

        $this->customer = Counterparty::create([
            'organization_id' => $this->org->id,
            'name' => 'Acme', 'code' => 'CUST-A',
            'type' => 'customer', 'currency_code' => 'GHS',
            'credit_limit' => 10000,
            'credit_hold_mode' => 'warn',
        ]);

        $this->service = $this->app->make(AgeingReportService::class);
    }

    public function test_buckets_invoices_by_overdue_days(): void
    {
        $asOf = CarbonImmutable::parse('2026-09-15');

        // Current (not yet due): due_date in future
        $this->makeOpenInvoice('100.00', invoiceDate: '2026-09-15', dueDate: '2026-10-15');
        // 1-30 days overdue: due 14 days ago
        $this->makeOpenInvoice('200.00', invoiceDate: '2026-08-01', dueDate: '2026-09-01');
        // 31-60 days overdue: due 50 days ago
        $this->makeOpenInvoice('300.00', invoiceDate: '2026-07-01', dueDate: '2026-07-26');
        // 61-90 days overdue: due 80 days ago
        $this->makeOpenInvoice('400.00', invoiceDate: '2026-06-01', dueDate: '2026-06-27');
        // 90+ days overdue: due 120 days ago
        $this->makeOpenInvoice('500.00', invoiceDate: '2026-04-01', dueDate: '2026-05-18');

        $report = $this->service->build($asOf);

        $this->assertCount(1, $report->customerLines);
        $line = $report->customerLines[0];

        $this->assertSame('100.00', $line->buckets['current']);
        $this->assertSame('200.00', $line->buckets['bucket_1']);
        $this->assertSame('300.00', $line->buckets['bucket_2']);
        $this->assertSame('400.00', $line->buckets['bucket_3']);
        $this->assertSame('500.00', $line->buckets['bucket_4']);
        $this->assertSame('1500.00', $line->buckets['total']);
    }

    public function test_grand_totals_sum_across_customers(): void
    {
        $other = Counterparty::create([
            'organization_id' => $this->org->id,
            'name' => 'Beta', 'code' => 'CUST-B',
            'type' => 'customer', 'currency_code' => 'GHS',
        ]);

        $this->makeOpenInvoice('200.00', invoiceDate: '2026-08-01', dueDate: '2026-09-01', customer: $this->customer);
        $this->makeOpenInvoice('300.00', invoiceDate: '2026-08-01', dueDate: '2026-09-01', customer: $other);

        $report = $this->service->build(CarbonImmutable::parse('2026-09-15'));

        $this->assertCount(2, $report->customerLines);
        $this->assertSame('500.00', $report->grandTotals['bucket_1']);
        $this->assertSame('500.00', $report->grandTotals['total']);
    }

    public function test_excludes_cash_invoices(): void
    {
        $this->makeOpenInvoice('500.00', invoiceDate: '2026-08-01', dueDate: '2026-09-01', isCash: true);

        $report = $this->service->build(CarbonImmutable::parse('2026-09-15'));

        $this->assertTrue($report->isEmpty());
    }

    public function test_excludes_fully_paid_invoices(): void
    {
        $inv = $this->makeOpenInvoice('500.00', invoiceDate: '2026-08-01', dueDate: '2026-09-01');
        $inv->update(['amount_paid' => '500.00', 'balance_due' => '0', 'fully_paid_at' => now()]);

        $report = $this->service->build(CarbonImmutable::parse('2026-09-15'));

        $this->assertTrue($report->isEmpty());
    }

    public function test_excludes_reversed_invoices(): void
    {
        $inv = $this->makeOpenInvoice('500.00', invoiceDate: '2026-08-01', dueDate: '2026-09-01');
        $inv->update(['status' => Invoice::STATUS_REVERSED]);

        $report = $this->service->build(CarbonImmutable::parse('2026-09-15'));

        $this->assertTrue($report->isEmpty());
    }

    public function test_sorts_by_total_balance_desc(): void
    {
        $small = Counterparty::create([
            'organization_id' => $this->org->id, 'name' => 'Small', 'code' => 'S',
            'type' => 'customer', 'currency_code' => 'GHS',
        ]);
        $big = Counterparty::create([
            'organization_id' => $this->org->id, 'name' => 'Big', 'code' => 'B',
            'type' => 'customer', 'currency_code' => 'GHS',
        ]);

        $this->makeOpenInvoice('100.00', invoiceDate: '2026-08-01', dueDate: '2026-09-01', customer: $small);
        $this->makeOpenInvoice('5000.00', invoiceDate: '2026-08-01', dueDate: '2026-09-01', customer: $big);

        $report = $this->service->build(CarbonImmutable::parse('2026-09-15'));

        $this->assertSame('Big', $report->customerLines[0]->counterparty->name);
        $this->assertSame('Small', $report->customerLines[1]->counterparty->name);
    }

    public function test_filter_by_specific_counterparty(): void
    {
        $other = Counterparty::create([
            'organization_id' => $this->org->id, 'name' => 'Other', 'code' => 'O',
            'type' => 'customer', 'currency_code' => 'GHS',
        ]);

        $this->makeOpenInvoice('200.00', invoiceDate: '2026-08-01', dueDate: '2026-09-01', customer: $this->customer);
        $this->makeOpenInvoice('999.00', invoiceDate: '2026-08-01', dueDate: '2026-09-01', customer: $other);

        $report = $this->service->build(CarbonImmutable::parse('2026-09-15'), counterpartyId: $this->customer->id);

        $this->assertCount(1, $report->customerLines);
        $this->assertSame('Acme', $report->customerLines[0]->counterparty->name);
    }

    public function test_severe_overdue_predicate(): void
    {
        $this->makeOpenInvoice('500.00', invoiceDate: '2026-04-01', dueDate: '2026-05-18');

        $report = $this->service->build(CarbonImmutable::parse('2026-09-15'));

        $this->assertTrue($report->customerLines[0]->hasSevereOverdue());
    }

    public function test_exceeds_credit_limit_predicate(): void
    {
        // Customer credit limit: 10,000. Stack invoices over that.
        for ($i = 0; $i < 12; $i++) {
            $this->makeOpenInvoice('1000.00', invoiceDate: '2026-08-01', dueDate: '2026-09-01');
        }

        $report = $this->service->build(CarbonImmutable::parse('2026-09-15'));

        $this->assertTrue($report->customerLines[0]->exceedsCreditLimit());
    }

    public function test_bucket_labels_dynamic_to_config(): void
    {
        config(['finance.ageing.bucket_days' => [15, 30, 60]]);

        $this->makeOpenInvoice('100.00', invoiceDate: '2026-08-01', dueDate: '2026-09-01');
        $report = $this->service->build(CarbonImmutable::parse('2026-09-15'));

        $labels = $report->bucketLabels();
        $this->assertSame('1-15 days', $labels['bucket_1']);
        $this->assertSame('16-30 days', $labels['bucket_2']);
        $this->assertSame('31-60 days', $labels['bucket_3']);
        $this->assertSame('60+ days', $labels['bucket_4']);
    }

    /* helper */
    protected function makeOpenInvoice(
        string $total,
        string $invoiceDate,
        string $dueDate,
        bool $isCash = false,
        ?Counterparty $customer = null,
    ): Invoice {
        static $counter = 0;
        $cust = $customer ?? $this->customer;
        return Invoice::create([
            'organization_id'  => $this->org->id,
            'invoice_number'   => 'INV-' . str_pad((string) (++$counter), 6, '0', STR_PAD_LEFT),
            'invoice_date'     => $invoiceDate,
            'due_date'         => $dueDate,
            'counterparty_id'  => $cust->id,
            'currency_code'    => 'GHS', 'exchange_rate' => '1',
            'status'           => Invoice::STATUS_POSTED,
            'is_cash_invoice'  => $isCash,
            'subtotal'         => $total, 'discount_total' => '0',
            'tax_total'        => '0',    'total'         => $total,
            'amount_paid'      => '0',
            'balance_due'      => $isCash ? '0' : $total,
            'fully_paid_at'    => $isCash ? now() : null,
            'created_by_user_id' => $this->user->id,
        ]);
    }
}
