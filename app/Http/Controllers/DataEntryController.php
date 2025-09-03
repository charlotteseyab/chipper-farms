<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PigWeight;
use Carbon\Carbon;

class DataEntryController extends Controller
{
    public function getDailyWeightsData(Request $request)
    {
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');
        
        // Default to last 7 days if no dates provided
        if (!$startDate || !$endDate) {
            $endDate = now()->format('Y-m-d');
            $startDate = now()->subDays(6)->format('Y-m-d');
        }
        
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        
        // Generate date range
        $dates = [];
        $weightsData = [];
        
        $current = $start->copy();
        while ($current->lte($end)) {
            $date = $current->format('Y-m-d');
            $count = PigWeight::whereDate('created_at', $date)->count();
            
            $dates[] = $current->format('M j');
            $weightsData[] = $count;
            
            $current->addDay();
        }
        
        return response()->json([
            'labels' => $dates,
            'data' => $weightsData
        ]);
    }
}
