<?php

namespace App\Services\Finance\Statements;

use App\Models\Counterparty;
use App\Models\Finance\Invoice;
use App\Models\Finance\Receipt;
use App\Services\Finance\Allocations\CustomerLedgerService;
use App\Services\Finance\Allocations\LedgerEntry;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Builds a customer activity statement.
 *
 * An activity statement covers a date range and includes:
 *   - Opening balance (what was owed at the start of the period)
 *   - Every invoice raised during the period
 *   - Every receipt received during the period
 *   - Closing balance (what's owed at the end of the period)
 *
 * Distinct from a period statement which is just "as of date X, customer
 * owes Y" — the activity statement gives the customer enough detail to
 * reconcile against their own books.
 *
 * Opening balance computation:
 *   = sum of (posted credit invoice totals - matched active allocations)
 *     for entries dated BEFORE the period start
 *   In other words: what the running ledger balance was at start-of-period.
 *
 * The class delegates to CustomerLedgerService to get the chronological
 * entries, then splits them by date into pre-period (rolled into opening
 * balance) and within-period (shown line-by-line).
 */
class StatementService
{
    public function __construct(
        protected CustomerLedgerService $ledgerService,
    ) {
    }

    public function build(
        Counterparty $customer,
        CarbonImmutable $startDate,
        CarbonImmutable $endDate,
    ): Statement {
        // Get all entries up to end_date. We need pre-period entries to
        // compute opening balance.
        $allEntries = $this->ledgerService->build(
            $customer,
            startDate: null,
            endDate:   $endDate,
        );

        // Split into pre-period + within-period.
        $startTimestamp = $startDate->getTimestamp();

        $prePeriod = collect();
        $withinPeriod = collect();

        foreach ($allEntries as $entry) {
            if ($entry->date->getTimestamp() < $startTimestamp) {
                $prePeriod->push($entry);
            } else {
                $withinPeriod->push($entry);
            }
        }

        // Opening balance = running balance at end of pre-period.
        // The CustomerLedgerService already computed runningBalance() on each
        // entry, walking from the very first transaction. So opening balance
        // is the runningBalance of the LAST pre-period entry.
        $openingBalance = $prePeriod->last()?->runningBalance() ?? '0.00';

        // Within-period entries already have correct running balance (continues
        // from pre-period). Closing balance = last entry's running balance,
        // OR opening balance if no within-period activity.
        $closingBalance = $withinPeriod->last()?->runningBalance() ?? $openingBalance;

        // Counts.
        $invoicesCount = $withinPeriod->where('type', 'invoice')->count();
        $receiptsCount = $withinPeriod->where('type', 'receipt')->count();
        $invoicesTotal = $withinPeriod->where('type', 'invoice')->sum(fn (LedgerEntry $e) => (float) $e->debit);
        $receiptsTotal = $withinPeriod->where('type', 'receipt')->sum(fn (LedgerEntry $e) => (float) $e->credit);

        // Open invoices as of end_date — for the "outstanding invoices" panel.
        $openInvoices = Invoice::query()
            ->where('counterparty_id', $customer->id)
            ->where('status', Invoice::STATUS_POSTED)
            ->where('is_cash_invoice', false)
            ->where('balance_due', '>', 0)
            ->whereDate('invoice_date', '<=', $endDate->toDateString())
            ->orderBy('invoice_date')
            ->orderBy('id')
            ->get();

        return new Statement(
            customer:         $customer,
            startDate:        $startDate,
            endDate:          $endDate,
            generatedAt:      CarbonImmutable::now(),
            openingBalance:   $openingBalance,
            closingBalance:   $closingBalance,
            entries:          $withinPeriod->values()->all(),
            openInvoices:     $openInvoices,
            invoicesCount:    $invoicesCount,
            receiptsCount:    $receiptsCount,
            invoicesTotal:    number_format((float) $invoicesTotal, 2, '.', ''),
            receiptsTotal:    number_format((float) $receiptsTotal, 2, '.', ''),
        );
    }
}
