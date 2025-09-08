<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PigWeight;
use Carbon\Carbon;

class AdminController extends Controller
{
    /**
     * Get weight trends data for the admin dashboard chart
     */
    public function getWeightTrendsData(Request $request)
    {
        $fromDate = $request->get('from');
        $toDate = $request->get('to');
        
        // Validate dates
        if (!$fromDate || !$toDate) {
            return response()->json(['error' => 'From and to dates are required'], 400);
        }
        
        try {
            $from = Carbon::parse($fromDate);
            $to = Carbon::parse($toDate);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Invalid date format'], 400);
        }
        
        // Get weight trends data for the specified date range - average of latest weight per pig for each day
        $weightTrendsData = PigWeight::selectRaw('DATE(created_at) as date, AVG(weight) as avg_weight')
            ->whereIn('id', function($query) use ($from, $to) {
                $query->select(\DB::raw('MAX(id)'))
                      ->from('pig_weights')
                      ->whereBetween('created_at', [$from, $to])
                      ->groupBy('pig_id');
            })
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('date')
            ->orderBy('date')
            ->get();
        
        // Generate labels and values
        $labels = [];
        $values = [];
        
        if ($weightTrendsData->count() > 0) {
            $labels = $weightTrendsData->pluck('date')->map(function($date) {
                return Carbon::parse($date)->format('M j');
            })->toArray();
            $values = $weightTrendsData->pluck('avg_weight')->toArray();
        }
        // If no data, return empty arrays - no fallback data
        
        return response()->json([
            'labels' => $labels,
            'values' => $values
        ]);
    }
}
