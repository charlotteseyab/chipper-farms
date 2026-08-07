<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Services\Finance\Overview\NeedsAttentionService;
use App\Support\CurrentOrganization;
use App\Support\Features\FeatureCatalog;
use App\Services\Organizations\OrganizationFeatureService;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

/**
 * Finance Overview — the farm-facing money screen (Phase 3, blueprint §22).
 *
 * WHAT THIS REPLACES AS THE DEFAULT
 * ---------------------------------
 * `FinanceDashboardController` describes itself as a "live snapshot of the
 * general ledger and posting activity" and leads with pending outbox events,
 * failed events, draft journal batches and trial-balance totals. That is a
 * useful screen — for an accountant or a support engineer debugging a posting
 * problem. It is not an answer to the questions an owner actually opens the app
 * with: how much cash do we have, who owes us, what is due.
 *
 * The ledger dashboard is NOT deleted. It moves under Advanced Accounting,
 * where the people who want it can still reach it. Blueprint §25 is explicit
 * that those modules are relocated, not removed.
 *
 * QUICK ACTIONS ARE FILTERED, NOT GREYED OUT
 * ------------------------------------------
 * An action the user cannot perform is omitted rather than shown disabled.
 * A farm operator does not benefit from seeing a button that will refuse them,
 * and prompt §29 asks for authorised actions only.
 */
class FinanceOverviewController extends Controller
{
    public function __construct(
        protected NeedsAttentionService $needsAttention,
        protected OrganizationFeatureService $features,
        protected CurrentOrganization $currentOrganization,
    ) {
    }

    public function __invoke(): View
    {
        $user         = auth()->user();
        $organization = $this->currentOrganization->get();

        // Attention items are filtered by the permission that would let the
        // viewer act on them. Showing an owner a problem only an accountant can
        // resolve is noise; showing it to nobody is how it stays unresolved.
        $attention = array_values(array_filter(
            $this->needsAttention->items(),
            fn (array $item): bool => $item['permission'] === null
                || $user?->hasPermission($item['permission']),
        ));

        return view('finance.overview', [
            'organization'  => $organization,
            'attention'     => $attention,
            'attentionTotal' => array_sum(array_column($attention, 'count')),
            'quickActions'  => $this->quickActions(),
        ]);
    }

    /**
     * @return list<array{label:string, route:string, icon:string}>
     */
    protected function quickActions(): array
    {
        $user = auth()->user();

        $candidates = [
            ['Record Expense', 'finance.expenses.create', 'fa-solid fa-receipt',
                FeatureCatalog::FINANCE_QUICK_EXPENSE, ['record_expenses']],
            ['Record Purchase', 'finance.purchases.create', 'fa-solid fa-cart-plus',
                FeatureCatalog::FINANCE_SIMPLE_PURCHASE, ['record_purchases', 'manage_vendor_invoices']],
            ['Create Invoice', 'finance.invoices.create', 'fa-solid fa-file-invoice',
                FeatureCatalog::FINANCE_SALES_MONEY_IN, ['manage_invoices']],
            ['Record Payment', 'finance.payments.create', 'fa-solid fa-money-bill-transfer',
                FeatureCatalog::FINANCE_CASH_BANK_MOMO, ['manage_payments']],
            ['Record Receipt', 'finance.receipts.create', 'fa-solid fa-hand-holding-dollar',
                FeatureCatalog::FINANCE_SALES_MONEY_IN, ['manage_receipts']],
            ['Record Loan', 'finance.loans.create', 'fa-solid fa-landmark',
                FeatureCatalog::FINANCE_LOANS, ['manage_loans']],
        ];

        $out = [];
        foreach ($candidates as [$label, $route, $icon, $feature, $permissions]) {
            // Routes for phases not yet built are skipped rather than rendered
            // as a dead link. Several of these land in Phases 4-7.
            if (! Route::has($route)) {
                continue;
            }
            if (! $this->features->usable($feature)) {
                continue;
            }
            if (! $user?->hasAnyPermission($permissions)) {
                continue;
            }

            $out[] = ['label' => $label, 'route' => $route, 'icon' => $icon];
        }

        return $out;
    }
}
