<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Storefront\StorefrontOrder;
use App\Support\CurrentOrganization;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Read-only storefront sales reporting. Aggregates orders by status, payment
 * method and fulfilment, plus realised revenue (completed orders). Gated by
 * view_storefront_reports.
 */
class StorefrontReportController extends Controller
{
    public function __construct(protected CurrentOrganization $currentOrganization)
    {
    }

    public function sales(Request $request): View
    {
        $orgId = $this->currentOrganization->requireId();

        $from = $request->date('from') ?? now()->startOfMonth();
        $to   = $request->date('to') ?? now()->endOfDay();

        $base = StorefrontOrder::query()
            ->where('organization_id', $orgId)
            ->whereBetween('placed_at', [$from, $to]);

        $byStatus = (clone $base)->selectRaw('status, count(*) as n, coalesce(sum(total),0) as total')
            ->groupBy('status')->get()->keyBy('status');

        $byMethod = (clone $base)->selectRaw('payment_method, count(*) as n, coalesce(sum(total),0) as total')
            ->groupBy('payment_method')->get()->keyBy('payment_method');

        $completed = (clone $base)->where('status', StorefrontOrder::STATUS_COMPLETED);

        $summary = [
            'orders_total'    => (clone $base)->count(),
            'orders_open'     => (clone $base)->open()->count(),
            'orders_completed' => (clone $completed)->count(),
            'revenue_realised' => (string) (clone $completed)->sum('total'),
            'revenue_pipeline' => (string) (clone $base)->open()->sum('total'),
        ];

        return view('storefront.admin.reports.sales', [
            'from' => $from, 'to' => $to,
            'byStatus' => $byStatus, 'byMethod' => $byMethod, 'summary' => $summary,
        ]);
    }
}
