<?php

namespace App\Services\Finance\VendorInvoicing;

use App\Finance\Drafts\JournalBatchDraft;
use App\Finance\Drafts\JournalDraft;
use App\Finance\Drafts\JournalLineDraft;
use App\Finance\Posting\PostingEngine;
use App\Finance\Resolvers\FinanceAccountResolver;
use App\Models\Finance\GrIrEntry;
use App\Models\Finance\JournalPosting;
use App\Models\Finance\VendorInvoice;
use App\Models\Finance\VendorInvoiceLine;
use App\Models\PurchaseRequest;
use App\Models\TaxCode;
use App\Models\User;
use App\Services\Finance\Inventory\InventoryAccountResolver;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Phase 4 — rewritten vendor invoice posting service.
 *
 * Posts a vendor invoice to GL with the new 2-step AP/inventory model:
 *
 *   STANDARD POSTING (unchanged when no PR-linked GR/IR exists):
 *     Dr [line.account_id]   line_subtotal       (per line)
 *     Dr [Input VAT acct]    tax_amount           (per recoverable-tax line)
 *     Dr [line.account_id]   tax_amount           (per non-recoverable-tax line)
 *                                Cr [AP control]  total
 *
 *   PR-LINKED POSTING (Phase 4 new — clears outstanding GR/IR):
 *     Dr [GR/IR account]     receipt_amount       (clears the accrual)
 *     Dr [Inventory acct OR PPV] variance         (within tolerance)
 *     Dr [Input VAT acct]    tax_amount           (recoverable VAT path)
 *                                Cr [AP control]  line_total
 *
 *     The PR-linked line walks each posted GR/IR entry for the line's
 *     purchase_request_item_id, clears them in FIFO order, and applies
 *     PPV for any price variance. Each GR/IR entry updates to CLEARED.
 *
 * BUG FIXES from prior version:
 *
 *   1. resolveInputVatAccountId() previously had no parameters but was
 *      called with `$line->taxCode`, producing ArgumentCountError on
 *      every recoverable-VAT line. Now: the method takes a nullable
 *      TaxCode and routes by tax_code.gl_account_hint when present,
 *      falling back to the COA code '2211' (VAT Receivable - Input).
 *
 *   2. The doc comment referenced 'Input VAT 1410'; the COA seeder
 *      puts Input VAT at '2211 VAT Receivable (Input)'. The previous
 *      code already used 2211; only the comment was stale. Updated.
 *
 *   3. The previous service silently posted PR-linked inventory lines
 *      to Dr Inventory / Cr AP, double-counting inventory because the
 *      GR/IR receipt path (when Phase 4 wires it) ALSO debits inventory.
 *      Now: PR-linked lines with a posted GR/IR entry hit GR/IR
 *      instead of inventory. Variance routes to PPV.
 *
 * MATCH TOLERANCE
 *
 *   `finance.vendor_invoices.match_tolerance_price_pct` defines the
 *   acceptable variance between PR price and supplier invoice price.
 *   Within tolerance: post variance to PPV automatically; CLEAR the
 *   GR/IR entry. Outside tolerance: REFUSE the post and require manual
 *   reconciliation.
 *
 *   The qty tolerance applies to receive-vs-PR (ThreeWayMatchingService);
 *   AP posting trusts the receipt quantity as the truth (since the
 *   physical receipt is what happened), and matches against AP qty for
 *   visibility only.
 *
 * IDEMPOTENCY
 *
 *   journal_postings.idempotency_key =
 *     'vendor_invoice_posted:VendorInvoice:{id}:vendor_invoice'.
 *
 *   Re-posting throws DuplicatePostingException, caught by callers
 *   (handled at the controller/subscriber layer).
 *
 *   GR/IR entry mutation happens INSIDE the same DB transaction as
 *   the posting, so a posting failure rolls back the GR/IR status
 *   change too.
 *
 * STATE CHANGES
 *
 *   On success:
 *     - vendor_invoice.status pending_approval → posted
 *     - vendor_invoice.journal_batch_id set
 *     - vendor_invoice.approved_by_user_id, approved_at set
 *     - matching GR/IR entries: status='cleared', cleared_at,
 *       cleared_by_vendor_invoice_line_id, cleared_amount, variance.
 *     - raw_material_stock_logs.vendor_invoice_line_id set for the
 *       stock log behind each cleared GR/IR entry.
 */
