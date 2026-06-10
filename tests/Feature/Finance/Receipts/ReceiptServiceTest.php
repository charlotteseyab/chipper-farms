<?php

namespace Tests\Feature\Finance\Receipts;

use App\Finance\Posting\PostingEngine;
use App\Models\Counterparty;
use App\Models\Finance\Account;
use App\Models\Finance\Receipt;
use App\Models\PaymentMethod;
use App\Services\Finance\Receipts\ReceiptService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Finance\FinanceTestSetup;
use Tests\TestCase;

/**
 * Tests for ReceiptService — the cash-receipt creation path.
 *
 * Two flows:
 *   1. createPosted — standard customer payment: allocates number,
 *      posts journal Dr Cash / Cr AR control, returns posted receipt.
 *   2. createBackfilled — historical materialisation: creates receipt
 *      with is_backfilled=true, journal_batch_id=NULL, no GL impact.
 */
class ReceiptServiceTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    protected Counterparty $customer;
    protected PaymentMethod $cashMethod;
    protected ReceiptService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFinanceFixtures();

        // Required for ReceiptService.resolveCashAccountId fallback.
        if (! Account::where('organization_id', $this->org->id)->where('code', '1910')->exists()) {
            Account::create([
                'organization_id' => $this->org->id, 'code' => '1910',
                'name' => 'Suspense - Receipts', 'account_type' => Account::TYPE_ASSET,
                'currency_code' => 'GHS', 'is_active' => true, 'allow_manual_journal' => false,
            ]);
        }

        $this->customer = Counterparty::create([
            'organization_id' => $this->org->id,
            'name' => 'Test Customer', 'code' => 'CUST-T',
            'type' => 'customer', 'currency_code' => 'GHS',
        ]);

        $this->cashMethod = PaymentMethod::create([
            'organization_id' => $this->org->id,
            'code' => 'CASH', 'name' => 'Cash',
            'is_cash' => true, 'gl_account_hint' => 'CASH_ON_HAND', 'is_active' => true,
        ]);

        $this->service = $this->app->make(ReceiptService::class);
    }

    public function test_create_posted_assigns_receipt_number(): void
    {
        $receipt = $this->service->createPosted(
            customer: $this->customer,
            paymentMethod: $this->cashMethod,
            amount: '500.00',
            receiptDate: CarbonImmutable::parse('2026-05-15'),
            createdBy: $this->user,
        );

        $this->assertMatchesRegularExpression('/^RCT-2026-\d{6}$/', $receipt->receipt_number);
        $this->assertSame(Receipt::STATUS_POSTED, $receipt->status);
        $this->assertSame('500.00', $receipt->amount);
        $this->assertSame('0.00', $receipt->allocated_amount);
        $this->assertFalse((bool) $receipt->is_backfilled);
        $this->assertNotNull($receipt->journal_batch_id, 'Posted receipt should have journal_batch_id');
    }

    public function test_create_posted_writes_journal_dr_cash_cr_ar(): void
    {
        $receipt = $this->service->createPosted(
            customer: $this->customer,
            paymentMethod: $this->cashMethod,
            amount: '500.00',
            receiptDate: CarbonImmutable::parse('2026-05-15'),
            createdBy: $this->user,
        );

        $batch = $receipt->journalBatch;
        $this->assertNotNull($batch);

        $journal = $batch->journals->first();
        $debit  = $journal->lines->firstWhere('debit', '>', 0);
        $credit = $journal->lines->firstWhere('credit', '>', 0);

        $this->assertSame($this->cashAccount->id, $debit->account_id, 'Cash should be debited');
        $this->assertSame($this->arAccount->id, $credit->account_id, 'AR control should be credited');
        $this->assertSame('500.00', (string) $debit->debit);
        $this->assertSame('500.00', (string) $credit->credit);
    }

    public function test_create_posted_rejects_non_customer(): void
    {
        $vendor = Counterparty::create([
            'organization_id' => $this->org->id,
            'name' => 'Vendor', 'code' => 'VEN-1',
            'type' => 'supplier', 'currency_code' => 'GHS',
        ]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/not a customer/i');

        $this->service->createPosted(
            customer: $vendor,
            paymentMethod: $this->cashMethod,
            amount: '500.00',
            receiptDate: CarbonImmutable::parse('2026-05-15'),
            createdBy: $this->user,
        );
    }

    public function test_create_posted_rejects_zero_amount(): void
    {
        $this->expectException(\DomainException::class);

        $this->service->createPosted(
            customer: $this->customer,
            paymentMethod: $this->cashMethod,
            amount: '0',
            receiptDate: CarbonImmutable::parse('2026-05-15'),
            createdBy: $this->user,
        );
    }

    public function test_create_posted_rejects_non_cash_equivalent_method(): void
    {
        $accrualMethod = PaymentMethod::create([
            'organization_id' => $this->org->id,
            'code' => 'CR', 'name' => 'On Credit',
            'is_cash' => false, 'is_bank' => false, 'is_mobile_money' => false,
            'is_active' => true,
        ]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/not a cash-equivalent/i');

        $this->service->createPosted(
            customer: $this->customer,
            paymentMethod: $accrualMethod,
            amount: '500.00',
            receiptDate: CarbonImmutable::parse('2026-05-15'),
            createdBy: $this->user,
        );
    }

    public function test_create_backfilled_creates_receipt_without_posting(): void
    {
        $receipt = $this->service->createBackfilled(
            customer: $this->customer,
            paymentMethod: $this->cashMethod,
            amount: '500.00',
            receiptDate: CarbonImmutable::parse('2026-04-01'),
            createdBy: $this->user,
        );

        $this->assertSame(Receipt::STATUS_POSTED, $receipt->status);
        $this->assertTrue((bool) $receipt->is_backfilled);
        $this->assertNull($receipt->journal_batch_id, 'Backfilled receipt must NOT have a journal batch');
        $this->assertSame('backfill', $receipt->reference_type);
        $this->assertSame('500.00', $receipt->amount);
    }

    public function test_create_backfilled_handles_missing_payment_method(): void
    {
        $receipt = $this->service->createBackfilled(
            customer: $this->customer,
            paymentMethod: null,
            amount: '500.00',
            receiptDate: CarbonImmutable::parse('2026-04-01'),
            createdBy: $this->user,
        );

        $this->assertNull($receipt->payment_method_id);
        $this->assertSame(Receipt::STATUS_POSTED, $receipt->status);
        $this->assertTrue((bool) $receipt->is_backfilled);
    }
}
