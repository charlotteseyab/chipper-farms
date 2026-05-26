<?php

namespace App\Services\Finance\Payments;

use App\Finance\Drafts\JournalBatchDraft;
use App\Finance\Drafts\JournalDraft;
use App\Finance\Drafts\JournalLineDraft;
use App\Finance\Posting\PostingEngine;
use App\Finance\Resolvers\FinanceAccountResolver;
use App\Models\Finance\JournalPosting;
use App\Models\Finance\Payment;
use App\Models\Finance\PaymentAllocation;
use App\Models\Finance\VendorInvoice;
use App\Models\User;
use App\Services\Finance\JournalReversalService;
use App\Services\Finance\BankReconciliation\BankReconciliationService;
use App\Services\Finance\WithholdingCertificates\WhtCertificateService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Manages payment allocations against vendor invoices.
 *
 * Allocate flow:
 *   1. Lock both Payment and VendorInvoice rows for update.
 *   2. Validate: both posted, same org, same counterparty, same currency,
 *      amount <= vi.balance_due AND amount - wht_slice <= payment.unallocated.
 *   3. Compute WHT slice via WithholdingCalculator.
 *   4. Insert PaymentAllocation row (status=active).
 *   5. Post the AP-relief journal:
 *        Dr  AP control       allocation.amount    (full gross)
 *            Cr Suspense Payment    (amount - wht)  (clears suspense by net cash)
 *            Cr WHT Payable          wht            (only if wht > 0)
 *   6. Refresh aggregates.
 *
 * The journal is balanced: AP debit equals (suspense credit + WHT credit)
 * which equals (amount - wht) + wht = amount.
 *
 * Why a single 3-line journal?
 *   This is the journal Phase 3b's docblock specified for AP settlement
 *   with WHT, just with the cash-side replaced by Suspense (because cash
 *   already left when the payment was created — see PaymentService).
 *   AP clears by the gross, the WHT obligation is recognised, and
 *   suspense closes by the net cash that "actually" flowed to this VI.
 *
 * Reversal:
 *   reverse() reverses the journal via JournalReversalService and flips
 *   the allocation status. Aggregates recompute.
 *
 * Auto-allocate FIFO:
 *   Walks the supplier's open VIs in invoice_date asc order, allocating
 *   min(payment.unallocated_net + wht_to_be_added, vi.balance_due) until
 *   the payment's net cash is exhausted or no more VIs remain.
 *
 *   The arithmetic for "how much to allocate to this VI" is non-trivial
 *   when WHT is involved:
 *     - payment has X unallocated net (cash to attribute)
 *     - vi has balance Y, withholding rate ratio r = wht_total / total
 *     - if we allocate gross G to this VI:
 *         WHT slice = G * r   (proportional)
 *         net consumed = G - G*r = G*(1-r)
 *     - to consume X net, we need G = X / (1 - r)
 *     - but G can't exceed Y, so G = min(Y, X / (1-r))
 *
 *   The implementation handles this with bcmath.
 *
 * Idempotency:
 *   Each allocation's journal carries the idempotency key
 *   'allocation_posted:PaymentAllocation:{id}:withholding_tax'. Re-calling
 *   the service for the same allocation row throws DuplicatePostingException.
 */
class PaymentAllocationService
{
    public function __construct(
        protected PostingEngine $postingEngine,
        protected FinanceAccountResolver $accountResolver,
        protected WithholdingCalculator $withholdingCalculator,
        protected JournalReversalService $journalReversal,
        protected WhtCertificateService $whtCertificateService,
        protected BankReconciliationService $bankReconciliationService,
    ) {
    }

