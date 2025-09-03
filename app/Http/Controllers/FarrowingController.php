<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Farrowing;

class FarrowingController extends Controller
{
    public function index()
    {
        $farrowingRecords = Farrowing::with(['sow', 'boar', 'recordedBy'])
            ->orderBy('farrowing_date', 'desc')
            ->paginate(15);

        $stats = [
            'total_farrowings' => Farrowing::count(),
            'farrowings_this_month' => Farrowing::thisMonth()->count(),
            'average_litter_size' => Farrowing::avg('live_births'),
            'average_survival_rate' => Farrowing::selectRaw('AVG(CASE WHEN total_piglets_born > 0 THEN (live_births / total_piglets_born) * 100 ELSE 0 END) as avg_survival')
                ->value('avg_survival'),
            'natural_farrowings' => Farrowing::where('farrowing_type', 'natural')->count(),
            'assisted_farrowings' => Farrowing::where('farrowing_type', 'assisted')->count(),
            'cesarean_farrowings' => Farrowing::where('farrowing_type', 'cesarean')->count(),
            'excellent_condition' => Farrowing::where('sow_condition', 'excellent')->count(),
            'good_condition' => Farrowing::where('sow_condition', 'good')->count(),
            'fair_condition' => Farrowing::where('sow_condition', 'fair')->count(),
            'poor_condition' => Farrowing::where('sow_condition', 'poor')->count(),
        ];

        return view('farrowing.index', compact('farrowingRecords', 'stats'));
    }

    public function show(Farrowing $farrowing)
    {
        $farrowing->load(['sow', 'boar', 'recordedBy']);

        $genderDistribution = $farrowing->gender_distribution;

        return view('farrowing.show', [
            'farrowing' => $farrowing,
            'genderDistribution' => $genderDistribution,
        ]);
    }
}
