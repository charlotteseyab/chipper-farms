<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PigManagement;
use App\Models\PigWeight;
use App\Helpers\FlashHelper;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;

class WeightManagementController extends Controller
{
    /**
     * Display a listing of all weight records
     */
    public function index(Request $request)
    {
        $query = PigWeight::with(['pig', 'weightAlerts']);

        // Filter by pig
        if ($request->pig_id) {
            $query->where('pig_id', $request->pig_id);
        }

        // Filter by date range
        if ($request->date_from) {
            $query->whereDate('weigh_date', '>=', $request->date_from);
        }
        if ($request->date_to) {
            $query->whereDate('weigh_date', '<=', $request->date_to);
        }

        // Filter by purpose
        if ($request->purpose) {
            $query->where('purpose', $request->purpose);
        }

        // Filter by weight range
        if ($request->weight_from) {
            $query->where('weight', '>=', $request->weight_from);
        }
        if ($request->weight_to) {
            $query->where('weight', '<=', $request->weight_to);
        }

        $weights = $query->orderBy('weigh_date', 'desc')->paginate(10);
        $weights->appends($request->query());

        // Get data for filters
        $pigs = PigManagement::orderBy('ear_tag_number')->get();
        $purposes = PigWeight::distinct()->pluck('purpose')->filter();

        return view('weights.index', compact('weights', 'pigs', 'purposes'));
    }

    /**
     * Show the form for creating a new weight record
     */
    public function create(Request $request)
    {
        $pigs = PigManagement::orderBy('ear_tag_number')->get();
        $selectedPig = null;

        if ($request->pig_id) {
            $selectedPig = PigManagement::find($request->pig_id);
        }

        return view('weights.create', compact('pigs', 'selectedPig'));
    }

    /**
     * Store a newly created weight record
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'pig_id' => 'required|exists:pigs_management,id',
            'weight_kg' => 'required|numeric|min:0.1|max:500',
            'weigh_date' => 'required|date|before_or_equal:today',
            'target_week' => 'required|integer|min:1|max:104',
            'weigh_time' => 'nullable|date_format:H:i',
            'purpose' => 'required|in:routine,breeding,health,sale,birth,weaning,maturity,other',
            'body_condition_score' => 'nullable|numeric|min:1|max:5',
            'health_status' => 'nullable|in:excellent,good,fair,poor',
            'notes' => 'nullable|string|max:1000',
            'week_notes' => 'nullable|string|max:500',
        ]);

        // Get pig for age calculation
        $pig = PigManagement::findOrFail($data['pig_id']);

        // Calculate actual age in weeks on weigh date
        $weighDate = Carbon::parse($data['weigh_date']);
        $actualAgeWeeks = $pig->date_of_birth ? $pig->date_of_birth->diffInWeeks($weighDate) : null;

        // Prepare data for model (match model field names)
        $weightData = [
            'pig_id' => $data['pig_id'],
            'weight' => $data['weight_kg'],
            'weight_unit' => 'kg',
            'weigh_date' => $data['weigh_date'],
            'target_week' => $data['target_week'],
            'actual_age_weeks' => $actualAgeWeeks,
            'purpose' => $data['purpose'],
            'body_condition_score' => $data['body_condition_score'] ?? null,
            'notes' => $data['notes'] ?? null,
            'week_notes' => $data['week_notes'] ?? null,
            'weighed_by' => auth()->user()->name ?? 'System',
            'is_estimated' => false,
        ];

        // Combine date and time
        if ($data['weigh_time']) {
            $weightData['weigh_date'] = Carbon::parse($data['weigh_date'])->format('Y-m-d') . ' ' . $data['weigh_time'];
        }

        PigWeight::create($weightData);

        return FlashHelper::successAndRedirect(
            'Weight record added successfully!',
            'weights.index'
        );
    }

    /**
     * Display weight reports and analytics
     */
    public function reports(Request $request)
    {
        $pig = null;
        $weights = collect();
        $analytics = [];

        if ($request->pig_id) {
            $pig = PigManagement::with(['weights' => function($query) {
                $query->orderBy('weigh_date', 'asc');
            }])->find($request->pig_id);

            if ($pig) {
                $weights = $pig->weights;
                $analytics = $this->calculateWeightAnalytics($weights);
            }
        }

        $pigs = PigManagement::orderBy('ear_tag_number')->get();

        return view('weights.reports', compact('pig', 'weights', 'analytics', 'pigs'));
    }

    /**
     * Calculate weight analytics for a pig
     */
    private function calculateWeightAnalytics($weights)
    {
        if ($weights->count() < 2) {
            return [];
        }

        $analytics = [];
        $weightData = $weights->sortBy('weigh_date');

        // Basic stats
        $analytics['total_records'] = $weightData->count();
        $analytics['first_weight'] = $weightData->first();
        $analytics['latest_weight'] = $weightData->last();
        $analytics['weight_gain'] = $analytics['latest_weight']->weight - $analytics['first_weight']->weight;

        // Calculate average daily gain
        $daysBetween = $analytics['first_weight']->weigh_date->diffInDays($analytics['latest_weight']->weigh_date);
        $analytics['avg_daily_gain'] = $daysBetween > 0 ? $analytics['weight_gain'] / $daysBetween : 0;

        // Growth periods
        $analytics['growth_periods'] = [];
        for ($i = 1; $i < $weightData->count(); $i++) {
            $current = $weightData->values()[$i];
            $previous = $weightData->values()[$i - 1];

            $days = $previous->weigh_date->diffInDays($current->weigh_date);
            $gain = $current->weight - $previous->weight;
            $dailyGain = $days > 0 ? $gain / $days : 0;

            $analytics['growth_periods'][] = [
                'from_date' => $previous->weigh_date,
                'to_date' => $current->weigh_date,
                'days' => $days,
                'weight_gain' => $gain,
                'daily_gain' => $dailyGain,
                'from_weight' => $previous->weight,
                'to_weight' => $current->weight,
            ];
        }

        return $analytics;
    }

    /**
     * Export weight report to PDF
     */
    public function exportPDF(Request $request)
    {
        $request->validate([
            'pig_id' => 'required|exists:pigs_management,id'
        ]);

        $pig = PigManagement::with(['weights' => function($query) {
            $query->orderBy('weigh_date', 'asc');
        }])->find($request->pig_id);

        if (!$pig) {
            return redirect()->route('weights.reports')->with('error', 'Pig not found.');
        }

        $weights = $pig->weights;
        $analytics = [];

        if ($weights->count() >= 2) {
            $analytics = $this->calculateWeightAnalytics($weights);
        }

        // Generate PDF
        $pdf = Pdf::loadView('weights.pdf-report', compact('pig', 'weights', 'analytics'));

        // Set PDF options
        $pdf->setPaper('A4', 'portrait');
        $pdf->setOptions([
            'isHtml5ParserEnabled' => true,
            'isPhpEnabled' => true,
            'defaultFont' => 'sans-serif'
        ]);

        $filename = 'weight-report-' . $pig->ear_tag_number . '-' . now()->format('Y-m-d') . '.pdf';

        return $pdf->download($filename);
    }
}