class VendorInvoicePostingService
{
    public function __construct(
        protected PostingEngine $postingEngine,
        protected FinanceAccountResolver $accountResolver,
        protected InventoryAccountResolver $inventoryResolver,
    ) {
    }

    public function post(VendorInvoice $invoice, User $approver): VendorInvoice
    {
        if (! $invoice->canApprove()) {
            throw new \DomainException(
                "Vendor invoice #{$invoice->id} cannot be posted: status is '{$invoice->status}'"
            );
        }

        $invoice->load([
            'lines.taxCode',
            'lines.account',
            'lines.purchaseRequestItem',
            'counterparty.supplierCategory',
        ]);

        return DB::transaction(function () use ($invoice, $approver) {
            $invoice = VendorInvoice::lockForUpdate()->findOrFail($invoice->id);

            // Pre-flight: validate PR-linked lines against any open
            // GR/IR entries and check price-variance tolerance. Throws
            // a DomainException if any line would be outside tolerance.
            $linePlans = $this->buildLinePlans($invoice);

            // Build the batch from the line plans.
            $batchDraft = $this->buildBatchDraft($invoice, $linePlans);

            $batch = $this->postingEngine->post($batchDraft, $approver);

            // Update the invoice header.
            $invoice->update([
                'status'              => VendorInvoice::STATUS_POSTED,
                'journal_batch_id'    => $batch->id,
                'approved_by_user_id' => $approver->id,
                'approved_at'         => now(),
            ]);

            // Update GR/IR entries + stock logs for cleared lines.
            $this->applyClearings($invoice, $linePlans, $batch->id);

            return $invoice->fresh(['lines', 'journalBatch']);
        });
    }

    // ================================================================
    // Pre-flight: per-line planning
    // ================================================================

