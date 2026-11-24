<?php

namespace App\Services\Sales;

use App\Models\PigSale;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Generates sales exports (CSV + PDF).
 *
 * Extracted from PigManagementController::salesExportCsv (was line 2456) and
 * ::salesExportPdf (was line 2535). The original implementations duplicated
 * the filter block across both methods and with salesIndex (line 2392).
 * This service exposes buildFilteredQuery() so the PigSaleController index
 * method can share the same filter semantics.
 *
 * Uses display_sold_by accessor (Batch A) so exports correctly render the
 * seller for sales created before or after the sold_by_user_id migration.
 */
class SalesExportService
{
    /**
     * Apply request filters to a PigSale query.
     * Centralised so index, csv export, and pdf export can't drift.
     */
    public function buildFilteredQuery(Request $request): Builder
    {
        $query = PigSale::with(['pig.earTagAssignments.tag.color']);

        if ($request->filled('buyer_name')) {
            $query->where('buyer_name', 'like', '%' . $request->buyer_name . '%');
        }
        if ($request->filled('sale_purpose')) {
            $query->where('sale_purpose', $request->sale_purpose);
        }
        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }
        if ($request->filled('date_from')) {
            $query->where('sale_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->where('sale_date', '<=', $request->date_to);
        }

        return $query->orderBy('sale_date', 'desc');
    }

    public function csv(Request $request): StreamedResponse
    {
        $sales = $this->buildFilteredQuery($request)->get();

        $filename = 'sales_records_' . now()->format('Y-m-d_H-i-s') . '.csv';
        $headers  = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () use ($sales) {
            $file = fopen('php://output', 'w');

            fputcsv($file, [
                'Pig Code',
                'Ear Tag',
                'Sale Date',
                'Buyer Name',
                'Buyer Contact',
                'Sale Weight (kg)',
                'Weight Type',
                'Sale Price (₵)',
                'Price per kg (₵)',
                'Sale Purpose',
                'Payment Status',
                'Payment Method',
                'Sold By',
                'Transport Cost (₵)',
                'Notes',
            ]);

            foreach ($sales as $sale) {
                fputcsv($file, [
                    $sale->pig->code ?? 'N/A',
                    $sale->pig?->tagLabelAt($sale->sale_date) ?? 'N/A',
                    $sale->sale_date->format('Y-m-d'),
                    $sale->buyer_name,
                    $sale->buyer_contact ?? '',
                    $sale->sale_weight ?? '',
                    ucfirst($sale->weight_type ?? 'live'),
                    number_format($sale->sale_price, 2),
                    $sale->price_per_kg ? number_format($sale->price_per_kg, 2) : '',
                    ucfirst($sale->sale_purpose),
                    ucfirst($sale->payment_status),
                    $sale->payment_method ? ucfirst(str_replace('_', ' ', $sale->payment_method)) : '',
                    $sale->display_sold_by ?? '',  // Uses the Batch A accessor.
                    $sale->transport_cost ? number_format($sale->transport_cost, 2) : '',
                    $sale->notes ?? '',
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function pdf(Request $request)
    {
        if ($tooMany = \App\Support\PdfExportLimit::check($this->buildFilteredQuery($request)->count())) {
            return $tooMany;
        }
        $sales = $this->buildFilteredQuery($request)->get();

        $stats = [
            'total_sales'    => $sales->count(),
            'total_value'    => $sales->sum('sale_price'),
            'average_price'  => $sales->avg('sale_price'),
            'average_weight' => $sales->where('sale_weight', '>', 0)->avg('sale_weight'),
        ];

        $pdf      = \PDF::loadView('exports.sales-pdf', compact('sales', 'stats'));
        $filename = 'sales_records_' . now()->format('Y-m-d_H-i-s') . '.pdf';

        return $pdf->download($filename);
    }
}