<?php

namespace App\Services\Finance\Allocations;

use App\Finance\Posting\PostingEngine;
use App\Finance\Resolvers\FinanceAccountResolver;
use App\Services\Finance\JournalReversalService;
use App\Services\Finance\BankReconciliation\BankReconciliationService;
use App\Models\Finance\Invoice;
use App\Models\Finance\Receipt;
use App\Models\Finance\ReceiptAllocation;
use App\Models\User;
use App\Support\CurrentOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Applies a receipt's cash to an invoice's outstanding balance. The
 * core sub-ledger primitive in Phase 2c.
 *
 * Concurrency model:
 *   Two users could simultaneously try to allocate the same receipt
 *   against different invoices. Both transactions need to read the
 *   receipt's allocated_amount, decide if there's headroom, and write
 *   a new allocation. Without locking, both could succeed and we'd
 *   over-allocate (allocated_amount > amount).
 *
 *   Solution: lockForUpdate on BOTH the receipt and the invoice inside
 *   the transaction. The first writer holds locks until commit; the
 *   second waits, re-reads with fresh values, and either fails or
 *   succeeds with the truth.
 *
 *   Lock order matters: ALWAYS lock receipt first, then invoice. If
 *   another transaction grabs them in the opposite order, deadlock.
 *   This ordering is enforced by getting both via id (deterministic).
 *
 * Validation:
 *   - Receipt and invoice must be in same organisation
 *   - Receipt must be posted (not draft, not reversed)
 *   - Invoice must be posted, not reversed, not cancelled, not cash
 *   - Counterparty (customer) must match — can't allocate one customer's
 *     payment against another customer's invoice
 *   - Currency must match (Phase 5 will add FX conversion)
 *   - Amount > 0
 *   - Amount <= receipt's unallocated portion
 *   - Amount <= invoice's outstanding balance_due
 *
 * No GL impact:
 *   The cash hit GL when the receipt was created (Dr Cash, Cr AR control).
 *   This is sub-ledger detail only — it attributes that AR-control credit
 *   to specific invoices.
 *
 * Atomicity:
 *   The allocation row insert + receipt.allocated_amount update +
 *   invoice.amount_paid/balance_due/payment-date updates all happen
 *   inside one DB::transaction. If any step fails, none persist.
 */
class AllocationService
{
    public function __construct(
        protected CurrentOrganization $currentOrganization,
        protected PostingEngine $postingEngine,
        protected FinanceAccountResolver $accountResolver,
        protected JournalReversalService $journalReversal,
        protected BankReconciliationService $bankReconciliationService,
    ) {
    }

    /**
     * Create an allocation row tying the receipt to the invoice.
     *
     * @param  string  $amount  decimal-as-string, > 0
     */
    public function allocate(
        Receipt $receipt,
        Invoice $invoice,
        string $amount,
        User $user,
        ?CarbonImmutable $allocationDate = null,
        ?string $notes = null,
    ): ReceiptAllocation {
        if (bccomp($amount, '0', 2) <= 0) {
            throw new \DomainException("Allocation amount must be > 0; got {$amount}");
        }

        return DB::transaction(function () use ($receipt, $invoice, $amount, $user, $allocationDate, $notes) {
            // Lock both rows in deterministic order to avoid deadlock.
            $receipt = Receipt::lockForUpdate()->findOrFail($receipt->id);
            $invoice = Invoice::lockForUpdate()->findOrFail($invoice->id);

            $this->validate($receipt, $invoice, $amount);

            $allocation = ReceiptAllocation::create([
                'organization_id'     => $this->currentOrganization->resolve()?->id,
                'receipt_id'          => $receipt->id,
                'invoice_id'          => $invoice->id,
                'amount'              => $amount,
                'allocation_date'     => ($allocationDate ?? CarbonImmutable::today())->toDateString(),
                'status'              => ReceiptAllocation::STATUS_ACTIVE,
                'created_by_user_id'  => $user->id,
                'notes'               => $notes,
            ]);

            $this->updateReceiptAggregate($receipt);
            $this->updateInvoiceAggregate($invoice);

            return $allocation->fresh();
        });
    }

    /**
     * Reverse an allocation. The cash returns to the receipt's unallocated
     * pool; the invoice's balance restores.
     *
     * The reversal does NOT delete the original allocation — it flips its
     * status to 'reversed' and updates the audit columns. This preserves
     * the audit trail for who allocated what when, and who reversed it.
     */
    public function reverse(ReceiptAllocation $allocation, User $user, string $reason): ReceiptAllocation
    {
        // Phase 3e: refuse if the parent receipt is part of a closed
        // (reconciled or flagged) bank reconciliation.
        $parentReceipt = $allocation->fresh(['receipt'])?->receipt;
        if ($parentReceipt) {
            $lockingRecons = $this->bankReconciliationService->lockingReconciliations(
                \App\Models\Finance\BankStatementLine::MATCHED_TYPE_RECEIPT,
                (int) $parentReceipt->id,
            );
            if (! empty($lockingRecons)) {
                $recNumbers = array_map(fn ($r) => $r->reconciliation_number, $lockingRecons);
                throw new \DomainException(
                    "Cannot reverse allocation #{$allocation->id} — its parent "
                    . "receipt {$parentReceipt->receipt_number} is part of "
                    . "closed bank reconciliation(s): " . implode(', ', $recNumbers)
                    . '. Reopen the reconciliation first.'
                );
            }
        }

        return DB::transaction(function () use ($allocation, $user, $reason) {
            $allocation = ReceiptAllocation::lockForUpdate()->findOrFail($allocation->id);
            if (! $allocation->isActive()) {
                throw new \DomainException(
                    "Allocation #{$allocation->id} cannot be reversed: status is '{$allocation->status}'"
                );
            }

            $receipt    = Receipt::lockForUpdate()->findOrFail($allocation->receipt_id);
            $invoice    = Invoice::lockForUpdate()->findOrFail($allocation->invoice_id);

            $allocation->update([
                'status'             => ReceiptAllocation::STATUS_REVERSED,
                'reversed_by_user_id'=> $user->id,
                'reversed_at'        => now(),
                'reversal_reason'    => $reason,
            ]);

            $this->updateReceiptAggregate($receipt);
            $this->updateInvoiceAggregate($invoice);

            return $allocation->fresh();
        });
    }

