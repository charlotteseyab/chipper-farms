<?php

namespace App\Services\Finance\Ageing;

use App\Models\Finance\Invoice;
use Illuminate\Support\Collection;

/**
 * AR (accounts receivable) ageing — outstanding customer invoices.
 *
 * Includes posted, non-cash invoices with balance_due > 0.
 * Excludes:
 *   - cash invoices (never hit AR)
 *   - drafts, cancelled, reversed invoices
 *
 * Trial-balance reconciliation: total of all customer ageing balances
 * should equal AR control GL balance + sum of unallocated receipts.
 */
class ArAgeingService extends AbstractAgeingService
{
    protected function fetchOpenDocuments(?int $counterpartyId): Collection
    {
        $orgId = $this->currentOrganization->id();

        return Invoice::query()
            ->where('status', Invoice::STATUS_POSTED)
            ->where('is_cash_invoice', false)
            ->where('balance_due', '>', 0)
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->when($counterpartyId, fn ($q) => $q->where('counterparty_id', $counterpartyId))
            ->with('counterparty:id,name,code,credit_limit,credit_hold_mode,currency_code,tax_id')
            ->orderBy('counterparty_id')
            ->orderBy('invoice_date')
            ->get();
    }

    protected function balanceColumn(): string
    {
        return 'balance_due';
    }

    protected function dateColumn(): string
    {
        return 'invoice_date';
    }

    protected function reportType(): string
    {
        return 'ar';
    }
}
