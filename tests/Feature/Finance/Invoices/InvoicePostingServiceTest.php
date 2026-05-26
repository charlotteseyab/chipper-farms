<?php

namespace Tests\Feature\Finance\Invoices;

use App\Models\Counterparty;
use App\Models\Finance\Account;
use App\Models\Finance\Invoice;
use App\Models\Finance\JournalBatch;
use App\Models\PaymentMethod;
use App\Services\Finance\Invoicing\InvoiceLineDraft;
use App\Services\Finance\Invoicing\InvoicePostingService;
use App\Services\Finance\Invoicing\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Finance\FinanceTestSetup;
use Tests\TestCase;

class InvoicePostingServiceTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    protected Counterparty $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFinanceFixtures();

        // Need vat control + suspense receipt accounts for postings to resolve.
        Account::create([
            'organization_id' => $this->org->id,
            'code' => '2210',
            'name' => 'VAT Control',
            'account_type' => 'liability',
            'currency_code' => 'GHS',
            'is_active' => true,
            'allow_manual_journal' => false,
        ]);
        Account::create([
            'organization_id' => $this->org->id,
            'code' => '1910',
            'name' => 'Suspense - Receipts',
            'account_type' => 'asset',
            'currency_code' => 'GHS',
            'is_active' => true,
            'allow_manual_journal' => false,
        ]);

        $this->customer = Counterparty::create([
            'organization_id' => $this->org->id,
            'name'            => 'Test Customer',
            'type'            => 'customer',
            'code'            => 'CUST-TEST',
            'currency_code'   => 'GHS',
        ]);
    }

    public function test_post_credit_invoice_dr_ar_cr_revenue(): void
    {
        $invoice = $this->makeDraft();
        $posted = $this->app->make(InvoicePostingService::class)->post($invoice, $this->user);

        $this->assertSame(Invoice::STATUS_POSTED, $posted->status);
        $this->assertNotNull($posted->journal_batch_id);
        $this->assertNotNull($posted->posted_at);

        $batch = JournalBatch::find($posted->journal_batch_id);
        $this->assertSame(JournalBatch::STATUS_POSTED, $batch->status);

        $journal = $batch->journals->first();
        $this->assertSame('500.00', (string) $journal->total_debit);
        $this->assertSame('500.00', (string) $journal->total_credit);

        // Debit hits AR (1110)
        $debitLine = $journal->lines->firstWhere('debit', '>', 0);
        $this->assertSame($this->arAccount->id, $debitLine->account_id);
        $this->assertSame('500.00', (string) $debitLine->debit);

        // Credit hits Revenue (4010)
        $creditLine = $journal->lines->firstWhere('credit', '>', 0);
        $this->assertSame($this->revenueAccount->id, $creditLine->account_id);
    }

    public function test_post_cash_invoice_dr_cash_cr_revenue(): void
    {
        $cashMethod = PaymentMethod::create([
            'organization_id' => $this->org->id,
            'code' => 'CASH',
            'name' => 'Cash',
            'is_cash' => true,
            'gl_account_hint' => 'CASH_ON_HAND',
            'is_active' => true,
        ]);

        $invoice = $this->makeDraft(['payment_method_id' => $cashMethod->id]);
        $this->assertTrue($invoice->is_cash_invoice);

        $posted = $this->app->make(InvoicePostingService::class)->post($invoice, $this->user);
        $journal = $posted->journalBatch->journals->first();

        // Debit hits Cash (1010), not AR (1110)
        $debitLine = $journal->lines->firstWhere('debit', '>', 0);
        $this->assertSame($this->cashAccount->id, $debitLine->account_id);
    }

    public function test_invoice_with_tax_creates_separate_tax_credit(): void
    {
        $service = $this->app->make(InvoiceService::class);
        $invoice = $service->createDraft(
            customer: $this->customer,
            headerData: ['invoice_date' => '2026-05-15', 'currency_code' => 'GHS'],
            lineDrafts: [
                new InvoiceLineDraft(description: 'X', quantity: '1', unitPrice: '100', taxRatePercent: '15'),
            ],
        );

        $posted = $this->app->make(InvoicePostingService::class)->post($invoice, $this->user);
        $journal = $posted->journalBatch->journals->first();

        // Three lines: AR (debit 115), Revenue (credit 100), VAT (credit 15)
        $this->assertSame(3, $journal->lines->count());

        $vatAccount = Account::where('code', '2210')->first();
        $vatLine = $journal->lines->firstWhere('account_id', $vatAccount->id);
        $this->assertNotNull($vatLine);
        $this->assertSame('15.00', (string) $vatLine->credit);
    }

    public function test_post_rejects_already_posted_invoice(): void
    {
        $invoice = $this->makeDraft();
        $this->app->make(InvoicePostingService::class)->post($invoice, $this->user);

        $this->expectException(\DomainException::class);
        $this->app->make(InvoicePostingService::class)->post($invoice->fresh(), $this->user);
    }

    public function test_idempotency_blocks_duplicate_posting_via_engine_unique(): void
    {
        $invoice = $this->makeDraft();
        $this->app->make(InvoicePostingService::class)->post($invoice, $this->user);

        // Forcing the invoice back to draft and re-posting → engine catches via UNIQUE.
        $invoice->update([
            'status' => Invoice::STATUS_DRAFT,
            'journal_batch_id' => null,
        ]);

        $this->expectException(\App\Finance\Exceptions\DuplicatePostingException::class);
        $this->app->make(InvoicePostingService::class)->post($invoice->fresh(), $this->user);
    }

    private function makeDraft(array $headerOverrides = []): Invoice
    {
        return $this->app->make(InvoiceService::class)->createDraft(
            customer: $this->customer,
            headerData: array_merge([
                'invoice_date' => '2026-05-15',
                'currency_code' => 'GHS',
            ], $headerOverrides),
            lineDrafts: [
                new InvoiceLineDraft(description: 'Test pig', quantity: '50', unitPrice: '10'),
            ],
        );
    }
}