    /**
     * Allocate `amount` (gross AP relief) of `payment` against `invoice`.
     *
     * @param  string  $amount  GROSS amount paid down on the AP control
     *                          for this VI. The actual cash credit to
     *                          suspense will be (amount - withholding).
     */
    public function allocate(
        Payment $payment,
        VendorInvoice $invoice,
        string $amount,
        User $createdBy,
        ?\DateTimeInterface $allocationDate = null,
        ?string $notes = null,
    ): PaymentAllocation {
        if (bccomp($amount, '0', 2) <= 0) {
            throw new \InvalidArgumentException("Allocation amount must be > 0, got {$amount}");
        }

        return DB::transaction(function () use (
            $payment, $invoice, $amount, $createdBy, $allocationDate, $notes
        ) {
            $payment = Payment::lockForUpdate()->findOrFail($payment->id);
            $invoice = VendorInvoice::lockForUpdate()->findOrFail($invoice->id);

            if (! $payment->isPosted()) {
                throw new \DomainException(
                    "Cannot allocate from payment {$payment->payment_number} in status '{$payment->status}'"
                );
            }
            if (! $invoice->isPosted()) {
                throw new \DomainException(
                    "Cannot allocate to vendor invoice {$invoice->vendor_invoice_number} in status '{$invoice->status}'"
                );
            }

            if ($payment->organization_id !== $invoice->organization_id) {
                throw new \DomainException('Payment and vendor invoice belong to different organisations');
            }

            if ($payment->counterparty_id !== $invoice->counterparty_id) {
                throw new \DomainException(
                    "Payment counterparty does not match vendor invoice counterparty " .
                    "({$payment->counterparty_id} vs {$invoice->counterparty_id})"
                );
            }

            if ($payment->currency_code !== $invoice->currency_code) {
                throw new \DomainException(
                    "Currency mismatch: payment {$payment->currency_code} vs invoice {$invoice->currency_code}. " .
                    "Multi-currency allocation not supported until Phase 5."
                );
            }

            // Amount cannot exceed VI balance due.
            if (bccomp($amount, (string) $invoice->balance_due, 2) > 0) {
                throw new \DomainException(
                    "Allocation amount {$amount} exceeds vendor invoice {$invoice->vendor_invoice_number} balance due {$invoice->balance_due}"
                );
            }

            // Compute WHT slice.
            $alreadyWithheld = (string) PaymentAllocation::query()
                ->where('vendor_invoice_id', $invoice->id)
                ->where('status', PaymentAllocation::STATUS_ACTIVE)
                ->sum('withholding_amount');

            $whtSlice = $this->withholdingCalculator->slice($invoice, $amount, $alreadyWithheld);

            // Net cash consumed from suspense = amount - WHT slice.
            $netCashConsumed = bcsub($amount, $whtSlice, 2);

            // Validate: payment's unallocated NET cash >= netCashConsumed.
            $paymentNetUnallocated = $payment->unallocatedAmount();
            if (bccomp($netCashConsumed, $paymentNetUnallocated, 2) > 0) {
                throw new \DomainException(
                    "Allocation requires {$netCashConsumed} net cash but payment {$payment->payment_number} " .
                    "has only {$paymentNetUnallocated} unallocated. " .
                    "(Allocation gross {$amount}, WHT slice {$whtSlice}, net needed {$netCashConsumed}.)"
                );
            }

            $allocation = PaymentAllocation::create([
                'organization_id'     => $payment->organization_id,
                'payment_id'          => $payment->id,
                'vendor_invoice_id'   => $invoice->id,
                'amount'              => $amount,
                'withholding_amount'  => $whtSlice,
                'allocation_date'     => $allocationDate
                    ? CarbonImmutable::instance($allocationDate)->toDateString()
                    : (string) $payment->payment_date,
                'status'              => PaymentAllocation::STATUS_ACTIVE,
                'created_by_user_id'  => $createdBy->id,
                'notes'               => $notes,
            ]);

            $batch = $this->postAllocationJournal($allocation, $createdBy);
            $allocation->update(['withholding_journal_batch_id' => $batch->id]);

            $this->refreshPaymentAggregates($payment);
            $this->refreshVendorInvoiceAggregates($invoice);

            return $allocation->fresh();
        });
    }

