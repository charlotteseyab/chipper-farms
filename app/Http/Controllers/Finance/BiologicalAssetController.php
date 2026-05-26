<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Services\Finance\BiologicalAssets\BiologicalAssetReconciliationService;
use App\Services\Finance\BiologicalAssets\BiologicalAssetStageResolver;
use App\Services\Finance\BiologicalAssets\FairValueDisclosureService;
use App\Models\Finance\BiologicalAssetStageBalance;
use App\Support\CurrentOrganization;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Phase 7 — biological assets reporting.
 *
 *   index        Stage balance summary (carrying value by stage) + the
 *                three-way reconciliation status.
 *   fairValue    IAS 41 fair-value disclosure note (cost vs FVLCTS by stage).
 *
 * Permission: view_financial_reports (read-only reports).
 */
class BiologicalAssetController extends Controller
{
    public function __construct(protected CurrentOrganization $currentOrg)
    {
        $this->middleware('auth');
        $this->middleware('permission:view_financial_reports');
    }

    public function index(
        Request $request,
        BiologicalAssetStageResolver $resolver,
        BiologicalAssetReconciliationService $recon,
    ) {
        $orgId = $this->currentOrg->id();
        $asOf = $request->filled('as_of_date')
            ? Carbon::parse($request->get('as_of_date'))->endOfDay()
            : Carbon::now()->endOfDay();

        $balances = BiologicalAssetStageBalance::query()
            ->when($orgId !== null,
                fn ($q) => $q->where('organization_id', $orgId),
                fn ($q) => $q->whereNull('organization_id'))
            ->with('group')
            ->get();

        // Aggregate by stage for the summary.
        $byStage = [];
        foreach ($resolver->allStageKeys() as $stageKey) {
            $rows = $balances->where('stage_key', $stageKey);
            if ($rows->isEmpty()) {
                continue;
            }
            $meta = $resolver->stageMeta($stageKey);
            $byStage[$stageKey] = [
                'label'          => $meta['label'] ?? $stageKey,
                'account_code'   => $meta['account_code'] ?? null,
                'classification' => $meta['classification'] ?? null,
                'pig_count'      => $rows->sum(fn ($r) => (int) $r->pig_count),
                'weight_kg'      => $rows->reduce(fn ($c, $r) => bcadd($c, (string) $r->weight_kg, 4), '0'),
                'carrying_value' => $rows->reduce(fn ($c, $r) => bcadd($c, (string) $r->carrying_value, 4), '0'),
            ];
        }

        return view('finance.biological-assets.index', [
            'byStage'        => $byStage,
            'reconciliation' => $recon->run($asOf),
            'asOf'           => $asOf,
        ]);
    }

    public function fairValue(FairValueDisclosureService $service)
    {
        return view('finance.biological-assets.fair-value', [
            'disclosure' => $service->generate(),
        ]);
    }
}
