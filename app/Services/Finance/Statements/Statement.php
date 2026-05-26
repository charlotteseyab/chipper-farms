<?php

namespace App\Services\Finance\Statements;

use App\Models\Counterparty;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Customer activity statement — point-in-time snapshot of a customer's
 * AR activity over a date range.
 *
 * Immutable value object. Renderable by both HTML view (statement.show)
 * and PDF generator (StatementPdfRenderer).
 */
final class Statement
{
    /**
     * @param  array<\App\Services\Finance\Allocations\LedgerEntry>  $entries
     * @param  Collection<\App\Models\Finance\Invoice>  $openInvoices
     */
    public function __construct(
        public readonly Counterparty $customer,
        public readonly CarbonImmutable $startDate,
        public readonly CarbonImmutable $endDate,
        public readonly CarbonImmutable $generatedAt,
        public readonly string $openingBalance,
        public readonly string $closingBalance,
        public readonly array $entries,
        public readonly Collection $openInvoices,
        public readonly int $invoicesCount,
        public readonly int $receiptsCount,
        public readonly string $invoicesTotal,
        public readonly string $receiptsTotal,
    ) {
    }

    public function isEmpty(): bool
    {
        return empty($this->entries) && $this->openInvoices->isEmpty()
            && bccomp($this->openingBalance, '0', 2) === 0;
    }

    /**
     * Filename when downloaded as PDF.
     */
    public function suggestedFilename(): string
    {
        $code = $this->customer->code ?: ('CUST' . $this->customer->id);
        return sprintf(
            'statement_%s_%s_%s.pdf',
            $code,
            $this->startDate->format('Ymd'),
            $this->endDate->format('Ymd'),
        );
    }
}