    public function reverse(
        PaymentAllocation $allocation,
        User $reverser,
        string $reason,
    ): PaymentAllocation {
        return DB::transaction(function () use ($allocation, $reverser, $reason) {
            $allocation = PaymentAllocation::lockForUpdate()->findOrFail($allocation->id);

            if (! $allocation->isActive()) {
                throw new \DomainException(
                    "Cannot reverse allocation #{$allocation->id} — status is '{$allocation->status}'"
                );
            }

                        // Phase 3d: refuse if any issued WHT certificate covers this allocation.
            // The certificate has already been filed with GRA — silent reversal
            // would create an audit-trail mismatch between filed totals and
            // current allocation set.
            $blockingCerts = $this->whtCertificateService->issuedCertificatesCovering($allocation);
            if (! empty($blockingCerts)) {
                $certNumbers = array_map(fn ($c) => $c->certificate_number, $blockingCerts);
                throw new \DomainException(
                    "Cannot reverse allocation #{$allocation->id} — it contributes to "
                    . count($blockingCerts) . " issued WHT certificate(s): "
                    . implode(', ', $certNumbers)
                    . '. Void the certificate(s) first if you must reverse.'
                );
            }

                        // Phase 3e: refuse if the parent payment is part of a
            // closed (reconciled or flagged) bank reconciliation.
            // Reconciliation locks are released by reopening the
            // reconciliation, not by reversing the underlying payment.
            $parentPayment = $allocation->payment;
            if ($parentPayment) {
                $lockingRecons = $this->bankReconciliationService->lockingReconciliations(
                    \App\Models\Finance\BankStatementLine::MATCHED_TYPE_PAYMENT,
                    (int) $parentPayment->id,
                );
                if (! empty($lockingRecons)) {
                    $recNumbers = array_map(fn ($r) => $r->reconciliation_number, $lockingRecons);
                    throw new \DomainException(
                        "Cannot reverse allocation #{$allocation->id} — its parent "
                        . "payment {$parentPayment->payment_number} is part of "
                        . "closed bank reconciliation(s): " . implode(', ', $recNumbers)
                        . '. Reopen the reconciliation first.'
                    );
                }
            }

            if ($allocation->withholding_journal_batch_id) {
                $reversalBatch = $this->journalReversal->reverse(
                    original: $allocation->withholdingJournalBatch,
                    reverser: $reverser,
                    reason:   "Reverse allocation #{$allocation->id}: {$reason}",
                );
                $allocation->reversed_by_journal_batch_id = $reversalBatch->id;
            }

            $allocation->update([
                'status'                       => PaymentAllocation::STATUS_REVERSED,
                'reversed_by_user_id'          => $reverser->id,
                'reversed_at'                  => now(),
                'reversal_reason'              => $reason,
                'reversed_by_journal_batch_id' => $allocation->reversed_by_journal_batch_id,
            ]);

            $payment = Payment::lockForUpdate()->find($allocation->payment_id);
            $invoice = VendorInvoice::lockForUpdate()->find($allocation->vendor_invoice_id);
            if ($payment) $this->refreshPaymentAggregates($payment);
            if ($invoice) $this->refreshVendorInvoiceAggregates($invoice);

            return $allocation->fresh();
        });
    }

