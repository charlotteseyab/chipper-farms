<?php

namespace App\Services\Finance\Invoicing;

use App\Finance\Drafts\JournalBatchDraft;
use App\Finance\Drafts\JournalDraft;
use App\Finance\Drafts\JournalLineDraft;
use App\Finance\Posting\PostingEngine;
use App\Finance\Resolvers\FinanceAccountResolver;
use App\Models\Finance\Invoice;
use App\Models\Finance\JournalPosting;
use App\Models\PigSale;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Converts an invoice in DRAFT status into a posted journal batch.
 *
 * Posting rules:
 *   For a CREDIT invoice (is_cash_invoice=false):
 *     Dr  AR account                            invoice.total
 *         Cr  Revenue account (per-line)            line_subtotal - line_discount
 *         Cr  Tax payable account                   tax_total       (if non-zero)
 *
 *   For a CASH invoice (is_cash_invoice=true):
 *     Dr  Cash account (resolved from payment_method)  invoice.total
 *         Cr  Revenue account (per-line)              line_subtotal - line_discount
 *         Cr  Tax payable account                     tax_total       (if non-zero)
 *
 * Account resolution:
 *   - Revenue: line.revenue_account_id || customer.category.revenue_account_id || '4010'
 *   - AR:      customer.category.ar_account_id || '1110'
 *   - Cash:    payment_method.gl_account_hint → resolver
 *   - Tax:     line.tax_code.gl_account_hint → resolver  (Phase 2 extension —
 *              for now: tax goes to a single 'VAT_CONTROL' hint)
 *
 * Idempotency:
 *   The journal_postings.idempotency_key is built from the invoice's
 *   identity: 'invoice_posted:Invoice:{id}:revenue'. Re-posting the same
 *   invoice raises DuplicatePostingException — caught by callers and
 *   treated as success.
 *
 * Errors:
 *   Any PostingException from the engine bubbles up. The caller is
 *   responsible for either (a) leaving the invoice in draft for retry,
 *   or (b) flipping it to a failure status. This service does NOT
 *   change the invoice's status itself — that's the controller's job
 *   so the user gets a clear error message.
 */
class InvoicePostingService
{
    public function __construct(
        protected PostingEngine $postingEngine,
        protected FinanceAccountResolver $accountResolver,
    ) {
    }

    /**
     * Post the invoice. Builds the JournalBatchDraft and hands to PostingEngine.
     * On success, links the invoice to the batch and updates status.
     *
     * @return Invoice  The fresh, posted invoice.
     */
    public function post(Invoice $invoice, User $postingUser): Invoice
    {
        if (! $invoice->canPost()) {
            throw new \DomainException(
                "Invoice #{$invoice->id} cannot be posted: status is '{$invoice->status}'"
            );
        }

        $invoice->load(['lines.taxCode', 'counterparty.category', 'paymentMethod']);

        $batchDraft = $this->buildBatchDraft($invoice);

        return DB::transaction(function () use ($invoice, $batchDraft, $postingUser) {
            $batch = $this->postingEngine->post($batchDraft, $postingUser);

            $invoice->update([
                'status'           => Invoice::STATUS_POSTED,
                'journal_batch_id' => $batch->id,
                'posted_by_user_id'=> $postingUser->id,
                'posted_at'        => now(),
            ]);

            // If this invoice is for a pig sale and the legacy is_posted flag
            // exists on pig_sales, flip it (operations dashboards depend on it).
            if ($invoice->source_type === PigSale::class && $invoice->source_id) {
                PigSale::where('id', $invoice->source_id)->update([
                    'is_posted' => true,
                    'posted_at' => now(),
                ]);
            }

            return $invoice->fresh(['lines', 'journalBatch']);
        });
    }