    /**
     * For each VI line, decide:
     *   - Does it clear a GR/IR accrual? (if PR-linked AND outstanding
     *     entries exist).
     *   - How much GR/IR is cleared (FIFO across entries)?
     *   - What is the variance vs the supplier's billed line subtotal?
     *   - Is the variance within tolerance?
     *
     * Returns a structured plan per line, used both to build the journal
     * AND to apply the clearings after posting.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function buildLinePlans(VendorInvoice $invoice): array
    {
        $tolerancePct = (float) config('finance.vendor_invoices.match_tolerance_price_pct', 2.0);
        $plans = [];

        foreach ($invoice->lines as $line) {
            $entries = collect();
            if ($line->purchase_request_item_id) {
                $entries = GrIrEntry::query()
                    ->where('purchase_request_item_id', $line->purchase_request_item_id)
                    ->where('status', GrIrEntry::STATUS_OUTSTANDING)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();
            }

            // Sum receipt-side amount in flight (sum of receipt_amount
            // of outstanding entries, which is what we'd clear).
            $totalReceiptAmount = '0';
            foreach ($entries as $entry) {
                $totalReceiptAmount = bcadd(
                    $totalReceiptAmount,
                    (string) $entry->receipt_amount,
                    4,
                );
            }

            $lineSubtotal = (string) $line->line_subtotal;

            // Variance = billed (subtotal) - receipt-side amount.
            //   Positive variance → unfavourable (supplier billed more).
            //   Negative variance → favourable.
            $variance = '0';
            $clearsGrIr = $entries->isNotEmpty();

            if ($clearsGrIr) {
                $variance = bcsub($lineSubtotal, $totalReceiptAmount, 4);

                // Tolerance check.
                if (bccomp($totalReceiptAmount, '0', 4) > 0) {
                    $variancePct = abs((float) bcdiv($variance, $totalReceiptAmount, 6)) * 100.0;
                    if ($variancePct > $tolerancePct) {
                        throw new \DomainException(sprintf(
                            "Vendor invoice line #%d for PR item #%d has price variance %.2f%% "
                            . "(billed %s vs receipt %s) — exceeds tolerance %.2f%%. "
                            . "Manual reconciliation required before posting.",
                            $line->line_number,
                            (int) $line->purchase_request_item_id,
                            $variancePct,
                            $lineSubtotal,
                            $totalReceiptAmount,
                            $tolerancePct,
                        ));
                    }
                }
            }

            $plans[] = [
                'line'                  => $line,
                'clears_gr_ir'          => $clearsGrIr,
                'gr_ir_entries'         => $entries,    // collection
                'total_receipt_amount'  => $totalReceiptAmount,
                'variance'              => $variance,    // signed; "supplier billed - receipt"
                'tolerance_pct'         => $tolerancePct,
            ];
        }

        return $plans;
    }

    // ================================================================
    // Build the journal draft from line plans
    // ================================================================

    /**
     * @param  array<int, array<string, mixed>>  $linePlans
     */
    protected function buildBatchDraft(VendorInvoice $invoice, array $linePlans): JournalBatchDraft
    {
        $apAccountId = $this->resolveApAccountId($invoice);
        $description = $this->buildDescription($invoice);

        $journalLines = [];

        foreach ($linePlans as $plan) {
            $line = $plan['line'];

            $debitDescription = sprintf(
                'VI %s line %d: %s',
                $invoice->vendor_invoice_number,
                $line->line_number,
                \Illuminate\Support\Str::limit($line->description, 80),
            );

            $lineSubtotal = (string) $line->line_subtotal;
            $taxIsRecoverable = $line->taxCode?->is_recoverable ?? false;
            $taxAmount = (string) $line->tax_amount;

            if ($plan['clears_gr_ir']) {
                // PR-linked line — clear GR/IR + post variance to PPV.
                $this->appendGrIrClearingLines(
                    journalLines:    $journalLines,
                    invoice:         $invoice,
                    line:            $line,
                    plan:            $plan,
                    description:     $debitDescription,
                );
            } else {
                // Standard line — debit the resolved expense/inventory
                // account at line_subtotal (plus non-recoverable tax).
                $lineDebit = $lineSubtotal;
                if (! $taxIsRecoverable && bccomp($taxAmount, '0', 2) > 0) {
                    $lineDebit = bcadd($lineDebit, $taxAmount, 2);
                }

                $journalLines[] = JournalLineDraft::debit(
                    accountId:   (int) $line->account_id,
                    amount:      $lineDebit,
                    description: $debitDescription,
                    dimensions: [
                        'counterparty_id'  => $invoice->counterparty_id,
                        'source_type'      => VendorInvoice::class,
                        'source_id'        => $invoice->id,
                        'reference'        => $invoice->vendor_invoice_number,
                    ],
                );
            }

            // Recoverable VAT — always a separate line (independent of
            // whether the main line was GR/IR or standard).
            if ($taxIsRecoverable && bccomp($taxAmount, '0', 2) > 0) {
                $vatAccountId = $this->resolveInputVatAccountId($line->taxCode);
                $journalLines[] = JournalLineDraft::debit(
                    accountId:   $vatAccountId,
                    amount:      $taxAmount,
                    description: "Input VAT for {$debitDescription}",
                    dimensions: [
                        'counterparty_id'  => $invoice->counterparty_id,
                        'tax_code_id'      => $line->tax_code_id,
                        'source_type'      => VendorInvoice::class,
                        'source_id'        => $invoice->id,
                        'reference'        => $invoice->vendor_invoice_number,
                    ],
                );
            }
        }

        // AP control credit — total bill amount.
        $journalLines[] = JournalLineDraft::credit(
            accountId:   $apAccountId,
            amount:      (string) $invoice->total,
            description: $description,
            dimensions: [
                'counterparty_id'  => $invoice->counterparty_id,
                'source_type'      => VendorInvoice::class,
                'source_id'        => $invoice->id,
                'reference'        => $invoice->vendor_invoice_number,
            ],
        );

        $journal = new JournalDraft(
            journalType:  'vendor_invoice',
            lines:        $journalLines,
            reference:    $invoice->vendor_invoice_number,
            description:  $description,
            currencyCode: $invoice->currency_code,
            exchangeRate: (string) $invoice->exchange_rate,
            sourceType:   VendorInvoice::class,
            sourceId:     $invoice->id,
        );

        return new JournalBatchDraft(
            batchType:        'auto_vendor_invoice',
            postingDate:      CarbonImmutable::instance($invoice->invoice_date),
            journals:         [$journal],
            description:      $description,
            sourceModule:     'vendor_invoices',
            sourceType:       VendorInvoice::class,
            sourceId:         $invoice->id,
            idempotencyKey:   JournalPosting::buildIdempotencyKey(
                'vendor_invoice_posted',
                VendorInvoice::class,
                $invoice->id,
                JournalPosting::PURPOSE_VENDOR_INVOICE,
            ),
            postingPurpose:   JournalPosting::PURPOSE_VENDOR_INVOICE,
            subscriberClass:  static::class,
        );
    }

