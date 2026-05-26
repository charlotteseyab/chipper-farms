<?php

namespace App\Services\Finance\Ageing;

use App\Models\Finance\VendorInvoice;
use Illuminate\Support\Collection;

/**
 * AP (accounts payable) ageing — outstanding vendor invoices.
 *
 * Includes posted, non-reversed vendor invoices with balance_due > 0.
 * Excludes:
 *   - drafts, pending_approval, cancelled, reversed invoices
 *
 * Trial-balance reconciliation: total of all supplier ageing balances
 * should equal AP control GL balance + sum of unattributed payments
 * (Suspense Payment 2910 net by counterparty).
 *
 * Note on overdue interpretation:
 *   For AR, overdue means "the customer owes us and is late paying."
 *   For AP, overdue means "we owe the supplier and are late paying."
 *   Same arithmetic, opposite operational meaning. The 90+ bucket
 *   on AP indicates relationship risk — supplier may stop deliveries
 *   or charge late fees.
 */
class ApAgeingService extends AbstractAgeingService
{
    protected function fetchOpenDocuments(?int $counterpartyId): Collection
    {
        $orgId = $this->currentOrganization->id();

        return VendorInvoice::query()
            ->where('status', VendorInvoice::STATUS_POSTED)
            ->where('balance_due', '>', 0)
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->when($counterpartyId, fn ($q) => $q->where('counterparty_id', $counterpartyId))
            ->with([
                'counterparty:id,name,code,currency_code,tax_id',
                'counterparty.supplierCategory:id,code,name',
            ])
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
        return 'ap';
    }
}