    /**
     * Build the JournalBatchDraft for this invoice.
     */
    protected function buildBatchDraft(Invoice $invoice): JournalBatchDraft
    {
        $lineDrafts = [];

        // Resolve revenue accounts per line.
        foreach ($invoice->lines as $line) {
            $revenueAccountId = $this->resolveRevenueAccountId($invoice, $line);
            $lineNetCredit = bcsub((string) $line->line_subtotal, (string) $line->line_discount_amount, 2);

            if (bccomp($lineNetCredit, '0', 2) === 0) {
                continue;  // Zero-revenue line, skip.
            }

            $lineDrafts[] = JournalLineDraft::credit(
                accountId: $revenueAccountId,
                amount:    $lineNetCredit,
                description: $invoice->invoice_number . ' — ' . $line->description,
                dimensions: [
                    'counterparty_id'  => $invoice->counterparty_id,
                    'sales_channel_id' => $line->sales_channel_id,
                    'cost_centre_id'   => $line->cost_centre_id,
                    'tax_code_id'      => $line->tax_code_id,
                    'source_type'      => Invoice::class,
                    'source_id'        => $invoice->id,
                    'reference'        => $invoice->invoice_number,
                ],
            );
        }

        // Tax credit (single line for the invoice's total tax).
        if (bccomp((string) $invoice->tax_total, '0', 2) > 0) {
            $taxAccountId = $this->resolveTaxAccountId($invoice);
            $lineDrafts[] = JournalLineDraft::credit(
                accountId: $taxAccountId,
                amount:    (string) $invoice->tax_total,
                description: $invoice->invoice_number . ' — VAT',
                dimensions: [
                    'counterparty_id' => $invoice->counterparty_id,
                    'source_type'     => Invoice::class,
                    'source_id'       => $invoice->id,
                    'reference'       => $invoice->invoice_number,
                ],
            );
        }

        // Debit side: AR (credit invoice) or Cash (cash invoice).
        $debitAccountId = $invoice->is_cash_invoice
            ? $this->resolveCashAccountId($invoice)
            : $this->resolveArAccountId($invoice);

        $debitDescription = ($invoice->is_cash_invoice ? 'Cash receipt: ' : 'AR: ') . $invoice->invoice_number;

        $lineDrafts[] = JournalLineDraft::debit(
            accountId: $debitAccountId,
            amount:    (string) $invoice->total,
            description: $debitDescription,
            dimensions: [
                'counterparty_id'   => $invoice->counterparty_id,
                'payment_method_id' => $invoice->payment_method_id,
                'source_type'       => Invoice::class,
                'source_id'         => $invoice->id,
                'reference'         => $invoice->invoice_number,
            ],
        );

        $journal = new JournalDraft(
            journalType:  $invoice->is_cash_invoice ? 'cash_sale' : 'sales_invoice',
            lines:        $lineDrafts,
            reference:    $invoice->invoice_number,
            description:  $invoice->description ?: ('Invoice ' . $invoice->invoice_number),
            currencyCode: $invoice->currency_code,
            exchangeRate: (string) $invoice->exchange_rate,
            sourceType:   Invoice::class,
            sourceId:     $invoice->id,
        );

        return new JournalBatchDraft(
            batchType:        'auto_invoice',
            postingDate:      CarbonImmutable::instance($invoice->invoice_date),
            journals:         [$journal],
            description:      'Invoice ' . $invoice->invoice_number,
            sourceModule:     'invoicing',
            sourceType:       Invoice::class,
            sourceId:         $invoice->id,
            idempotencyKey:   JournalPosting::buildIdempotencyKey(
                'invoice_posted',
                Invoice::class,
                $invoice->id,
                JournalPosting::PURPOSE_REVENUE,
            ),
            postingPurpose:   JournalPosting::PURPOSE_REVENUE,
            subscriberClass:  static::class,
        );
    }

    protected function resolveRevenueAccountId(Invoice $invoice, $line): int
    {
        if ($line->revenue_account_id) {
            return (int) $line->revenue_account_id;
        }

        $categoryAccountId = $invoice->counterparty?->category?->revenue_account_id;
        if ($categoryAccountId) {
            return (int) $categoryAccountId;
        }

        return $this->accountResolver->byCode('4010')->id;
    }

    protected function resolveArAccountId(Invoice $invoice): int
    {
        $categoryAccountId = $invoice->counterparty?->category?->ar_account_id;
        if ($categoryAccountId) {
            return (int) $categoryAccountId;
        }

        return $this->accountResolver->byCode('1110')->id;
    }

    protected function resolveCashAccountId(Invoice $invoice): int
    {
        $pm = $invoice->paymentMethod;
        if ($pm && $pm->gl_account_hint) {
            try {
                return $this->accountResolver->byHint($pm->gl_account_hint)->id;
            } catch (\App\Finance\Resolvers\MissingAccountException) {
                // Fall through.
            }
        }
        return $this->accountResolver->byHint('SUSPENSE_RECEIPT')->id;
    }

    protected function resolveTaxAccountId(Invoice $invoice): int
    {
        // Phase 2b: single VAT_CONTROL account. Phase 3+ will route per
        // tax_code.gl_account_hint for NHIL/GETFund/COVID etc.
        return $this->accountResolver->byHint('VAT_CONTROL')->id;
    }
}
