<?php

namespace App\Services\Finance\Payments;

use App\Models\Counterparty;
use App\Models\Finance\Payment;
use App\Models\Finance\PaymentRun;
use App\Models\Finance\PaymentRunLine;
use App\Models\Finance\VendorInvoice;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Services\Finance\JournalReversalService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Executes a draft payment run: groups lines by (counterparty,
 * payment_method, source_account), creates one Payment per group via
 * PaymentService, then allocates each group's lines via
 * PaymentAllocationService.
 *
 * Grouping rules (Decision 32):
 *   1. Resolve NULL line.payment_method_id → run.default_payment_method_id
 *   2. Resolve NULL line.source_account_id → run.default_source_account_id
 *   3. Throw if any line still has NULL method (run had no default and
 *      line had no override)
 *   4. Group by (counterparty_id, payment_method_id, source_account_id)
 *   5. For each group:
 *        a. Compute payment amount = sum of line amounts in the group
 *        b. Create a posted Payment with that amount
 *        c. For each line in the group:
 *           - Allocate amount to the line's vendor_invoice
 *           - Update line.payment_id and line.payment_allocation_id
 *
 * The whole execute() runs in a transaction. If any single payment
 * creation or allocation fails, EVERYTHING rolls back — including
 * already-created Payment rows in the same execute call. The
 * PostingEngine's idempotency keys mean a partial-failure-then-retry
 * would not double-post, but the convenience of "all-or-nothing" makes
 * recovery cleaner.
 *
 * Reverse:
 *   reverse() iterates the run's child payments. For each posted one:
 *     - Reverse all active allocations (which reverses their WHT journals)
 *     - Reverse the cash journal via JournalReversalService
 *     - Mark payment status=reversed
 *   Marks run status=reversed.
 *
 *   If any child payment was independently reversed before, run reverse
 *   is blocked (PaymentRun::canReverse() returns false). Treasury
 *   handles those edge cases manually.
 */
class PaymentRunExecutor
{
    public function __construct(
        protected PaymentService $paymentService,
        protected PaymentAllocationService $allocationService,
        protected JournalReversalService $journalReversal,
    ) {
    }

