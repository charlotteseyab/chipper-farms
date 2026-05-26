<?php

namespace App\Services\Finance\Allocations;

use App\Models\Counterparty;
use App\Models\Finance\Invoice;
use App\Models\Finance\Receipt;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Builds the per-customer chronological ledger view.
 *
 * For a given customer + date range, produces a sequence of LedgerEntry
 * value objects representing every transaction affecting the customer's
 * AR balance in chronological order, with a running balance.
 *
 * Includes:
 *   - Posted credit invoices (debits — increase AR balance)
 *   - Posted receipts (credits — decrease AR balance)
 *
 * Excludes:
 *   - Cash invoices (never hit AR)
 *   - Reversed invoices (no longer represent owed money)
 *   - Reversed receipts (cash returned to customer)
 *   - Draft invoices, cancelled invoices
 *
 * Chronological ordering rules:
 *   1. Sort by date ASC
 *   2. Within the same date: invoices first, then receipts. This produces
 *      the natural narrative ("on 15 May we billed 500, then received 500"
 *      runs to a zero balance same day).
 *   3. Within the same date and type: by id ASC (stable for re-renders).
 *
 * The running balance is computed by walking the sorted list left to
 * right. The first entry's running_balance = its net amount; each
 * subsequent entry adds its net to the prior running balance.
 *
 * Performance:
 *   For a customer with 5,000 historical transactions, the SQL is two
 *   simple indexed queries (org+counterparty+status). The PHP-side sort
 *   is O(n log n) on the merged list. Acceptable up to ~50k entries per
 *   customer; beyond that, paginate with date ranges.
 */
class CustomerLedgerService
{
    /**
     * Build the ledger for a customer.
     *
     * @return Collection<int, LedgerEntry>
     */
    public function build(
        Counterparty $customer,
        ?CarbonImmutable $startDate = null,
        ?CarbonImmutable $endDate = null,
    ): Collection {
        $invoices = Invoice::query()
            ->where('counterparty_id', $customer->id)
            ->where('status', Invoice::STATUS_POSTED)
            ->where('is_cash_invoice', false)
            ->when($startDate, fn ($q) => $q->whereDate('invoice_date', '>=', $startDate->toDateString()))
            ->when($endDate,   fn ($q) => $q->whereDate('invoice_date', '<=', $endDate->toDateString()))
            ->get();

        $receipts = Receipt::query()
            ->where('counterparty_id', $customer->id)
            ->where('status', Receipt::STATUS_POSTED)
            ->when($startDate, fn ($q) => $q->whereDate('receipt_date', '>=', $startDate->toDateString()))
            ->when($endDate,   fn ($q) => $q->whereDate('receipt_date', '<=', $endDate->toDateString()))
            ->get();

        $entries = collect();

        foreach ($invoices as $inv) {
            $entries->push(new LedgerEntry(
                date:        CarbonImmutable::instance($inv->invoice_date),
                type:        'invoice',
                reference:   $inv->invoice_number,
                debit:       (string) $inv->total,
                credit:      '0',
                relatedId:   $inv->id,
                description: $inv->description ?? "Invoice {$inv->invoice_number}",
            ));
        }

        foreach ($receipts as $rec) {
            $entries->push(new LedgerEntry(
                date:        CarbonImmutable::instance($rec->receipt_date),
                type:        'receipt',
                reference:   $rec->receipt_number,
                debit:       '0',
                credit:      (string) $rec->amount,
                relatedId:   $rec->id,
                description: $rec->description ?? "Receipt {$rec->receipt_number}",
            ));
        }

        // Sort: by date asc, then invoices before receipts on same date, then id asc.
        $sorted = $entries->sort(function (LedgerEntry $a, LedgerEntry $b) {
            $dateCmp = $a->date->getTimestamp() <=> $b->date->getTimestamp();
            if ($dateCmp !== 0) return $dateCmp;
            $typeOrder = ['invoice' => 0, 'receipt' => 1];
            $typeCmp = $typeOrder[$a->type] <=> $typeOrder[$b->type];
            if ($typeCmp !== 0) return $typeCmp;
            return $a->relatedId <=> $b->relatedId;
        })->values();

        // Compute running balance.
        $runningBalance = '0';
        foreach ($sorted as $entry) {
            $runningBalance = bcadd($runningBalance, $entry->netAmount(), 2);
            $entry->setRunningBalance($runningBalance);
        }

        return $sorted;
    }

    /**
     * Outstanding balance = sum of balance_due across posted, non-cash,
     * non-reversed invoices for this customer. Independent of the
     * full-ledger build — used in customer cards / list summaries
     * where the full ledger is overkill.
     */
    public function outstandingBalance(Counterparty $customer): string
    {
        $sum = (string) Invoice::query()
            ->where('counterparty_id', $customer->id)
            ->where('status', Invoice::STATUS_POSTED)
            ->where('is_cash_invoice', false)
            ->sum('balance_due');

        return bcadd($sum ?: '0', '0', 2);
    }
}