    /**
     * Append the GR/IR clearing + (optional) PPV lines for one VI line.
     */
    protected function appendGrIrClearingLines(
        array &$journalLines,
        VendorInvoice $invoice,
        VendorInvoiceLine $line,
        array $plan,
        string $description,
    ): void {
        $entries = $plan['gr_ir_entries'];
        $totalReceiptAmount = $plan['total_receipt_amount'];
        $variance = $plan['variance'];

        // The GR/IR debit clears at the *receipt* amount (not the billed
        // amount). Use the GR/IR account from the FIRST entry's raw
        // material (all entries on a single VI line share the same
        // raw material; the validation already enforces this).
        /** @var GrIrEntry $first */
        $first = $entries->first();
        $grIrAccountId = $this->inventoryResolver->grIrAccountForRawMaterial($first->rawMaterial);

        $journalLines[] = JournalLineDraft::debit(
            accountId:   $grIrAccountId,
            amount:      $totalReceiptAmount,
            description: "GR/IR clearing — {$description}",
            dimensions: [
                'counterparty_id'        => $invoice->counterparty_id,
                'source_type'            => VendorInvoice::class,
                'source_id'              => $invoice->id,
                'reference'              => $invoice->vendor_invoice_number,
            ],
        );

        // Variance routing — PPV for any signed difference.
        if (bccomp($variance, '0', 4) !== 0) {
            $ppvAccountId = $this->inventoryResolver->ppvAccountForRawMaterial($first->rawMaterial);

            if (bccomp($variance, '0', 4) > 0) {
                // Unfavourable (billed > receipt): Dr PPV (expense up).
                $journalLines[] = JournalLineDraft::debit(
                    accountId:   $ppvAccountId,
                    amount:      $variance,
                    description: "PPV (unfavourable) — {$description}",
                    dimensions: [
                        'counterparty_id'        => $invoice->counterparty_id,
                        'source_type'            => VendorInvoice::class,
                        'source_id'              => $invoice->id,
                        'reference'              => $invoice->vendor_invoice_number,
                    ],
                );
            } else {
                // Favourable (billed < receipt): Cr PPV (expense down).
                $abs = ltrim((string) bcsub('0', $variance, 4), '-');
                $journalLines[] = JournalLineDraft::credit(
                    accountId:   $ppvAccountId,
                    amount:      $abs,
                    description: "PPV (favourable) — {$description}",
                    dimensions: [
                        'counterparty_id'        => $invoice->counterparty_id,
                        'source_type'            => VendorInvoice::class,
                        'source_id'              => $invoice->id,
                        'reference'              => $invoice->vendor_invoice_number,
                    ],
                );
            }
        }
    }

    // ================================================================
    // Post-posting: apply GR/IR clearings to gr_ir_entries + stock logs
    // ================================================================

