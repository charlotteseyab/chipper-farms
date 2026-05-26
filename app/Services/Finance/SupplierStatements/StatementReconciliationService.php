<?php

namespace App\Services\Finance\SupplierStatements;

use App\Models\Finance\Payment;
use App\Models\Finance\PaymentAllocation;
use App\Models\Finance\SupplierStatement;
use App\Models\Finance\SupplierStatementLine;
use App\Models\Finance\VendorInvoice;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * The matching engine for supplier statement reconciliation.
 *
 * On each pass:
 *   1. Compute "our balance" for the supplier as of period_to.
 *   2. For each unresolved line on the statement, attempt to match it
 *      against our books:
 *        - 'invoice' lines    : match against VendorInvoice by
 *                                supplier_invoice_number (case-insensitive)
 *        - 'payment' lines    : match against Payment by external_reference
 *                                (cheque/transfer/momo ref) — fall back
 *                                to PaymentAllocation linked to the supplier
 *                                if the amount + date are unambiguous
 *        - 'credit'           : not yet handled (Phase 4 — credit notes
 *                                are a separate document module that hasn't
 *                                shipped). Lines marked not_in_our_books
 *                                with a note.
 *        - 'adjustment'       : never auto-matched; left unmatched for
 *                                manual handling.
 *   3. Classify each match:
 *        matched, amount_mismatch, not_in_our_books, duplicate_match.
 *   4. Update header aggregates (our_balance, balance_difference) and
 *      lifecycle status (draft → in_progress).
 *
 * Idempotency:
 *   Re-running reconcile() on the same statement is safe. It re-classifies
 *   every line that isn't manually_resolved. If a line was matched on a
 *   previous pass and the underlying doc still exists with the same ref +
 *   amount, it stays matched — same id, same status.
 *
 * What it does NOT touch:
 *   - manually_resolved lines (human override is sticky)
 *   - Reconciled/archived statements (status guard)
 *
 * Reverse direction ("our docs missing from supplier statement"):
 *   This is a derived view, computed in {@see missingFromStatement()}.
 *   Not stored — recomputed on demand from open AP detail.
 */
class StatementReconciliationService
{
    /**
     * Run a full reconciliation pass on a statement.
     *
     * @return array  Summary: {matched: int, amount_mismatch: int, not_in_our_books: int,
     *                          duplicate_match: int, skipped_resolved: int, our_balance: string,
     *                          balance_difference: string}
     */
    public function reconcile(SupplierStatement $statement, User $user): array
    {
        if ($statement->isArchived()) {
            throw new \DomainException('Cannot reconcile archived statements.');
        }
        if ($statement->isReconciled()) {
            // Re-running on a "reconciled" status is allowed (in case data changed)
            // but we revert to in_progress before processing.
            $statement->update(['status' => SupplierStatement::STATUS_IN_PROGRESS]);
        }

        $statement->loadMissing('lines');

        $summary = [
            'matched'           => 0,
            'amount_mismatch'   => 0,
            'not_in_our_books'  => 0,
            'duplicate_match'   => 0,
            'skipped_resolved'  => 0,
        ];

        DB::transaction(function () use ($statement, &$summary) {
            foreach ($statement->lines as $line) {
                if ($line->isManuallyResolved()) {
                    $summary['skipped_resolved']++;
                    continue;
                }
                $this->matchLine($statement, $line, $summary);
            }

            // Refresh "our balance" snapshot.
            $ourBalance = $this->computeOurBalance($statement);
            $diff       = bcsub((string) $statement->supplier_balance, $ourBalance, 2);

            $statement->update([
                'our_balance'                => $ourBalance,
                'balance_difference'         => $diff,
                'status'                     => SupplierStatement::STATUS_IN_PROGRESS,
                'last_reconciled_at'         => now(),
                'last_reconciled_by_user_id' => $user?->id,
            ]);
        });

        $statement->refresh();
        $summary['our_balance']        = (string) $statement->our_balance;
        $summary['balance_difference'] = (string) $statement->balance_difference;

        return $summary;
    }

    /**
     * Mark a specific line as manually resolved with a note.
     * Used when the human accepts a discrepancy (forex, rounding, etc.).
     */
    public function markLineManuallyResolved(
        SupplierStatementLine $line,
        User $user,
        string $note,
    ): SupplierStatementLine {
        if ($line->isManuallyResolved()) {
            throw new \DomainException('Line is already manually resolved.');
        }
        $line->update([
            'match_status'        => SupplierStatementLine::STATUS_MANUALLY_RESOLVED,
            'match_notes'         => trim($note),
            'resolved_by_user_id' => $user->id,
            'resolved_at'         => now(),
        ]);
        return $line->fresh();
    }

