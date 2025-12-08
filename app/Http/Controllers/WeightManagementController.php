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
        // Subquery: get latest weigh_date for each pig_id
        $latestSub = \DB::table('pig_weights as pw2')
            ->selectRaw('MAX(weigh_date) as max_date, pig_id')
            ->groupBy('pig_id');

        // Main query: join on pig_id and weigh_date to get only latest per pig
        $query = PigWeight::with(['pig', 'weightAlerts'])
            ->joinSub($latestSub, 'latest', function($join) {
                $join->on('pig_weights.pig_id', '=', 'latest.pig_id')
                     ->on('pig_weights.weigh_date', '=', 'latest.max_date');
            });

        // Apply filters (only those that make sense for latest weights)
        if ($request->pig_id) {
            $query->where('pig_weights.pig_id', $request->pig_id);
        }
        if ($request->date_from) {
            $query->whereDate('pig_weights.weigh_date', '>=', $request->date_from);
        }
        if ($request->date_to) {
            $query->whereDate('pig_weights.weigh_date', '<=', $request->date_to);
        }
        if ($request->purpose) {
            $query->where('pig_weights.purpose', $request->purpose);
        }
        if ($request->weight_from) {
            $query->where('pig_weights.weight', '>=', $request->weight_from);
        }
        if ($request->weight_to) {
            $query->where('pig_weights.weight', '<=', $request->weight_to);
        }

        $weights = $query->orderBy('pig_weights.weigh_date', 'desc')
                        ->select('pig_weights.*') // select only columns from pig_weights
                        ->paginate(10);
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
            'target_week' => 'required|integer|min:1|max:200',
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

		// Ensure we use date-only to match DB column type
		$dateOnly = $weighDate->toDateString();

		// Prepare data for model (match model field names)
		$weightData = [
			'pig_id' => $data['pig_id'],
			'weight' => $data['weight_kg'],
			'weight_unit' => 'kg',
			'weigh_date' => $dateOnly,
			'target_week' => $data['target_week'],
			'actual_age_weeks' => $actualAgeWeeks,
			'purpose' => $data['purpose'],
			'body_condition_score' => $data['body_condition_score'] ?? null,
			'health_status' => $data['health_status'] ?? null,
			'notes' => $data['notes'] ?? null,
			'week_notes' => $data['week_notes'] ?? null,
			'weighed_by' => auth()->user()->name ?? 'System',
			'is_estimated' => false,
		];

		// Upsert by (pig_id, weigh_date)
		$existing = PigWeight::where('pig_id', $data['pig_id'])
			->whereDate('weigh_date', $dateOnly)
			->first();

		if ($existing) {
			$existing->fill($weightData);
			$existing->save();
			$message = 'Weight record updated successfully!';
		} else {
			PigWeight::create($weightData);
			$message = 'Weight record added successfully!';
		}

		return FlashHelper::successAndRedirect(
			$message,
			'weights.index'
		);
	}

    /**
     * Show the form for editing the specified weight record
     */
    public function edit(PigWeight $weight)
    {
        $pigs = PigManagement::orderBy('ear_tag_number')->get();
        $selectedPig = $weight->pig;

        return view('weights.edit', compact('weight', 'pigs', 'selectedPig'));
    }

    /**
     * Update the specified weight record
     */
    public function update(Request $request, PigWeight $weight)
    {
        $data = $request->validate([
            'pig_id' => 'required|exists:pigs_management,id',
            'weight_kg' => 'required|numeric|min:0.1|max:500',
            'weigh_date' => 'required|date|before_or_equal:today',
            'target_week' => 'required|integer|min:1|max:200',
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

        // Ensure we use date-only to match DB column type
        $dateOnly = $weighDate->toDateString();

        // Prepare data for model (match model field names)
        $weightData = [
            'pig_id' => $data['pig_id'],
            'weight' => $data['weight_kg'],
            'weight_unit' => 'kg',
            'weigh_date' => $dateOnly,
            'target_week' => $data['target_week'],
            'actual_age_weeks' => $actualAgeWeeks,
            'purpose' => $data['purpose'],
            'body_condition_score' => $data['body_condition_score'] ?? null,
            'health_status' => $data['health_status'] ?? null,
            'notes' => $data['notes'] ?? null,
            'week_notes' => $data['week_notes'] ?? null,
            'weighed_by' => auth()->user()->name ?? 'System',
            'is_estimated' => false,
        ];

        // Update the weight record
        $weight->fill($weightData);
        $weight->save();

        // Regenerate weight alerts for this weight
        $weight->regenerateAlerts();

        return FlashHelper::successAndRedirect(
            'Weight record updated successfully!',
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

        if ($weights->count() >= 2) {
            $analytics = $this->calculateWeightAnalytics($weights);
        
            // Add phase info to each growth period
            $analytics['growth_periods'] = collect($analytics['growth_periods'])->map(function ($period) {
                $endWeight = $period['to_weight'];
        
                if ($endWeight < 25) {
                    $period['phase'] = 'Starter';
                } elseif ($endWeight < 60) {
                    $period['phase'] = 'Grower';
                } else {
                    $period['phase'] = 'Finisher';
                }
        
                return $period;
            })->toArray();
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

    /**
     * Export all weights (filtered) as PDF
     */
    public function exportAllPDF(Request $request)
    {
        // Apply same filters as index
        $latestSub = \DB::table('pig_weights as pw2')
            ->selectRaw('MAX(weigh_date) as max_date, pig_id')
            ->groupBy('pig_id');

        $query = PigWeight::with(['pig', 'weightAlerts'])
            ->joinSub($latestSub, 'latest', function($join) {
                $join->on('pig_weights.pig_id', '=', 'latest.pig_id')
                     ->on('pig_weights.weigh_date', '=', 'latest.max_date');
            });

        if ($request->pig_id) {
            $query->where('pig_weights.pig_id', $request->pig_id);
        }
        if ($request->date_from) {
            $query->whereDate('pig_weights.weigh_date', '>=', $request->date_from);
        }
        if ($request->date_to) {
            $query->whereDate('pig_weights.weigh_date', '<=', $request->date_to);
        }
        if ($request->purpose) {
            $query->where('pig_weights.purpose', $request->purpose);
        }
        if ($request->weight_from) {
            $query->where('pig_weights.weight', '>=', $request->weight_from);
        }
        if ($request->weight_to) {
            $query->where('pig_weights.weight', '<=', $request->weight_to);
        }

        $weights = $query->orderBy('pig_weights.pig_id')->orderBy('pig_weights.weigh_date', 'desc')
            ->select('pig_weights.*')->get();

        // Load filter params for display
        $filters = $request->only(['pig_id', 'purpose', 'weight_from', 'weight_to', 'date_from', 'date_to']);

        $pdf = Pdf::loadView('weights.all-pdf-report', compact('weights', 'filters'));
        $pdf->setPaper('A4', 'landscape');
        $pdf->setOptions([
            'isHtml5ParserEnabled' => true,
            'isPhpEnabled' => true,
            'defaultFont' => 'sans-serif'
        ]);

        $filename = 'weights-all-' . now()->format('Y-m-d') . '.pdf';
        return $pdf->download($filename);
    }

    /**
     * Get previous weight for a pig
     */
    public function getPreviousWeight($pigId)
    {
        $pig = PigManagement::findOrFail($pigId);
        
        $previousWeight = PigWeight::where('pig_id', $pigId)
            ->orderBy('weigh_date', 'desc')
            ->first();

        if (!$previousWeight) {
            return response()->json([
                'success' => false,
                'message' => 'No previous weight found',
                'data' => null
            ]);
        }

        $daysSince = $previousWeight->weigh_date->diffInDays(now());

        return response()->json([
            'success' => true,
            'data' => [
                'weight' => $previousWeight->weight,
                'date' => $previousWeight->weigh_date->format('Y-m-d'),
                'days_since' => $daysSince,
                'purpose' => $previousWeight->purpose,
                'target_week' => $previousWeight->target_week
            ]
        ]);
    }

    /**
     * Get expected weight for a pig based on age and breed
     */
    public function getExpectedWeight(Request $request, $pigId)
    {
        $pig = PigManagement::findOrFail($pigId);
        $referenceDate = $request->query('date') ? Carbon::parse($request->query('date')) : now();
        $ifUserWeek = $request->query('week');
        if (is_numeric($ifUserWeek)) {
            $requestedWeek = max(0, (int) $ifUserWeek);
        } else {
            $requestedWeek = null;
        }
        $ageInWeeks = $requestedWeek ?? (
            $pig->date_of_birth
                ? max(0, $pig->date_of_birth->diffInWeeks($referenceDate))
                : $pig->current_age_in_weeks
        );
        $ageSourceNote = $requestedWeek !== null
            ? 'Using selected tracking week'
            : 'Based on pig\'s actual age';
        
        // Get weight standards for this pig's breed, gender, stock type and age
        $weightStandard = \App\Models\ExpectedWeightStandard::where('breed', $pig->breed)
            ->where('gender', $pig->gender)
            ->where('stock_type', $pig->stock_type)
            ->where('week_number', $ageInWeeks)
            ->where('is_active', true)
            ->first();

        if (!$weightStandard) {
            if ($requestedWeek !== null) {
                return response()->json([
                    'success' => false,
                    'message' => "No expected-weight standard defined for week {$requestedWeek}.",
                    'data' => [
                        'week_number' => $requestedWeek,
                        'pig_age_weeks' => $ageInWeeks,
                        'note' => $ageSourceNote . '. No standard available for this week.'
                    ]
                ]);
            }

            // Try to find the closest week standard when the week wasn't manually selected
            $closestStandard = \App\Models\ExpectedWeightStandard::where('breed', $pig->breed)
                ->where('gender', $pig->gender)
                ->where('stock_type', $pig->stock_type)
                ->where('is_active', true)
                ->orderByRaw('ABS(week_number - ?)', [$ageInWeeks])
                ->first();

            if ($closestStandard) {
                return response()->json([
                'success' => true,
                'data' => [
                    'min_weight' => $closestStandard->expected_min_weight,
                    'max_weight' => $closestStandard->expected_max_weight,
                    'optimal_weight' => $closestStandard->optimal_weight,
                    'week_number' => $closestStandard->week_number,
                    'pig_age_weeks' => $ageInWeeks,
                    'note' => $ageSourceNote . '. Using closest available standard (week ' . $closestStandard->week_number . ')'
                ]
            ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'No weight standards found for this breed',
                'data' => null
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'min_weight' => $weightStandard->expected_min_weight,
                'max_weight' => $weightStandard->expected_max_weight,
                'optimal_weight' => $weightStandard->optimal_weight,
                'week_number' => $weightStandard->week_number,
                'pig_age_weeks' => $ageInWeeks,
                'note' => $ageSourceNote . '. Exact match for week ' . $weightStandard->week_number
            ]
        ]);
    }
}