    /**
     * Auto-allocate a receipt's full amount against the customer's oldest
     * open invoices, FIFO. Used by the "create receipt + auto allocate"
     * UI path and the backfill command.
     *
     * Walks open invoices oldest first. For each, allocates min(remaining,
     * invoice.balance_due). Stops when receipt is fully allocated or no
     * more open invoices.
     *
     * @return array<ReceiptAllocation>  list of created allocations
     */
    public function autoAllocateFifo(Receipt $receipt, User $user): array
    {
        $allocations = [];
        $iterations = 0;
        $maxIterations = 1000;  // Defensive — should never hit

        while ($iterations++ < $maxIterations) {
            $receipt = $receipt->fresh();

            $remaining = $receipt->unallocatedAmount();
            if (bccomp($remaining, '0', 2) <= 0) {
                break;
            }

            $invoice = Invoice::open()
                ->where('counterparty_id', $receipt->counterparty_id)
                ->where('currency_code', $receipt->currency_code)
                ->where('balance_due', '>', 0)
                ->orderBy('invoice_date')
                ->orderBy('id')
                ->first();

            if (! $invoice) {
                break;
            }

            // Allocate min(remaining, balance_due).
            $allocAmount = bccomp($remaining, (string) $invoice->balance_due, 2) <= 0
                ? $remaining
                : (string) $invoice->balance_due;

            $allocations[] = $this->allocate($receipt, $invoice, $allocAmount, $user);
        }

        return $allocations;
    }

    /* internals */

    protected function validate(Receipt $receipt, Invoice $invoice, string $amount): void
    {
        if (! $receipt->isPosted()) {
            throw new \DomainException("Receipt #{$receipt->id} is not posted (status: {$receipt->status})");
        }
        if (! $invoice->isPosted()) {
            throw new \DomainException("Invoice #{$invoice->id} is not posted (status: {$invoice->status})");
        }
        if ($invoice->is_cash_invoice) {
            throw new \DomainException("Cannot allocate against cash invoice #{$invoice->id} — already paid");
        }
        if ($receipt->organization_id !== $invoice->organization_id) {
            throw new \DomainException('Receipt and invoice belong to different organisations');
        }
        if ((int) $receipt->counterparty_id !== (int) $invoice->counterparty_id) {
            throw new \DomainException(
                "Counterparty mismatch: receipt belongs to customer #{$receipt->counterparty_id}, "
                . "invoice belongs to customer #{$invoice->counterparty_id}"
            );
        }
        if ($receipt->currency_code !== $invoice->currency_code) {
            throw new \DomainException(
                "Currency mismatch: receipt is {$receipt->currency_code}, invoice is {$invoice->currency_code}"
            );
        }

        $unallocated = $receipt->unallocatedAmount();
        if (bccomp($amount, $unallocated, 2) > 0) {
            throw new \DomainException(
                "Allocation amount {$amount} exceeds receipt's unallocated balance {$unallocated}"
            );
        }

        $invoiceBalance = (string) $invoice->balance_due;
        if (bccomp($amount, $invoiceBalance, 2) > 0) {
            throw new \DomainException(
                "Allocation amount {$amount} exceeds invoice #{$invoice->id} balance due {$invoiceBalance}"
            );
        }
    }

    /**
     * Recompute receipt.allocated_amount from sum of active allocations.
     * Called inside transactions while holding lockForUpdate on the receipt.
     */
    protected function updateReceiptAggregate(Receipt $receipt): void
    {
        $sum = (string) $receipt->activeAllocations()->sum('amount');
        // sum() returns int|float|string depending on driver — normalise to 2dp
        $sum = bcadd($sum ?: '0', '0', 2);

        $receipt->update(['allocated_amount' => $sum]);
    }

    /**
     * Recompute invoice.amount_paid + balance_due + payment-date columns
     * from the sum of active allocations. Called inside transactions.
     */
    protected function updateInvoiceAggregate(Invoice $invoice): void
    {
        $activeAllocations = $invoice->activeAllocations()->orderBy('allocation_date')->get();

        $sum = '0';
        foreach ($activeAllocations as $a) {
            $sum = bcadd($sum, (string) $a->amount, 2);
        }

        $balanceDue = bcsub((string) $invoice->total, $sum, 2);
        // Floor at 0 — never negative (validation should prevent over-allocation
        // but guard anyway in case of legacy data).
        if (bccomp($balanceDue, '0', 2) < 0) {
            $balanceDue = '0';
        }

        $firstPaymentAt = $activeAllocations->first()?->allocation_date;
        $lastPaymentAt  = $activeAllocations->last()?->allocation_date;
        $fullyPaidAt    = bccomp($balanceDue, '0', 2) === 0 && $sum !== '0'
            ? ($lastPaymentAt ?? now())
            : null;

        $invoice->update([
            'amount_paid'      => $sum,
            'balance_due'      => $balanceDue,
            'first_payment_at' => $firstPaymentAt,
            'last_payment_at'  => $lastPaymentAt,
            'fully_paid_at'    => $fullyPaidAt,
        ]);
    }
}