    /**
     * Auto-allocate the payment's unallocated NET cash across the supplier's
     * open VIs in oldest-first order.
     */
    public function autoAllocateFifo(Payment $payment, User $createdBy): array
    {
        $allocations = [];
        $iterations = 0;
        $maxIterations = 1000;

        while ($iterations < $maxIterations) {
            $iterations++;

            $payment = $payment->fresh();
            if (! $payment->hasUnallocatedAmount()) {
                break;
            }

            $oldestOpenVi = VendorInvoice::query()
                ->where('counterparty_id', $payment->counterparty_id)
                ->where('currency_code', $payment->currency_code)
                ->where('status', VendorInvoice::STATUS_POSTED)
                ->where('balance_due', '>', 0)
                ->orderBy('invoice_date')
                ->orderBy('id')
                ->first();

            if (! $oldestOpenVi) {
                break;
            }

            // Compute the gross allocation amount.
            //   Net cash available: X = payment->unallocatedAmount()
            //   VI balance_due:     Y = oldestOpenVi->balance_due
            //   Withholding ratio:  r = vi.withholding_total / vi.total (if total>0)
            //   To consume X net, gross = X / (1 - r). But limited by Y.
            //   So gross = min(Y, X / (1-r)).

            $netAvail = $payment->unallocatedAmount();
            $balDue = (string) $oldestOpenVi->balance_due;
            $viTotal = (string) $oldestOpenVi->total;
            $viWht = (string) $oldestOpenVi->withholding_total;

            $grossCap = $balDue;

            if (bccomp($viTotal, '0', 2) > 0 && bccomp($viWht, '0', 2) > 0) {
                // r = wht / total  → 1 - r = (total - wht) / total
                // gross = X / (1 - r) = X * total / (total - wht)
                $oneMinusRDenom = bcsub($viTotal, $viWht, 8);
                if (bccomp($oneMinusRDenom, '0', 8) > 0) {
                    $grossFromNet = bcdiv(bcmul($netAvail, $viTotal, 8), $oneMinusRDenom, 8);
                    $grossFromNet = number_format((float) $grossFromNet, 2, '.', '');
                    if (bccomp($grossFromNet, $grossCap, 2) < 0) {
                        $grossCap = $grossFromNet;
                    }
                }
            } else {
                // No WHT — gross = net.
                if (bccomp($netAvail, $grossCap, 2) < 0) {
                    $grossCap = $netAvail;
                }
            }

            // Round; if grossCap rounded down to zero, exit.
            if (bccomp($grossCap, '0', 2) <= 0) {
                break;
            }

            $allocations[] = $this->allocate(
                payment:    $payment,
                invoice:    $oldestOpenVi,
                amount:     $grossCap,
                createdBy:  $createdBy,
                notes:      'Auto-allocated FIFO',
            );
        }

        if ($iterations >= $maxIterations) {
            throw new \RuntimeException(
                "PaymentAllocationService::autoAllocateFifo hit iteration limit ({$maxIterations}). " .
                "This indicates a logic bug or runaway loop."
            );
        }

        return $allocations;
    }

    /* aggregate refresh */

    public function refreshPaymentAggregates(Payment $payment): void
    {
        $sums = $payment->allocations()  // active only via scope
            ->selectRaw('COALESCE(SUM(amount), 0) AS allocated_gross, COALESCE(SUM(withholding_amount), 0) AS withheld')
            ->first();

        $allocatedGross = (string) ($sums?->allocated_gross ?? '0');
        $withheld       = (string) ($sums?->withheld ?? '0');
        $allocatedNet   = bcsub($allocatedGross, $withheld, 2);

        $allocatedGross = number_format((float) $allocatedGross, 2, '.', '');
        $withheld       = number_format((float) $withheld, 2, '.', '');
        $allocatedNet   = number_format((float) $allocatedNet, 2, '.', '');

        $payment->update([
            'allocated_amount'   => $allocatedGross,  // GROSS AP relief total
            'withholding_amount' => $withheld,
            'net_amount'         => $allocatedNet,    // == bank statement matched amount when fully allocated
        ]);
    }

    public function refreshVendorInvoiceAggregates(VendorInvoice $invoice): void
    {
        // VI's amount_paid is in GROSS terms — what was credited against AP.
        $amountPaid = (string) PaymentAllocation::query()
            ->where('vendor_invoice_id', $invoice->id)
            ->where('status', PaymentAllocation::STATUS_ACTIVE)
            ->sum('amount');

        $amountPaid = number_format((float) $amountPaid, 2, '.', '');
        $balanceDue = bcsub((string) $invoice->total, $amountPaid, 2);
        $balanceDue = number_format((float) $balanceDue, 2, '.', '');

        $update = [
            'amount_paid' => $amountPaid,
            'balance_due' => $balanceDue,
        ];

        if (bccomp($balanceDue, '0', 2) === 0) {
            if (! $invoice->fully_paid_at) {
                $update['fully_paid_at'] = now();
            }
        } else {
            if ($invoice->fully_paid_at) {
                $update['fully_paid_at'] = null;
            }
        }

        $invoice->update($update);
    }

