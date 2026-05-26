<?php

namespace App\Services\Finance\Ageing;

use App\Support\CurrentOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Shared ageing-report logic for AR (customer invoices) and AP (vendor
 * invoices). Handles bucketing, per-counterparty rollups, grand totals,
 * and sort order.
 *
 * Subclasses provide:
 *   - the source query (which model and filter set)
 *   - the date column to age against (due_date with invoice_date fallback)
 *   - the balance column (balance_due)
 *   - the counterparty type (customer vs supplier) for filter dropdowns
 *
 * Bucket semantics (configurable via config('finance.ageing.bucket_days'),
 * default [30, 60, 90]):
 *   current  : not yet due (effective_due_date >= as_of)
 *   bucket_1 : 1-30 days overdue
 *   bucket_2 : 31-60 days overdue
 *   bucket_3 : 61-90 days overdue
 *   bucket_4 : 90+ days overdue
 *
 * Performance:
 *   Single hydrated query with eager-loaded counterparty. Grouping done
 *   in PHP. Tested OK at 50k open documents in MySQL 8 (sub-second per
 *   ageing run). For >250k open documents a nightly snapshot table
 *   approach is preferable — additive change, easy to introduce later
 *   without breaking the public surface of this class.
 *
 * Trial balance reconciliation:
 *   AR ageing total should equal AR control GL balance + sum of
 *   unallocated receipts (Phase 2c reconciliation command).
 *   AP ageing total should equal AP control GL balance + sum of
 *   unallocated payments (Suspense Payment 2910 net) — Phase 3e
 *   reconciliation command will verify this identity.
 */
abstract class AbstractAgeingService
{
    public function __construct(
        protected CurrentOrganization $currentOrganization,
    ) {
    }

    /**
     * Build the report.
     *
     * @param  CarbonImmutable|null  $asOf            Date to age against. Defaults to today.
     * @param  int|null              $counterpartyId Filter to a single counterparty.
     */
    public function build(?CarbonImmutable $asOf = null, ?int $counterpartyId = null): AgeingReport
    {
        $asOf = $asOf ?? CarbonImmutable::today();
        $bucketDays = config('finance.ageing.bucket_days', [30, 60, 90]);
        if (count($bucketDays) !== 3) {
            throw new \InvalidArgumentException(
                'finance.ageing.bucket_days must contain exactly 3 day boundaries; got '
                . count($bucketDays)
            );
        }

        $rows = $this->fetchOpenDocuments($counterpartyId);
        $byCounterparty = $rows->groupBy('counterparty_id');

        $counterpartyLines = [];
        $totals = $this->emptyBucketTotals();

        foreach ($byCounterparty as $cpId => $documents) {
            $first = $documents->first();
            $counterparty = $first->counterparty;
            if (! $counterparty) {
                // Orphan documents — skip but log.
                \Log::warning('Ageing report: counterparty missing for document', [
                    'service'        => static::class,
                    'counterparty_id'=> $cpId,
                    'doc_count'      => $documents->count(),
                ]);
                continue;
            }

            $cpTotals = $this->emptyBucketTotals();
            $balanceColumn = $this->balanceColumn();

            foreach ($documents as $doc) {
                $effectiveDueDate = $this->effectiveDueDate($doc);
                // diffInDays(false) returns negative if asOf is BEFORE due date,
                // which means the doc is NOT overdue.
                $daysOverdue = (int) $effectiveDueDate->diffInDays($asOf, false);

                $bucket = $this->bucketFor($daysOverdue, $bucketDays);
                $balance = (string) $doc->{$balanceColumn};

                $cpTotals[$bucket] = bcadd($cpTotals[$bucket], $balance, 2);
                $cpTotals['total'] = bcadd($cpTotals['total'], $balance, 2);
            }

            foreach ($cpTotals as $k => $v) {
                $totals[$k] = bcadd($totals[$k], $v, 2);
            }

            $oldest = $documents->sortBy(fn ($d) => $d->{$this->dateColumn()})->first();

            $counterpartyLines[] = new AgeingLine(
                counterparty:    $counterparty,
                invoiceCount:    $documents->count(),
                buckets:         $cpTotals,
                oldestInvoice:   $oldest,
                currencyCode:    $first->currency_code,
            );
        }

        // Sort largest exposure first.
        usort($counterpartyLines, function (AgeingLine $a, AgeingLine $b) {
            return bccomp($b->buckets['total'], $a->buckets['total'], 2);
        });

        return new AgeingReport(
            asOf:           $asOf,
            bucketDays:     $bucketDays,
            customerLines:  $counterpartyLines,
            grandTotals:    $totals,
            reportType:     $this->reportType(),
        );
    }

    /**
     * The Eloquent collection of open documents (Invoice or VendorInvoice).
     *
     * @return Collection
     */
    abstract protected function fetchOpenDocuments(?int $counterpartyId): Collection;

    /**
     * The column on the document carrying the outstanding balance.
     */
    abstract protected function balanceColumn(): string;

    /**
     * Primary date column on the document (invoice_date for both Invoice
     * and VendorInvoice). Used to find the oldest open document.
     */
    abstract protected function dateColumn(): string;

    /**
     * 'ar' or 'ap'. Drives view templates and CSV filenames.
     */
    abstract protected function reportType(): string;

    /**
     * Effective due date for a document: due_date if set, else invoice_date.
     * Returns CarbonImmutable.
     */
    protected function effectiveDueDate(object $doc): CarbonImmutable
    {
        $due = $doc->due_date ?? $doc->{$this->dateColumn()};
        return CarbonImmutable::parse((string) $due);
    }

    protected function bucketFor(int $daysOverdue, array $bucketDays): string
    {
        if ($daysOverdue <= 0) return 'current';
        if ($daysOverdue <= $bucketDays[0]) return 'bucket_1';
        if ($daysOverdue <= $bucketDays[1]) return 'bucket_2';
        if ($daysOverdue <= $bucketDays[2]) return 'bucket_3';
        return 'bucket_4';
    }

    protected function emptyBucketTotals(): array
    {
        return [
            'current'  => '0.00',
            'bucket_1' => '0.00',
            'bucket_2' => '0.00',
            'bucket_3' => '0.00',
            'bucket_4' => '0.00',
            'total'    => '0.00',
        ];
    }
}
