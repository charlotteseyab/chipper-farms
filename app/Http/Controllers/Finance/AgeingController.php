<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Counterparty;
use App\Services\Finance\Ageing\ApAgeingService;
use App\Services\Finance\Ageing\ArAgeingService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * Ageing reports controller — both AR (Phase 2d) and AP (Phase 3d).
 *
 * Permission map:
 *   index, ar, exportAr  : view_finance       (existing, broad)
 *   ap, exportAp         : view_ap_ageing     (new in Phase 3d, narrower)
 *
 * Why a separate AP perm: AR ageing has been visible to anyone with
 * view_finance since Phase 2d. AP exposes supplier balances, payment
 * patterns, and is operationally sensitive — narrower default.
 *
 * Both AR and AP exports require the existing export_finance perm.
 */
class AgeingController extends Controller
{
    /* ---------------- AR (existing surface, unchanged behaviour) ---------------- */

    /**
     * Backward-compat alias: /finance/ageing → AR ageing.
     */
    public function index(Request $request, ArAgeingService $service)
    {
        return $this->ar($request, $service);
    }

    public function ar(Request $request, ArAgeingService $service)
    {
        $asOf = $this->parseAsOf($request);
        $counterpartyId = $request->filled('counterparty_id')
            ? (int) $request->get('counterparty_id')
            : null;

        $report = $service->build($asOf, $counterpartyId);

        $counterparties = Counterparty::customers()->active()
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        return view('finance.ageing.index', [
            'report'          => $report,
            'asOf'            => $asOf,
            'counterpartyId'  => $counterpartyId,
            'counterparties'  => $counterparties,
            'reportType'      => 'ar',
        ]);
    }

    public function exportAr(Request $request, ArAgeingService $service)
    {
        if (! $request->user()?->hasPermission('export_finance')) {
            abort(403);
        }
        return $this->streamCsv($service->build($this->parseAsOf($request)));
    }

    /* ---------------- AP (new in 3d) ---------------- */

    public function ap(Request $request, ApAgeingService $service)
    {
        if (! $request->user()?->hasPermission('view_ap_ageing')) {
            abort(403);
        }

        $asOf = $this->parseAsOf($request);
        $counterpartyId = $request->filled('counterparty_id')
            ? (int) $request->get('counterparty_id')
            : null;

        $report = $service->build($asOf, $counterpartyId);

        $counterparties = Counterparty::suppliers()->active()
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        return view('finance.ageing.index', [
            'report'          => $report,
            'asOf'            => $asOf,
            'counterpartyId'  => $counterpartyId,
            'counterparties'  => $counterparties,
            'reportType'      => 'ap',
        ]);
    }

    public function exportAp(Request $request, ApAgeingService $service)
    {
        if (! $request->user()?->hasPermission('view_ap_ageing')) {
            abort(403);
        }
        if (! $request->user()?->hasPermission('export_finance')) {
            abort(403);
        }
        return $this->streamCsv($service->build($this->parseAsOf($request)));
    }

    /* ---------------- internals ---------------- */

    protected function parseAsOf(Request $request): CarbonImmutable
    {
        return $request->filled('as_of')
            ? CarbonImmutable::parse($request->get('as_of'))
            : CarbonImmutable::today();
    }

    protected function streamCsv(\App\Services\Finance\Ageing\AgeingReport $report)
    {
        $filename = $report->filenameSlug() . '_' . $report->asOf->format('Ymd') . '.csv';

        return response()->streamDownload(function () use ($report) {
            $out = fopen('php://output', 'w');

            $labels = $report->bucketLabels();
            $noun = $report->counterpartyNoun();

            fputcsv($out, [
                "{$noun} Code", "{$noun} Name", 'Currency', 'Doc Count',
                $labels['current'], $labels['bucket_1'], $labels['bucket_2'],
                $labels['bucket_3'], $labels['bucket_4'],
                'Total',
                $report->isAp() ? 'Tax ID' : 'Credit Limit',
                $report->isAp() ? '' : 'Over Limit?',
            ]);

            foreach ($report->customerLines as $line) {
                $extraA = $report->isAp()
                    ? ($line->counterparty->tax_id ?? '')
                    : ($line->counterparty->credit_limit ?? '');
                $extraB = $report->isAp()
                    ? ''
                    : ($line->exceedsCreditLimit() ? 'YES' : 'NO');

                fputcsv($out, [
                    $line->counterparty->code,
                    $line->counterparty->name,
                    $line->currencyCode,
                    $line->invoiceCount,
                    $line->buckets['current'],
                    $line->buckets['bucket_1'],
                    $line->buckets['bucket_2'],
                    $line->buckets['bucket_3'],
                    $line->buckets['bucket_4'],
                    $line->buckets['total'],
                    $extraA,
                    $extraB,
                ]);
            }

            fputcsv($out, [
                '', 'GRAND TOTAL', '', $report->customerCount(),
                $report->grandTotals['current'],
                $report->grandTotals['bucket_1'],
                $report->grandTotals['bucket_2'],
                $report->grandTotals['bucket_3'],
                $report->grandTotals['bucket_4'],
                $report->grandTotals['total'],
                '', '',
            ]);

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}