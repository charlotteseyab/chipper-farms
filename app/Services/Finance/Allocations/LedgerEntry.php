<?php

namespace App\Services\Finance\Allocations;

use Carbon\CarbonImmutable;

/**
 * One line in a customer ledger.
 *
 * The runningBalance field is mutable (set after the entries are sorted)
 * — this is the only mutable field. Every other field is set in __construct
 * and final.
 */
final class LedgerEntry
{
    private string $runningBalance = '0';

    public function __construct(
        public readonly CarbonImmutable $date,
        public readonly string $type,           // 'invoice' | 'receipt'
        public readonly string $reference,
        public readonly string $debit,           // decimal-as-string, "0" if none
        public readonly string $credit,          // decimal-as-string, "0" if none
        public readonly int $relatedId,          // invoice.id or receipt.id
        public readonly string $description,
    ) {
    }

    public function setRunningBalance(string $balance): void
    {
        $this->runningBalance = $balance;
    }

    public function runningBalance(): string
    {
        return $this->runningBalance;
    }

    /**
     * Net effect of this entry on AR balance.
     * Invoices increase balance (debit > 0); receipts decrease (credit > 0).
     */
    public function netAmount(): string
    {
        return bcsub($this->debit, $this->credit, 2);
    }
}
