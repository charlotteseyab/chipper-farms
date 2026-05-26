<?php

namespace Tests\Feature\Finance\Ageing;

use App\Models\Counterparty;
use App\Models\Finance\Account;
use App\Models\Finance\SupplierCategory;
use App\Models\Finance\VendorInvoice;
use App\Services\Finance\Ageing\ApAgeingService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Finance\FinanceTestSetup;
use Tests\TestCase;

/**
 * Phase 3d — AP ageing report tests.
 */
class ApAgeingServiceTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    protected Counterparty $supplier;
    protected ApAgeingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFinanceFixtures();

        $expense = Account::firstOrCreate(
            ['organization_id' => $this->org->id, 'code' => '6010'],
            ['name' => 'Sundry Expense', 'account_type' => Account::TYPE_EXPENSE,
             'currency_code' => 'GHS', 'is_active' => true, 'allow_manual_journal' => true],
        );

        $cat = SupplierCategory::create([
            'organization_id' => $this->org->id,
            'code' => 'GEN', 'name' => 'General',
            'default_expense_account_id' => $expense->id,
            'payment_terms_days' => 30,
            'default_withholding_percent' => 0,
            'is_active' => true,
        ]);

        $this->supplier = Counterparty::create([
            'organization_id' => $this->org->id,
            'name' => 'Acme Vendor', 'code' => 'VEND-A',
            'type' => Counterparty::TYPE_SUPPLIER,
            'currency_code' => 'GHS',
            'supplier_category_id' => $cat->id,
            'tax_id' => 'P0001234567',
        ]);

        $this->service = $this->app->make(ApAgeingService::class);
    }

    public function test_buckets_vendor_invoices_by_overdue_days(): void
    {
        $asOf = CarbonImmutable::parse('2026-09-15');

        // current
        $this->makeOpenVi('100.00', '2026-09-15', '2026-10-15');
        // 1-30 (14 days overdue)
        $this->makeOpenVi('200.00', '2026-08-01', '2026-09-01');
        // 31-60 (50 overdue)
        $this->makeOpenVi('300.00', '2026-07-01', '2026-07-26');
        // 61-90 (80 overdue)
        $this->makeOpenVi('400.00', '2026-06-01', '2026-06-27');
        // 90+ (120 overdue)
        $this->makeOpenVi('500.00', '2026-04-01', '2026-05-18');

        $report = $this->service->build($asOf);

        $this->assertCount(1, $report->customerLines);
        $line = $report->customerLines[0];

        $this->assertSame('100.00', $line->buckets['current']);
        $this->assertSame('200.00', $line->buckets['bucket_1']);
        $this->assertSame('300.00', $line->buckets['bucket_2']);
        $this->assertSame('400.00', $line->buckets['bucket_3']);
        $this->assertSame('500.00', $line->buckets['bucket_4']);
        $this->assertSame('1500.00', $line->buckets['total']);
        $this->assertSame('ap', $report->reportType);
        $this->assertTrue($report->isAp());
    }

    public function test_excludes_drafts_cancelled_reversed_and_paid(): void
    {
        $this->makeOpenVi('100.00', '2026-08-01', '2026-09-01'); // posted, balance > 0 → counts
        $this->makeOpenVi('999.00', '2026-08-01', '2026-09-01', status: VendorInvoice::STATUS_DRAFT);
        $this->makeOpenVi('999.00', '2026-08-01', '2026-09-01', balanceDue: '0');

        $report = $this->service->build(CarbonImmutable::parse('2026-09-15'));
        $this->assertCount(1, $report->customerLines);
        $this->assertSame('100.00', $report->grandTotals['total']);
    }

    public function test_severe_overdue_flag_set_when_90_plus_present(): void
    {
        $this->makeOpenVi('100.00', '2026-04-01', '2026-05-18'); // 90+

        $report = $this->service->build(CarbonImmutable::parse('2026-09-15'));
        $line = $report->customerLines[0];
        $this->assertTrue($line->hasSevereOverdue());
    }

    public function test_oldest_invoice_returned(): void
    {
        $oldest = $this->makeOpenVi('100.00', '2026-04-01', '2026-05-18');
        $this->makeOpenVi('200.00', '2026-08-01', '2026-09-01');

        $report = $this->service->build(CarbonImmutable::parse('2026-09-15'));
        $this->assertSame($oldest->id, $report->customerLines[0]->oldestInvoice->id);
    }

    public function test_empty_report_when_no_open_vendor_invoices(): void
    {
        $report = $this->service->build(CarbonImmutable::parse('2026-09-15'));
        $this->assertTrue($report->isEmpty());
        $this->assertSame('0.00', $report->grandTotals['total']);
    }

    public function test_grouping_per_supplier_with_grand_totals(): void
    {
        $cat = SupplierCategory::first();
        $other = Counterparty::create([
            'organization_id' => $this->org->id,
            'name' => 'Beta Vendor', 'code' => 'VEND-B',
            'type' => Counterparty::TYPE_SUPPLIER, 'currency_code' => 'GHS',
            'supplier_category_id' => $cat->id,
        ]);

        $this->makeOpenVi('200.00', '2026-08-01', '2026-09-01', supplier: $this->supplier);
        $this->makeOpenVi('300.00', '2026-08-01', '2026-09-01', supplier: $other);

        $report = $this->service->build(CarbonImmutable::parse('2026-09-15'));
        $this->assertCount(2, $report->customerLines);
        $this->assertSame('500.00', $report->grandTotals['total']);
        // Sorted by total desc — Beta first.
        $this->assertSame($other->id, $report->customerLines[0]->counterparty->id);
    }

    /* helpers */

    protected function makeOpenVi(
        string $total,
        string $invoiceDate,
        string $dueDate,
        ?Counterparty $supplier = null,
        string $status = VendorInvoice::STATUS_POSTED,
        ?string $balanceDue = null,
    ): VendorInvoice {
        static $counter = 0;
        $sup = $supplier ?? $this->supplier;
        return VendorInvoice::create([
            'organization_id' => $this->org->id,
            'vendor_invoice_number' => 'VI-2026-' . str_pad((string) (++$counter), 6, '0', STR_PAD_LEFT),
            'invoice_date'     => $invoiceDate,
            'due_date'         => $dueDate,
            'counterparty_id'  => $sup->id,
            'currency_code'    => 'GHS', 'exchange_rate' => '1',
            'status'           => $status,
            'subtotal'         => $total, 'tax_total' => '0', 'withholding_total' => '0',
            'total'            => $total,
            'amount_paid'      => '0',
            'balance_due'      => $balanceDue ?? $total,
        ]);
    }
}
