<?php

namespace Tests\Feature\Finance\Phase2d;

use App\Models\Counterparty;
use App\Models\Finance\Account;
use App\Models\Finance\Receipt;
use App\Models\Finance\Refund;
use App\Models\PaymentMethod;
use App\Services\Finance\Refunds\RefundService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Finance\FinanceTestSetup;
use Tests\TestCase;

class RefundServiceTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    protected Counterparty $customer;
    protected PaymentMethod $cashMethod;
    protected RefundService $service;

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

        $this->service = $this->app->make(RefundService::class);
    }

    public function test_create_draft_assigns_refund_number(): void
    {
        $refund = $this->service->createDraft(
            customer: $this->customer,
            paymentMethod: $this->cashMethod,
            amount: '500.00',
            refundDate: CarbonImmutable::parse('2026-05-15'),
            createdBy: $this->user,
            extras: ['reason' => 'customer overpaid'],
        );

        $this->assertMatchesRegularExpression('/^REF-2026-\d{6}$/', $refund->refund_number);
        $this->assertSame(Refund::STATUS_DRAFT, $refund->status);
        $this->assertNull($refund->journal_batch_id);
        $this->assertNull($refund->approved_by_user_id);
    }

    public function test_create_draft_rejects_non_customer(): void
    {
        $vendor = Counterparty::create([
            'organization_id' => $this->org->id,
            'name' => 'V', 'code' => 'V', 'type' => 'supplier', 'currency_code' => 'GHS',
        ]);

        $this->expectException(\DomainException::class);
        $this->service->createDraft(
            customer: $vendor,
            paymentMethod: $this->cashMethod,
            amount: '500.00',
            refundDate: CarbonImmutable::parse('2026-05-15'),
            createdBy: $this->user,
        );
    }

    public function test_create_draft_rejects_zero_amount(): void
    {
        $this->expectException(\DomainException::class);
        $this->service->createDraft(
            customer: $this->customer,
            paymentMethod: $this->cashMethod,
            amount: '0',
            refundDate: CarbonImmutable::parse('2026-05-15'),
            createdBy: $this->user,
        );
    }

    public function test_create_draft_rejects_non_cash_method(): void
    {
        $accrual = PaymentMethod::create([
            'organization_id' => $this->org->id,
            'code' => 'CR', 'name' => 'On Credit',
            'is_cash' => false, 'is_bank' => false, 'is_mobile_money' => false,
            'is_active' => true,
        ]);

        $this->expectException(\DomainException::class);
        $this->service->createDraft(
            customer: $this->customer,
            paymentMethod: $accrual,
            amount: '500.00',
            refundDate: CarbonImmutable::parse('2026-05-15'),
            createdBy: $this->user,
        );
    }

    public function test_source_receipt_validation_counterparty_match(): void
    {
        $other = Counterparty::create([
            'organization_id' => $this->org->id,
            'name' => 'Other', 'code' => 'O', 'type' => 'customer', 'currency_code' => 'GHS',
        ]);
        $sourceReceipt = $this->makePostedReceipt($other, '500.00');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/belongs to customer/i');

        $this->service->createDraft(
            customer: $this->customer,
            paymentMethod: $this->cashMethod,
            amount: '500.00',
            refundDate: CarbonImmutable::parse('2026-05-15'),
            createdBy: $this->user,
            extras: ['source_receipt_id' => $sourceReceipt->id],
        );
    }

    public function test_source_receipt_validation_unallocated_cap(): void
    {
        $sourceReceipt = $this->makePostedReceipt($this->customer, '500.00', allocatedAmount: '300.00');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/exceeds receipt.*unallocated/i');

        $this->service->createDraft(
            customer: $this->customer,
            paymentMethod: $this->cashMethod,
            amount: '300.00',  // would exceed unallocated 200
            refundDate: CarbonImmutable::parse('2026-05-15'),
            createdBy: $this->user,
            extras: ['source_receipt_id' => $sourceReceipt->id],
        );
    }

    public function test_source_receipt_must_be_posted(): void
    {
        $sourceReceipt = $this->makePostedReceipt($this->customer, '500.00');
        $sourceReceipt->update(['status' => Receipt::STATUS_REVERSED]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/not posted/i');

        $this->service->createDraft(
            customer: $this->customer,
            paymentMethod: $this->cashMethod,
            amount: '300.00',
            refundDate: CarbonImmutable::parse('2026-05-15'),
            createdBy: $this->user,
            extras: ['source_receipt_id' => $sourceReceipt->id],
        );
    }

    public function test_backfilled_receipt_cannot_be_refunded(): void
    {
        $sourceReceipt = $this->makePostedReceipt($this->customer, '500.00');
        $sourceReceipt->update(['is_backfilled' => true]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/backfilled/i');

        $this->service->createDraft(
            customer: $this->customer,
            paymentMethod: $this->cashMethod,
            amount: '300.00',
            refundDate: CarbonImmutable::parse('2026-05-15'),
            createdBy: $this->user,
            extras: ['source_receipt_id' => $sourceReceipt->id],
        );
    }

    public function test_post_creates_journal_dr_ar_cr_cash(): void
    {
        $refund = $this->service->createDraft(
            customer: $this->customer,
            paymentMethod: $this->cashMethod,
            amount: '500.00',
            refundDate: CarbonImmutable::parse('2026-05-15'),
            createdBy: $this->user,
        );

        $approver = $this->user;  // For test purposes
        $this->service->post($refund, $approver);

        $refund->refresh();

        $this->assertSame(Refund::STATUS_POSTED, $refund->status);
        $this->assertNotNull($refund->journal_batch_id);
        $this->assertSame((int) $approver->id, (int) $refund->approved_by_user_id);
        $this->assertNotNull($refund->approved_at);

        $batch = $refund->journalBatch;
        $journal = $batch->journals->first();
        $debit = $journal->lines->firstWhere('debit', '>', 0);
        $credit = $journal->lines->firstWhere('credit', '>', 0);

        $this->assertSame($this->arAccount->id, $debit->account_id, 'AR control should be debited (Dr AR)');
        $this->assertSame($this->cashAccount->id, $credit->account_id, 'Cash should be credited (Cr Cash)');
    }

    public function test_post_revalidates_source_receipt_unallocated(): void
    {
        // Create draft when source has 500 unallocated
        $sourceReceipt = $this->makePostedReceipt($this->customer, '500.00');
        $refund = $this->service->createDraft(
            customer: $this->customer,
            paymentMethod: $this->cashMethod,
            amount: '500.00',
            refundDate: CarbonImmutable::parse('2026-05-15'),
            createdBy: $this->user,
            extras: ['source_receipt_id' => $sourceReceipt->id],
        );

        // Then someone allocates the receipt before approval...
        $sourceReceipt->update(['allocated_amount' => '500.00']);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/now exceeds source receipt/i');

        $this->service->post($refund, $this->user);
    }

    public function test_cannot_post_already_posted_refund(): void
    {
        $refund = $this->service->createDraft(
            customer: $this->customer, paymentMethod: $this->cashMethod,
            amount: '500.00', refundDate: CarbonImmutable::parse('2026-05-15'), createdBy: $this->user,
        );
        $this->service->post($refund, $this->user);

        $this->expectException(\DomainException::class);
        $this->service->post($refund->fresh(), $this->user);
    }

    public function test_reverse_creates_inverse_journal_batch(): void
    {
        $refund = $this->service->createDraft(
            customer: $this->customer, paymentMethod: $this->cashMethod,
            amount: '500.00', refundDate: CarbonImmutable::parse('2026-05-15'), createdBy: $this->user,
        );
        $this->service->post($refund, $this->user);

        $reversed = $this->service->reverse($refund->fresh(), $this->user, 'wrong customer');

        $this->assertSame(Refund::STATUS_REVERSED, $reversed->status);
        $this->assertNotNull($reversed->reversed_by_journal_batch_id);
        $this->assertSame('wrong customer', $reversed->reversal_reason);
    }

    public function test_cancel_only_works_on_draft(): void
    {
        $refund = $this->service->createDraft(
            customer: $this->customer, paymentMethod: $this->cashMethod,
            amount: '500.00', refundDate: CarbonImmutable::parse('2026-05-15'), createdBy: $this->user,
        );

        $this->service->cancel($refund, $this->user);
        $this->assertSoftDeleted('refunds', ['id' => $refund->id]);
    }

    public function test_cancel_rejects_posted_refund(): void
    {
        $refund = $this->service->createDraft(
            customer: $this->customer, paymentMethod: $this->cashMethod,
            amount: '500.00', refundDate: CarbonImmutable::parse('2026-05-15'), createdBy: $this->user,
        );
        $this->service->post($refund, $this->user);

        $this->expectException(\DomainException::class);
        $this->service->cancel($refund->fresh(), $this->user);
    }

    /* helper */
    protected function makePostedReceipt(Counterparty $customer, string $amount, string $allocatedAmount = '0'): Receipt
    {
        static $counter = 0;
        return Receipt::create([
            'organization_id'    => $this->org->id,
            'receipt_number'     => 'RCT-' . str_pad((string) (++$counter), 6, '0', STR_PAD_LEFT),
            'receipt_date'       => '2026-05-10',
            'counterparty_id'    => $customer->id,
            'currency_code'      => 'GHS', 'exchange_rate' => '1',
            'amount'             => $amount,
            'allocated_amount'   => $allocatedAmount,
            'status'             => Receipt::STATUS_POSTED,
            'is_backfilled'      => false,
            'reference_type'     => 'cash_receipt',
            'created_by_user_id' => $this->user->id,
        ]);
    }
}
