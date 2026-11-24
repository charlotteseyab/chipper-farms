<?php

namespace App\Services\Deaths;

use App\Models\PigDeath;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Generates death exports (CSV + PDF) with the adult/piglet split the
 * system uses everywhere else.
 *
 * Extracted from PigManagementController::deathsExportCsv (was line 2720) and
 * ::deathsExportPdf (was line 2813).
 *
 * Adult vs. piglet threshold is 60 days (from the original queries'
 * DATEDIFF rule). Kept identical so the export behaviour matches the
 * index view exactly.
 */
class DeathsExportService
{
    protected const PIGLET_THRESHOLD_DAYS = 60;

    /**
     * @return array{adult: \Illuminate\Database\Eloquent\Collection, piglet: \Illuminate\Database\Eloquent\Collection}
     */
    public function buildFilteredCollections(Request $request): array
    {
        $adultQuery = PigDeath::with(['pig.earTagAssignments.tag.color', 'recordedBy'])
            ->whereHas('pig', function ($q) {
                $q->whereRaw('DATEDIFF(death_date, date_of_birth) >= ?', [self::PIGLET_THRESHOLD_DAYS]);
            });

        $pigletQuery = PigDeath::with(['pig.earTagAssignments.tag.color', 'recordedBy'])
            ->whereHas('pig', function ($q) {
                $q->whereRaw('DATEDIFF(death_date, date_of_birth) < ?', [self::PIGLET_THRESHOLD_DAYS]);
            });

        $this->applyFilters($adultQuery, $request);
        $this->applyFilters($pigletQuery, $request);

        return [
            'adult'  => $adultQuery->orderBy('death_date', 'desc')->get(),
            'piglet' => $pigletQuery->orderBy('death_date', 'desc')->get(),
        ];
    }

    protected function applyFilters(Builder $query, Request $request): void
    {
        if ($request->filled('death_cause')) {
            $query->where('death_cause', $request->death_cause);
        }
        if ($request->filled('date_from')) {
            $query->where('death_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->where('death_date', '<=', $request->date_to);
        }
    }

    public function csv(Request $request): StreamedResponse
    {
        $collections = $this->buildFilteredCollections($request);
        $adultDeaths  = $collections['adult'];
        $pigletDeaths = $collections['piglet'];

        $filename = 'death_records_' . now()->format('Y-m-d_H-i-s') . '.csv';
        $headers  = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () use ($adultDeaths, $pigletDeaths) {
            $file = fopen('php://output', 'w');

            fputcsv($file, [
                'Category',
                'Pig Code',
                'Ear Tag',
                'Death Date',
                'Age at Death',
                'Death Cause',
                'Final Weight (kg)',
                'Disposal Method',
                'Recorded By',
                'Notes',
            ]);

            foreach ($adultDeaths as $death) {
                fputcsv($file, [
                    'Adult (60+ days)',
                    $death->pig->code ?? 'N/A',
                    $death->pig?->tagLabelAt($death->death_date) ?? 'N/A',
                    $death->death_date->format('Y-m-d'),
                    $death->age_at_death_display ?? 'Unknown',
                    $death->death_cause_display,
                    $death->final_weight ? number_format($death->final_weight, 2) : '',
                    $death->disposal_method_display,
                    $death->recordedBy->name ?? 'System',
                    $death->death_notes ?? '',
                ]);
            }

            foreach ($pigletDeaths as $death) {
                fputcsv($file, [
                    'Piglet (< 60 days)',
                    $death->pig->code ?? 'N/A',
                    $death->pig?->tagLabelAt($death->death_date) ?? 'N/A',
                    $death->death_date->format('Y-m-d'),
                    $death->age_at_death_display ?? 'Unknown',
                    $death->death_cause_display,
                    $death->final_weight ? number_format($death->final_weight, 2) : '',
                    $death->disposal_method_display,
                    $death->recordedBy->name ?? 'System',
                    $death->death_notes ?? '',
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function pdf(Request $request)
    {
        $collections  = $this->buildFilteredCollections($request);
        $adultDeaths  = $collections['adult'];
        $pigletDeaths = $collections['piglet'];
        if ($tooMany = \App\Support\PdfExportLimit::check($adultDeaths->count() + $pigletDeaths->count())) {
            return $tooMany;
        }

        $stats = [
            'total_deaths'  => $adultDeaths->count() + $pigletDeaths->count(),
            'adult_deaths'  => $adultDeaths->count(),
            'piglet_deaths' => $pigletDeaths->count(),
            'recent_deaths' => $adultDeaths->where('death_date', '>=', now()->subDays(30))->count()
                + $pigletDeaths->where('death_date', '>=', now()->subDays(30))->count(),
        ];

        $pdf      = \PDF::loadView('exports.deaths-pdf', compact('adultDeaths', 'pigletDeaths', 'stats'));
        $filename = 'death_records_' . now()->format('Y-m-d_H-i-s') . '.pdf';

        return $pdf->download($filename);
    }
}