    /**
     * Get our open vendor invoices for the supplier within the statement
     * period that DON'T appear on the statement. The reverse direction.
     *
     * @return array<\App\Models\Finance\VendorInvoice>
     */
    public function missingFromStatement(SupplierStatement $statement): array
    {
        $statement->loadMissing('lines');

        // Collect supplier-invoice-numbers cited on the statement.
        $citedRefs = [];
        foreach ($statement->lines as $l) {
            if ($l->document_kind === SupplierStatementLine::KIND_INVOICE) {
                $citedRefs[mb_strtoupper(trim($l->document_reference))] = true;
            }
        }

        return VendorInvoice::query()
            ->where('counterparty_id', $statement->counterparty_id)
            ->where('status', VendorInvoice::STATUS_POSTED)
            ->whereDate('invoice_date', '>=', $statement->period_from)
            ->whereDate('invoice_date', '<=', $statement->period_to)
            ->get()
            ->reject(function (VendorInvoice $vi) use ($citedRefs) {
                $key = mb_strtoupper(trim((string) $vi->supplier_invoice_number));
                return $key !== '' && isset($citedRefs[$key]);
            })
            ->values()
            ->all();
    }

    /* internals */

    protected function matchLine(
        SupplierStatement $statement,
        SupplierStatementLine $line,
        array &$summary,
    ): void {
        // Reset prior auto-match data (preserve manually-resolved already-handled above).
        $line->matched_id              = null;
        $line->matched_type            = null;
        $line->amount_difference       = null;
        $line->duplicate_candidate_ids = null;

        switch ($line->document_kind) {
            case SupplierStatementLine::KIND_INVOICE:
                $this->matchInvoiceLine($statement, $line, $summary);
                break;

            case SupplierStatementLine::KIND_PAYMENT:
                $this->matchPaymentLine($statement, $line, $summary);
                break;

            case SupplierStatementLine::KIND_CREDIT:
            case SupplierStatementLine::KIND_ADJUSTMENT:
                // No automatic matcher for these. They sit unmatched until
                // a human marks them manually_resolved.
                $line->match_status = SupplierStatementLine::STATUS_NOT_IN_OUR_BOOKS;
                $line->match_notes  = 'Auto-match not supported for this document_kind. Resolve manually.';
                $summary['not_in_our_books']++;
                break;
        }

        $line->save();
    }

    protected function matchInvoiceLine(
        SupplierStatement $statement,
        SupplierStatementLine $line,
        array &$summary,
    ): void {
        $ref = trim((string) $line->document_reference);
        if ($ref === '') {
            $line->match_status = SupplierStatementLine::STATUS_NOT_IN_OUR_BOOKS;
            $summary['not_in_our_books']++;
            return;
        }

        $candidates = VendorInvoice::query()
            ->where('counterparty_id', $statement->counterparty_id)
            ->where('organization_id', $statement->organization_id)
            ->whereRaw('UPPER(TRIM(supplier_invoice_number)) = UPPER(TRIM(?))', [$ref])
            ->get();

        if ($candidates->isEmpty()) {
            $line->match_status = SupplierStatementLine::STATUS_NOT_IN_OUR_BOOKS;
            $summary['not_in_our_books']++;
            return;
        }

        if ($candidates->count() > 1) {
            $line->match_status            = SupplierStatementLine::STATUS_DUPLICATE_MATCH;
            $line->duplicate_candidate_ids = $candidates->pluck('id')->all();
            $summary['duplicate_match']++;
            return;
        }

        /** @var VendorInvoice $vi */
        $vi = $candidates->first();
        $line->matched_type = SupplierStatementLine::MATCHED_TYPE_VENDOR_INVOICE;
        $line->matched_id   = $vi->id;

        $diff = bcsub((string) $line->amount, (string) $vi->total, 2);
        if (bccomp($diff, '0', 2) === 0) {
            $line->match_status = SupplierStatementLine::STATUS_MATCHED;
            $summary['matched']++;
        } else {
            $line->match_status      = SupplierStatementLine::STATUS_AMOUNT_MISMATCH;
            $line->amount_difference = $diff;
            $summary['amount_mismatch']++;
        }
    }