    /* internals */

    protected function postAllocationJournal(PaymentAllocation $allocation, User $user)
    {
        $allocation->load(['payment.counterparty.supplierCategory', 'vendorInvoice']);

        $apAccountId       = $this->resolveApAccountId($allocation);
        $suspenseAccountId = $this->accountResolver->byHint('SUSPENSE_PAYMENT')->id;

        $gross = (string) $allocation->amount;
        $wht   = (string) $allocation->withholding_amount;
        $net   = bcsub($gross, $wht, 2);

        $description = sprintf(
            'Payment %s allocated to %s — Dr AP %s / Cr Suspense %s%s',
            $allocation->payment->payment_number,
            $allocation->vendorInvoice->vendor_invoice_number,
            number_format((float) $gross, 2),
            number_format((float) $net, 2),
            bccomp($wht, '0', 2) > 0
                ? ' / Cr WHT ' . number_format((float) $wht, 2)
                : '',
        );

        $dimensions = [
            'counterparty_id' => $allocation->payment->counterparty_id,
            'source_type'     => PaymentAllocation::class,
            'source_id'       => $allocation->id,
            'reference'       => $allocation->payment->payment_number,
        ];

        $lines = [
            JournalLineDraft::debit(
                accountId: $apAccountId,
                amount:    $gross,
                description: $description,
                dimensions: $dimensions,
            ),
            JournalLineDraft::credit(
                accountId: $suspenseAccountId,
                amount:    $net,
                description: $description,
                dimensions: $dimensions,
            ),
        ];

        // Add WHT credit line only if the slice is positive.
        if (bccomp($wht, '0', 2) > 0) {
            $whtAccountId = $this->accountResolver->byCode('2250')->id;
            $lines[] = JournalLineDraft::credit(
                accountId: $whtAccountId,
                amount:    $wht,
                description: $description,
                dimensions: $dimensions,
            );
        }

        $journal = new JournalDraft(
            journalType:  bccomp($wht, '0', 2) > 0 ? 'payment_allocation_wht' : 'payment_allocation',
            lines:        $lines,
            reference:    $allocation->payment->payment_number,
            description:  $description,
            currencyCode: $allocation->payment->currency_code,
            exchangeRate: (string) $allocation->payment->exchange_rate,
            sourceType:   PaymentAllocation::class,
            sourceId:     $allocation->id,
        );

        $batchDraft = new JournalBatchDraft(
            batchType:        'auto_payment_allocation',
            postingDate:      CarbonImmutable::parse($allocation->allocation_date),
            journals:         [$journal],
            description:      $description,
            sourceModule:     'payment_allocations',
            sourceType:       PaymentAllocation::class,
            sourceId:         $allocation->id,
            idempotencyKey:   JournalPosting::buildIdempotencyKey(
                'allocation_posted',
                PaymentAllocation::class,
                $allocation->id,
                JournalPosting::PURPOSE_PAYMENT_ALLOCATION,
            ),
            postingPurpose:   JournalPosting::PURPOSE_PAYMENT_ALLOCATION,
            subscriberClass:  static::class,
        );

        return $this->postingEngine->post($batchDraft, $user);
    }

    protected function resolveApAccountId(PaymentAllocation $allocation): int
    {
        $category = $allocation->payment?->counterparty?->supplierCategory;
        if ($category && $category->ap_account_id) {
            return (int) $category->ap_account_id;
        }
        return $this->accountResolver->byCode('2010')->id;
    }
}
