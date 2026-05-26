<?php

namespace App\Services\Finance\Ageing;

use Carbon\CarbonImmutable;

/**
 * Value object: full AR ageing report.
 *
 * Composed of:
 *   - asOf           : the date the buckets were computed against
 *   - bucketDays     : the day boundaries used (e.g. [30, 60, 90])
 *   - customerLines  : per-customer bucketed totals (sorted by total desc)
 *   - grandTotals    : roll-up across all customers
 *
 * @see AgeingLine for individual rows
 */
final class AgeingReport
{
    /**
     * @param  array<AgeingLine>      $customerLines
     * @param  array<string, string>  $grandTotals
     * @param  string                 $reportType  'ar' | 'ap'
     */
    public function __construct(
        public readonly CarbonImmutable $asOf,
        public readonly array $bucketDays,
        public readonly array $customerLines,
        public readonly array $grandTotals,
        public readonly string $reportType = 'ar',
    ) {
    }

    public function customerCount(): int
    {
        return count($this->customerLines);
    }

    public function isEmpty(): bool
    {
        return empty($this->customerLines);
    }

    /**
     * Bucket labels for display, derived from bucketDays.
     */
    public function bucketLabels(): array
    {
        [$b1, $b2, $b3] = $this->bucketDays;
        return [
            'current'  => 'Current',
            'bucket_1' => "1-{$b1} days",
            'bucket_2' => ($b1 + 1) . "-{$b2} days",
            'bucket_3' => ($b2 + 1) . "-{$b3} days",
            'bucket_4' => "{$b3}+ days",
        ];
    }

    public function isAr(): bool
    {
        return $this->reportType === 'ar';
    }

    public function isAp(): bool
    {
        return $this->reportType === 'ap';
    }

    /**
     * Display label for the report type, used in views and CSV exports.
     */
    public function reportTitle(): string
    {
        return match ($this->reportType) {
            'ap'    => 'Accounts Payable Ageing',
            default => 'Accounts Receivable Ageing',
        };
    }

    /**
     * Counterparty noun (Customer for AR, Supplier for AP).
     * Used in headers and CSV column titles.
     */
    public function counterpartyNoun(): string
    {
        return $this->isAp() ? 'Supplier' : 'Customer';
    }

    /**
     * Friendly slug used for filenames: 'ar_ageing' | 'ap_ageing'.
     */
    public function filenameSlug(): string
    {
        return $this->reportType . '_ageing';
    }
}