    protected function matchPaymentLine(
        SupplierStatement $statement,
        SupplierStatementLine $line,
        array &$summary,
    ): void {
        $ref = trim((string) $line->document_reference);

        // Strategy 1: exact match on payment.external_reference.
        if ($ref !== '') {
            $candidates = Payment::query()
                ->where('counterparty_id', $statement->counterparty_id)
                ->where('organization_id', $statement->organization_id)
                ->where('status', Payment::STATUS_POSTED)
                ->whereRaw('UPPER(TRIM(external_reference)) = UPPER(TRIM(?))', [$ref])
                ->get();

            if ($candidates->count() === 1) {
                /** @var Payment $payment */
                $payment = $candidates->first();
                $this->finaliseAgainstPayment($line, $payment, $summary);
                return;
            }
            if ($candidates->count() > 1) {
                $line->match_status            = SupplierStatementLine::STATUS_DUPLICATE_MATCH;
                $line->duplicate_candidate_ids = $candidates->pluck('id')->all();
                $summary['duplicate_match']++;
                return;
            }
        }

        // Strategy 2: fall back to amount + date proximity (within 3 days).
        // The supplier may have a different ref scheme than ours. This is
        // a conservative fuzzy match — only triggers if exactly one
        // candidate exists.
        $expectedAmount = (string) $line->amount;
        $dateLow  = CarbonImmutable::instance($line->document_date)->subDays(3);
        $dateHigh = CarbonImmutable::instance($line->document_date)->addDays(3);

        $candidates = Payment::query()
            ->where('counterparty_id', $statement->counterparty_id)
            ->where('organization_id', $statement->organization_id)
            ->where('status', Payment::STATUS_POSTED)
            ->whereBetween('payment_date', [$dateLow->toDateString(), $dateHigh->toDateString()])
            ->get()
            ->filter(function (Payment $p) use ($expectedAmount) {
                // Match against the GROSS allocated amount (sum of allocations)
                // because the supplier sees their AP relieved, not the net cash.
                // For an unallocated payment, fall back to payment.amount (net).
                $cmp = bccomp((string) $p->allocated_amount, '0', 2) > 0
                    ? (string) $p->allocated_amount
                    : (string) $p->amount;
                return bccomp($cmp, $expectedAmount, 2) === 0;
            })
            ->values();

        if ($candidates->count() === 1) {
            /** @var Payment $payment */
            $payment = $candidates->first();
            $this->finaliseAgainstPayment($line, $payment, $summary);
            return;
        }
        if ($candidates->count() > 1) {
            $line->match_status            = SupplierStatementLine::STATUS_DUPLICATE_MATCH;
            $line->duplicate_candidate_ids = $candidates->pluck('id')->all();
            $summary['duplicate_match']++;
            return;
        }

        $line->match_status = SupplierStatementLine::STATUS_NOT_IN_OUR_BOOKS;
        $summary['not_in_our_books']++;
    }

    protected function finaliseAgainstPayment(
        SupplierStatementLine $line,
        Payment $payment,
        array &$summary,
    ): void {
        $line->matched_type = SupplierStatementLine::MATCHED_TYPE_PAYMENT;
        $line->matched_id   = $payment->id;

        // Compare against the GROSS allocated amount when allocations exist;
        // otherwise the NET payment amount.
        $compareAmount = bccomp((string) $payment->allocated_amount, '0', 2) > 0
            ? (string) $payment->allocated_amount
            : (string) $payment->amount;

        $diff = bcsub((string) $line->amount, $compareAmount, 2);
        if (bccomp($diff, '0', 2) === 0) {
            $line->match_status = SupplierStatementLine::STATUS_MATCHED;
            $summary['matched']++;
        } else {
            $line->match_status      = SupplierStatementLine::STATUS_AMOUNT_MISMATCH;
            $line->amount_difference = $diff;
            $summary['amount_mismatch']++;
        }
    }

    /**
     * Compute "our balance" for the supplier as of the statement's
     * period_to date.
     *
     * Definition: sum of balance_due across posted vendor invoices for
     * the counterparty whose invoice_date <= period_to. This matches the
     * AP control GL balance for that supplier as of period_to.
     *
     * Note: this uses the LIVE balance_due, not a historical snapshot.
     * For statements covering periods well in the past, the answer
     * reflects today's residual balance on those invoices, which is
     * the operationally useful number — what we still owe right now
     * against bills issued in that window.
     */
    protected function computeOurBalance(SupplierStatement $statement): string
    {
        $sum = (string) VendorInvoice::query()
            ->where('counterparty_id', $statement->counterparty_id)
            ->where('organization_id', $statement->organization_id)
            ->where('status', VendorInvoice::STATUS_POSTED)
            ->whereDate('invoice_date', '<=', $statement->period_to)
            ->sum('balance_due');

        return number_format((float) $sum, 2, '.', '');
    }
}