    public function execute(PaymentRun $run, User $executor): PaymentRun
    {
        return DB::transaction(function () use ($run, $executor) {
            $run = PaymentRun::lockForUpdate()->findOrFail($run->id);

            if (! $run->canExecute()) {
                throw new \DomainException(
                    "Payment run {$run->run_number} cannot be executed (status: {$run->status}, has lines: " .
                    ($run->lines()->exists() ? 'yes' : 'no') . ')'
                );
            }

            $run->loadMissing('lines.vendorInvoice');
            $lines = $run->lines;

            // Resolve NULL method/source from run defaults BEFORE grouping.
            $resolvedLines = $lines->map(function (PaymentRunLine $l) use ($run) {
                $methodId = $l->payment_method_id ?? $run->default_payment_method_id;
                $sourceId = $l->source_account_id ?? $run->default_source_account_id;
                if (! $methodId) {
                    throw new \DomainException(
                        "Line for VI #{$l->vendor_invoice_id} has no payment method and run has no default. " .
                        "Set the run default or override the line."
                    );
                }
                return [
                    'line'      => $l,
                    'method_id' => $methodId,
                    'source_id' => $sourceId,
                ];
            });

            // Group by (counterparty, method, source).
            $groups = $resolvedLines->groupBy(fn ($x) =>
                $x['line']->counterparty_id . ':' .
                $x['method_id']             . ':' .
                ($x['source_id'] ?? 'null')
            );

            // For each group: create payment + allocate lines.
            foreach ($groups as $groupKey => $groupLines) {
                $first = $groupLines->first();
                $counterpartyId = $first['line']->counterparty_id;
                $methodId       = $first['method_id'];
                $sourceId       = $first['source_id'];

                $supplier = Counterparty::lockForUpdate()->findOrFail($counterpartyId);
                $method   = PaymentMethod::findOrFail($methodId);

                // payment.amount is the NET cash leaving bank.
                // For each line: net = line.amount (gross) - line.withholding_amount.
                // Sum the nets across the group.
                $totalNet = '0';
                foreach ($groupLines as $entry) {
                    $lineGross = (string) $entry['line']->amount;
                    $lineWht   = (string) $entry['line']->withholding_amount;
                    $lineNet   = bcsub($lineGross, $lineWht, 2);
                    $totalNet  = bcadd($totalNet, $lineNet, 2);
                }
                $totalNet = number_format((float) $totalNet, 2, '.', '');

                $payment = $this->paymentService->createPosted(
                    supplier:     $supplier,
                    method:       $method,
                    amount:       $totalNet,
                    paymentDate:  CarbonImmutable::parse($run->run_date),
                    createdBy:    $executor,
                    extra: [
                        'currency_code'      => $run->currency_code,
                        'source_account_id'  => $sourceId,
                        'payment_run_id'     => $run->id,
                        'description'        => "Payment from run {$run->run_number}",
                    ],
                );

                // Allocate each line to its vendor invoice (using the GROSS amount).
                foreach ($groupLines as $entry) {
                    $line = $entry['line'];
                    $invoice = VendorInvoice::lockForUpdate()->findOrFail($line->vendor_invoice_id);

                    $allocation = $this->allocationService->allocate(
                        payment:    $payment->fresh(),
                        invoice:    $invoice,
                        amount:     (string) $line->amount,  // GROSS
                        createdBy:  $executor,
                        notes:      "From payment run {$run->run_number}",
                    );

                    $line->update([
                        'payment_id'             => $payment->id,
                        'payment_allocation_id'  => $allocation->id,
                    ]);
                }
            }

            $run->update([
                'status'              => PaymentRun::STATUS_EXECUTED,
                'executed_by_user_id' => $executor->id,
                'executed_at'         => now(),
            ]);

            return $run->fresh(['payments', 'lines']);
        });
    }

    public function reverse(PaymentRun $run, User $reverser, string $reason): PaymentRun
    {
        return DB::transaction(function () use ($run, $reverser, $reason) {
            $run = PaymentRun::lockForUpdate()->findOrFail($run->id);

            if (! $run->canReverse()) {
                throw new \DomainException(
                    "Payment run {$run->run_number} cannot be reversed (status: {$run->status}). " .
                    "If a child payment was reversed independently, run reverse is blocked — " .
                    "handle the remaining payments manually."
                );
            }

            $run->loadMissing(['payments.allAllocations']);

            foreach ($run->payments as $payment) {
                if (! $payment->isPosted()) {
                    continue;
                }
                $payment = Payment::lockForUpdate()->findOrFail($payment->id);

                // Reverse active allocations first.
                foreach ($payment->allocations as $allocation) {
                    $this->allocationService->reverse(
                        allocation: $allocation,
                        reverser:   $reverser,
                        reason:     "Run {$run->run_number} reversal: {$reason}",
                    );
                }

                // Reverse the cash journal.
                if ($payment->journal_batch_id) {
                    $reversalBatch = $this->journalReversal->reverse(
                        original: $payment->journalBatch,
                        reverser: $reverser,
                        reason:   "Payment {$payment->payment_number} reversal: {$reason}",
                    );
                    $payment->reversed_by_journal_batch_id = $reversalBatch->id;
                }

                $payment->update([
                    'status'                       => Payment::STATUS_REVERSED,
                    'reversed_by_user_id'          => $reverser->id,
                    'reversed_at'                  => now(),
                    'reversal_reason'              => "Run reversal: {$reason}",
                    'reversed_by_journal_batch_id' => $payment->reversed_by_journal_batch_id,
                ]);
            }

            $run->update([
                'status'              => PaymentRun::STATUS_REVERSED,
                'reversed_by_user_id' => $reverser->id,
                'reversed_at'         => now(),
                'reversal_reason'     => $reason,
            ]);

            return $run->fresh();
        });
    }
}