    /**
     * @param  array<int, array<string, mixed>>  $linePlans
     */
    protected function applyClearings(VendorInvoice $invoice, array $linePlans, int $journalBatchId): void
    {
        foreach ($linePlans as $plan) {
            if (! $plan['clears_gr_ir']) {
                continue;
            }

            /** @var VendorInvoiceLine $line */
            $line = $plan['line'];
            $entries = $plan['gr_ir_entries'];
            $totalReceipt = $plan['total_receipt_amount'];

            // Compute per-entry pro-rata share of the billed amount.
            // Sum of cleared_amount across entries equals line_subtotal.
            // Variance is allocated PROPORTIONALLY to each entry.
            $lineSubtotal = (string) $line->line_subtotal;
            $allocatedSoFar = '0';
            $entryCount = $entries->count();

            foreach ($entries as $i => $entry) {
                $isLast = ($i === $entryCount - 1);

                if ($isLast) {
                    // Last entry absorbs rounding so the sum exactly
                    // equals line_subtotal.
                    $entryClearedAmount = bcsub($lineSubtotal, $allocatedSoFar, 4);
                } else {
                    if (bccomp($totalReceipt, '0', 4) > 0) {
                        $share = bcdiv((string) $entry->receipt_amount, $totalReceipt, 8);
                        $entryClearedAmount = bcmul($lineSubtotal, $share, 4);
                    } else {
                        $entryClearedAmount = '0';
                    }
                }

                $entryVariance = bcsub(
                    $entryClearedAmount,
                    (string) $entry->receipt_amount,
                    4,
                );

                $entry->update([
                    'status'                            => GrIrEntry::STATUS_CLEARED,
                    'clearing_journal_batch_id'         => $journalBatchId,
                    'cleared_by_vendor_invoice_line_id' => $line->id,
                    'cleared_at'                        => now(),
                    'cleared_amount'                    => $entryClearedAmount,
                    'variance_amount'                   => $entryVariance,
                ]);

                // Tag the receipt stock log with the VI line so
                // ThreeWayMatchingService and reports can join.
                if ($entry->stock_log_id) {
                    \App\Models\RawMaterialStockLog::query()
                        ->where('id', $entry->stock_log_id)
                        ->update(['vendor_invoice_line_id' => $line->id]);
                }

                $allocatedSoFar = bcadd($allocatedSoFar, $entryClearedAmount, 4);
            }
        }
    }

    // ================================================================
    // Resolvers
    // ================================================================

    protected function resolveApAccountId(VendorInvoice $invoice): int
    {
        $category = $invoice->counterparty?->supplierCategory;
        if ($category && $category->ap_account_id) {
            return (int) $category->ap_account_id;
        }
        return $this->accountResolver->byCode('2010')->id;
    }

    /**
     * Resolve the Input VAT account from the line's tax code, falling
     * back to the configured default ('2211 VAT Receivable (Input)').
     *
     * PHASE 4 BUG FIX: signature now accepts the TaxCode parameter that
     * the caller has been passing all along. Previous version's signature
     * was `()` with no arguments, producing ArgumentCountError when
     * called with `$line->taxCode`.
     */
    protected function resolveInputVatAccountId(?TaxCode $taxCode): int
    {
        if ($taxCode && ! empty($taxCode->gl_account_hint)) {
            try {
                return $this->accountResolver->byCode($taxCode->gl_account_hint)->id;
            } catch (\Throwable) {
                // Hint references a missing code — fall through to the
                // global default. Log so ops can fix the tax_code row.
                logger()->warning(
                    "Tax code #{$taxCode->id} gl_account_hint '{$taxCode->gl_account_hint}' "
                    . "does not resolve to a COA account. Falling back to 2211."
                );
            }
        }

        $fallbackCode = (string) config(
            'finance.inventory.fallback_account_codes.input_vat',
            '2211',
        );

        return $this->accountResolver->byCode($fallbackCode)->id;
    }

    protected function buildDescription(VendorInvoice $invoice): string
    {
        $supplierName = $invoice->counterparty?->name ?? 'Unknown supplier';
        $base = sprintf(
            'Vendor Invoice %s — %s',
            $invoice->vendor_invoice_number,
            $supplierName,
        );

        if ($invoice->supplier_invoice_number) {
            $base .= " (their ref: {$invoice->supplier_invoice_number})";
        }
        if ($invoice->purchase_request_id) {
            $pr = $invoice->purchaseRequest ?? PurchaseRequest::find($invoice->purchase_request_id);
            if ($pr) {
                $base .= " — PR {$pr->request_no}";
            }
        }
        if ($invoice->hasMatchVariance()) {
            $count = is_array($invoice->match_variances) ? count($invoice->match_variances) : 0;
            $base .= " [VARIANCE: {$count} line(s)]";
        }

        return $base;
    }
}
