<?php

namespace Tests\Feature\Finance\Receipts;

use App\Models\Counterparty;
use App\Models\Finance\Account;
use App\Models\Finance\Invoice;
use App\Models\Finance\Receipt;
use App\Models\Finance\ReceiptAllocation;
use App\Models\PaymentMethod;
use App\Services\Finance\Allocations\AllocationService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Finance\FinanceTestSetup;
use Tests\TestCase;

/**
 * AllocationService is the heart of Phase 2c. Every allocation
 * invariant is verified here.
 *
 * Invariants tested:
 *   1. allocation amount > 0
 *   2. allocation amount <= receipt.unallocatedAmount
 *   3. allocation amount <= invoice.balance_due
 *   4. counterparty must match (no cross-customer allocation)
 *   5. currency must match (Phase 5+ will add FX)
 *   6. cash invoices reject allocation
 *   7. After allocate(): receipt.allocated_amount and invoice.amount_paid both update
 *   8. After reverse(): both aggregates restore
 *   9. fully_paid_at sets when balance hits zero, clears when restored
 *  10. autoAllocateFifo walks oldest invoice first and stops cleanly
 */
class AllocationServiceTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    protected Counterparty $customer;
    protected Counterparty $otherCustomer;
    protected AllocationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFinanceFixtures();

        $this->customer = Counterparty::create([
            'organization_id' => $this->org->id,
            'name' => 'Test Customer A', 'code' => 'CUST-A',
            'type' => 'customer', 'currency_code' => 'GHS',
        ]);

        $this->otherCustomer = Counterparty::create([
            'organization_id' => $this->org->id,
            'name' => 'Test Customer B', 'code' => 'CUST-B',
            'type' => 'customer', 'currency_code' => 'GHS',
        ]);

        $this->service = $this->app->make(AllocationService::class);
    }

    public function test_happy_path_single_allocation(): void
    {
        $invoice = $this->makePostedInvoice($this->customer, '500.00');
        $receipt = $this->makePostedReceipt($this->customer, '500.00');

        $allocation = $this->service->allocate($receipt, $invoice, '500.00', $this->user);

        $this->assertSame(ReceiptAllocation::STATUS_ACTIVE, $allocation->status);
        $this->assertSame('500.00', $allocation->amount);

        $receipt->refresh();
        $invoice->refresh();
        $this->assertSame('500.00', $receipt->allocated_amount);
        $this->assertSame('0.00', $receipt->unallocatedAmount());
        $this->assertSame('500.00', $invoice->amount_paid);
        $this->assertSame('0.00', $invoice->balance_due);
        $this->assertNotNull($invoice->fully_paid_at);
        $this->assertTrue($invoice->isFullyPaid());
    }

    public function test_partial_allocation_leaves_balance(): void
    {
        $invoice = $this->makePostedInvoice($this->customer, '1000.00');
        $receipt = $this->makePostedReceipt($this->customer, '300.00');

        $this->service->allocate($receipt, $invoice, '300.00', $this->user);

        $invoice->refresh();
        $this->assertSame('300.00', $invoice->amount_paid);
        $this->assertSame('700.00', $invoice->balance_due);
        $this->assertNull($invoice->fully_paid_at);
        $this->assertTrue($invoice->isPartiallyPaid());
    }

    public function test_over_allocation_against_receipt_rejected(): void
    {
        $invoice = $this->makePostedInvoice($this->customer, '1000.00');
        $receipt = $this->makePostedReceipt($this->customer, '500.00');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/exceeds receipt.*unallocated/i');

        $this->service->allocate($receipt, $invoice, '600.00', $this->user);
    }

    public function test_over_allocation_against_invoice_rejected(): void
    {
        $invoice = $this->makePostedInvoice($this->customer, '500.00');
        $receipt = $this->makePostedReceipt($this->customer, '1000.00');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/exceeds invoice.*balance due/i');

        $this->service->allocate($receipt, $invoice, '600.00', $this->user);
    }

    public function test_zero_amount_rejected(): void
    {
        $invoice = $this->makePostedInvoice($this->customer, '500.00');
        $receipt = $this->makePostedReceipt($this->customer, '500.00');

        $this->expectException(\DomainException::class);
        $this->service->allocate($receipt, $invoice, '0.00', $this->user);
    }

    public function test_counterparty_mismatch_rejected(): void
    {
        $invoice = $this->makePostedInvoice($this->customer, '500.00');
        $receipt = $this->makePostedReceipt($this->otherCustomer, '500.00');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/counterparty mismatch/i');

        $this->service->allocate($receipt, $invoice, '500.00', $this->user);
    }

    public function test_currency_mismatch_rejected(): void
    {
        $invoice = $this->makePostedInvoice($this->customer, '500.00', currency: 'GHS');
        $receipt = $this->makePostedReceipt($this->customer, '500.00', currency: 'USD');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/currency mismatch/i');

        $this->service->allocate($receipt, $invoice, '500.00', $this->user);
    }

    public function test_cash_invoice_rejected(): void
    {
        $invoice = $this->makePostedInvoice($this->customer, '500.00', isCash: true);
        $receipt = $this->makePostedReceipt($this->customer, '500.00');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/cash invoice/i');

        $this->service->allocate($receipt, $invoice, '500.00', $this->user);
    }

    public function test_multi_allocation_aggregates_correctly(): void
    {
        $invoice = $this->makePostedInvoice($this->customer, '1000.00');
        $receipt = $this->makePostedReceipt($this->customer, '1000.00');

        $this->service->allocate($receipt, $invoice, '300.00', $this->user, CarbonImmutable::parse('2026-05-10'));
        $this->service->allocate($receipt, $invoice, '400.00', $this->user, CarbonImmutable::parse('2026-05-15'));
        $this->service->allocate($receipt, $invoice, '300.00', $this->user, CarbonImmutable::parse('2026-05-20'));

        $invoice->refresh();
        $this->assertSame('1000.00', $invoice->amount_paid);
        $this->assertSame('0.00', $invoice->balance_due);
        $this->assertSame('2026-05-10', $invoice->first_payment_at->toDateString());
        $this->assertSame('2026-05-20', $invoice->last_payment_at->toDateString());
        $this->assertNotNull($invoice->fully_paid_at);
    }

    public function test_reversal_restores_aggregates_on_both_sides(): void
    {
        $invoice = $this->makePostedInvoice($this->customer, '500.00');
        $receipt = $this->makePostedReceipt($this->customer, '500.00');

        $allocation = $this->service->allocate($receipt, $invoice, '500.00', $this->user);

        $this->service->reverse($allocation, $this->user, 'customer dispute');

        $receipt->refresh();
        $invoice->refresh();
        $allocation->refresh();

        $this->assertSame(ReceiptAllocation::STATUS_REVERSED, $allocation->status);
        $this->assertSame('customer dispute', $allocation->reversal_reason);

        $this->assertSame('0.00', $receipt->allocated_amount);
        $this->assertSame('500.00', $receipt->unallocatedAmount());

        $this->assertSame('0.00', $invoice->amount_paid);
        $this->assertSame('500.00', $invoice->balance_due);
        $this->assertNull($invoice->fully_paid_at);
    }

    public function test_partial_reversal_with_remaining_active_allocation(): void
    {
        $invoice = $this->makePostedInvoice($this->customer, '1000.00');
        $receipt = $this->makePostedReceipt($this->customer, '1000.00');

        $a1 = $this->service->allocate($receipt, $invoice, '400.00', $this->user);
        $a2 = $this->service->allocate($receipt, $invoice, '600.00', $this->user);

        // Reverse a2 only.
        $this->service->reverse($a2, $this->user, 'partial refund');

        $invoice->refresh();
        $this->assertSame('400.00', $invoice->amount_paid);
        $this->assertSame('600.00', $invoice->balance_due);
        $this->assertNull($invoice->fully_paid_at);
    }

    public function test_double_reverse_rejected(): void
    {
        $invoice = $this->makePostedInvoice($this->customer, '500.00');
        $receipt = $this->makePostedReceipt($this->customer, '500.00');

        $allocation = $this->service->allocate($receipt, $invoice, '500.00', $this->user);
        $this->service->reverse($allocation, $this->user, 'first');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/cannot be reversed/i');
        $this->service->reverse($allocation, $this->user, 'second');
    }

    public function test_auto_allocate_fifo_walks_oldest_first(): void
    {
        $oldInvoice    = $this->makePostedInvoice($this->customer, '300.00', invoiceDate: '2026-04-01');
        $middleInvoice = $this->makePostedInvoice($this->customer, '300.00', invoiceDate: '2026-04-15');
        $newInvoice    = $this->makePostedInvoice($this->customer, '300.00', invoiceDate: '2026-05-01');

        $receipt = $this->makePostedReceipt($this->customer, '500.00');

        $allocations = $this->service->autoAllocateFifo($receipt, $this->user);

        $this->assertCount(2, $allocations, 'Receipt of 500 should produce 2 allocations: 300 + 200');

        $oldInvoice->refresh();
        $middleInvoice->refresh();
        $newInvoice->refresh();

        $this->assertSame('0.00', $oldInvoice->balance_due, 'oldest fully paid');
        $this->assertSame('100.00', $middleInvoice->balance_due, 'middle partially paid');
        $this->assertSame('300.00', $newInvoice->balance_due, 'newest untouched');

        $receipt->refresh();
        $this->assertSame('500.00', $receipt->allocated_amount);
        $this->assertSame('0.00', $receipt->unallocatedAmount());
    }

    public function test_auto_allocate_fifo_stops_when_no_open_invoices(): void
    {
        $receipt = $this->makePostedReceipt($this->customer, '500.00');

        $allocations = $this->service->autoAllocateFifo($receipt, $this->user);

        $this->assertEmpty($allocations);
        $this->assertSame('0.00', $receipt->fresh()->allocated_amount);
    }

    /* helpers */

    protected function makePostedInvoice(
        Counterparty $customer,
        string $total,
        bool $isCash = false,
        string $invoiceDate = '2026-05-15',
        string $currency = 'GHS',
    ): Invoice {
        static $counter = 0;
        return Invoice::create([
            'organization_id'  => $this->org->id,
            'invoice_number'   => 'INV-2026-' . str_pad((string) (++$counter), 6, '0', STR_PAD_LEFT),
            'invoice_date'     => $invoiceDate,
            'counterparty_id'  => $customer->id,
            'currency_code'    => $currency,
            'exchange_rate'    => '1',
            'status'           => Invoice::STATUS_POSTED,
            'is_cash_invoice'  => $isCash,
            'subtotal'         => $total, 'discount_total' => '0',
            'tax_total'        => '0',    'total'         => $total,
            'amount_paid'      => '0',    'balance_due'   => $isCash ? '0' : $total,
            'fully_paid_at'    => $isCash ? now() : null,
            'created_by_user_id' => $this->user->id,
            'posted_by_user_id'  => $this->user->id,
            'posted_at'          => now(),
        ]);
    }

    protected function makePostedReceipt(
        Counterparty $customer,
        string $amount,
        string $currency = 'GHS',
    ): Receipt {
        static $counter = 0;
        return Receipt::create([
            'organization_id'    => $this->org->id,
            'receipt_number'     => 'RCT-2026-' . str_pad((string) (++$counter), 6, '0', STR_PAD_LEFT),
            'receipt_date'       => '2026-05-15',
            'counterparty_id'    => $customer->id,
            'currency_code'      => $currency,
            'exchange_rate'      => '1',
            'amount'             => $amount,
            'allocated_amount'   => '0',
            'status'             => Receipt::STATUS_POSTED,
            'is_backfilled'      => false,
            'reference_type'     => 'cash_receipt',
            'created_by_user_id' => $this->user->id,
        ]);
    }
}
