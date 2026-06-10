<?php

namespace App\Services\Finance\Ageing;

use App\Models\Counterparty;
use App\Models\Finance\Invoice;
use App\Models\Finance\VendorInvoice;

/**
 * One row in the ageing report — a customer's bucketed outstanding balance.
 *
 * Companion class to {@see AgeingReport}.
 */
final class AgeingLine
{
    /**
     * @param  array<string, string>  $buckets  bucket_name => decimal-as-string
     */
    public function __construct(
        public readonly Counterparty $counterparty,
        public readonly int $invoiceCount,
        public readonly array $buckets,
        public readonly Invoice|VendorInvoice $oldestInvoice,
        public readonly string $currencyCode,
    ) {
    }

    /**
     * Customer is "in trouble" if any balance sits in the 90+ bucket
     * — flag for visual emphasis.
     */
    public function hasSevereOverdue(): bool
    {
        return bccomp($this->buckets['bucket_4'] ?? '0', '0', 2) > 0;
    }

    /**
     * Over their credit limit?
     */
    public function exceedsCreditLimit(): bool
    {
        $limit = $this->counterparty->credit_limit;
        if ($limit === null || bccomp((string) $limit, '0', 2) <= 0) {
            return false;  // No limit set → not exceeded
        }
        return bccomp($this->buckets['total'], (string) $limit, 2) > 0;
    }
}
