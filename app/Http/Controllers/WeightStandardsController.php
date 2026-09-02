<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ExpectedWeightStandard;
use App\Helpers\FlashHelper;

class WeightStandardsController extends Controller
{
    /**
     * Display a listing of weight standards
     */
    public function index(Request $request)
    {
        $request->validate([
            'breed' => ['nullable', \Illuminate\Validation\Rule::in(array_keys(\App\Models\Setting::animalBreeds()))],
            'gender' => ['nullable', 'in:M,F'],
            'stock_type' => ['nullable', 'in:standard,breeding'],
            'is_active' => ['nullable', 'in:0,1'],
            'week_from' => ['nullable', 'integer', 'min:1', 'max:200'],
            'week_to' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $query = ExpectedWeightStandard::with('creator');

        if ($request->filled('breed')) {
            $query->where('breed', $request->breed);
        }

        if ($request->filled('gender')) {
            $query->where('gender', $request->gender);
        }

        if ($request->filled('stock_type')) {
            $query->where('stock_type', $request->stock_type);
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', (bool) $request->is_active);
        }

        if ($request->filled('week_from')) {
            $query->where('week_number', '>=', (int) $request->week_from);
        }

        if ($request->filled('week_to')) {
            $query->where('week_number', '<=', (int) $request->week_to);
        }

        $standards = $query->orderBy('breed')
            ->orderBy('gender')
            ->orderBy('stock_type')
            ->orderBy('week_number')
            ->paginate(20)
            ->withQueryString();

        return view('weight-standards.index', compact('standards'));
    }

    /**
     * Show the form for creating a new weight standard
     */
    public function create()
    {
        return view('weight-standards.create');
    }

    /**
     * Store a newly created weight standard
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'breed' => ['required', \Illuminate\Validation\Rule::in(array_keys(\App\Models\Setting::animalBreeds()))],
            'gender' => 'required|in:M,F',
            'stock_type' => 'required|in:standard,breeding',
            'week_number' => 'required|integer|min:1|max:200', // Up to 2 years
            'expected_min_weight' => 'required|numeric|min:0.1|max:500',
            'expected_max_weight' => 'required|numeric|min:0.1|max:500|gt:expected_min_weight',
            'optimal_weight' => 'required|numeric|min:0.1|max:500|gte:expected_min_weight|lte:expected_max_weight',
            'notes' => 'nullable|string|max:1000',
            'is_active' => 'boolean'
        ]);

        $data['created_by'] = auth()->id();
        // Handle checkbox properly - unchecked checkboxes don't send any value
        $data['is_active'] = $request->has('is_active');

        // Check if standard already exists
        $existing = ExpectedWeightStandard::where('breed', $data['breed'])
                                         ->where('gender', $data['gender'])
                                         ->where('stock_type', $data['stock_type'])
                                         ->where('week_number', $data['week_number'])
                                         ->first();

        if ($existing) {
            return back()->withErrors([
                'week_number' => 'A weight standard already exists for this breed, gender, stock type, and week combination.'
            ])->withInput();
        }

        $weightStandard = ExpectedWeightStandard::create($data);

        // Reprocess existing weight records for this pig type to generate new alerts
        try {
            $processedCount = \App\Models\PigWeight::reprocessAlertsForPigType(
                $data['breed'],
                $data['gender'],
                $data['stock_type']
            );

            $message = "Weight standard created successfully!";
            if ($processedCount > 0) {
                $message .= " {$processedCount} existing weight record(s) have been re-evaluated against the new standard.";
            }

            return FlashHelper::successAndRedirect($message, 'weight-standards.index');
        } catch (\Exception $e) {
            \Log::error("Failed to reprocess weight alerts after creating standard: " . $e->getMessage());
            
            return FlashHelper::successAndRedirect(
                'Weight standard created successfully! (Note: Some existing weight records may need manual review)',
                'weight-standards.index'
            );
        }
    }

    /**
     * Display the specified weight standard
     */
    public function show(ExpectedWeightStandard $weightStandard)
    {
        $weightStandard->load('creator');
        return view('weight-standards.show', compact('weightStandard'));
    }

    /**
     * Show the form for editing the specified weight standard
     */
    public function edit(ExpectedWeightStandard $weightStandard)
    {
        return view('weight-standards.edit', compact('weightStandard'));
    }

    /**
     * Update the specified weight standard
     */
    public function update(Request $request, ExpectedWeightStandard $weightStandard)
    {
        $data = $request->validate([
            'expected_min_weight' => 'required|numeric|min:0.1|max:500',
            'expected_max_weight' => 'required|numeric|min:0.1|max:500|gt:expected_min_weight',
            'optimal_weight' => 'required|numeric|min:0.1|max:500|gte:expected_min_weight|lte:expected_max_weight',
            'notes' => 'nullable|string|max:1000',
            'is_active' => 'boolean'
        ]);

        // Handle checkbox properly - unchecked checkboxes don't send any value
        $data['is_active'] = $request->has('is_active');

        // Store the original values for reprocessing
        $originalBreed = $weightStandard->breed;
        $originalGender = $weightStandard->gender;
        $originalStockType = $weightStandard->stock_type;

        $weightStandard->update($data);

        // Reprocess existing weight records for this pig type to generate new alerts
        try {
            $processedCount = \App\Models\PigWeight::reprocessAlertsForPigType(
                $originalBreed,
                $originalGender,
                $originalStockType
            );

            $message = "Weight standard updated successfully!";
            if ($processedCount > 0) {
                $message .= " {$processedCount} existing weight record(s) have been re-evaluated against the updated standard.";
            }

            return FlashHelper::successAndRedirect($message, 'weight-standards.index');
        } catch (\Exception $e) {
            \Log::error("Failed to reprocess weight alerts after updating standard: " . $e->getMessage());
            
            return FlashHelper::successAndRedirect(
                'Weight standard updated successfully! (Note: Some existing weight records may need manual review)',
                'weight-standards.index'
            );
        }
    }

    /**
     * Remove the specified weight standard
     */
    public function destroy(ExpectedWeightStandard $weightStandard)
    {
        // Capture pig type before delete
        $breed = $weightStandard->breed;
        $gender = $weightStandard->gender;
        $stockType = $weightStandard->stock_type;

        $weightStandard->delete();

        // Reprocess affected weights so alerts reflect missing standards immediately
        $processedCount = 0;
        try {
            $processedCount = \App\Models\PigWeight::reprocessAlertsForPigType($breed, $gender, $stockType);
        } catch (\Exception $e) {
            \Log::error("Failed to reprocess weight alerts after deleting standard: " . $e->getMessage());
        }

        $message = 'Weight standard deleted successfully!';
        if ($processedCount > 0) {
            $message .= " {$processedCount} existing weight record(s) have been re-evaluated against the updated standards.";
        }

        return FlashHelper::successAndRedirect($message, 'weight-standards.index');
    }

    /**
     * Show bulk create form
     */
    public function bulkCreate()
    {
        $this->authorizeFarmAdmin();

        return view('weight-standards.bulk-create');
    }

    /**
     * Store multiple weight standards at once
     */
    public function bulkStore(Request $request)
    {
        $this->authorizeFarmAdmin();
        $organizationId = app(\App\Support\CurrentOrganization::class)->requireId();

        $data = $request->validate([
            'breed' => ['required', \Illuminate\Validation\Rule::in(array_keys(\App\Models\Setting::animalBreeds()))],
            'gender' => 'required|in:M,F',
            'stock_type' => 'required|in:standard,breeding',
            'week_from' => 'required|integer|min:1|max:200',
            'week_to' => 'required|integer|min:1|max:200|gte:week_from',
            'min_weight_start' => 'required|numeric|min:0.1|max:500',
            'max_weight_start' => 'required|numeric|min:0.1|max:500|gt:min_weight_start',
            'optimal_weight_start' => 'required|numeric|min:0.1|max:500|gte:min_weight_start|lte:max_weight_start',
            'weekly_growth_rate' => 'required|numeric|min:0|max:10', // kg per week
            'notes' => 'nullable|string|max:1000'
        ]);

        $createdCount = 0;
        $overwrittenCount = 0;

        for ($week = $data['week_from']; $week <= $data['week_to']; $week++) {
            // If a standard already exists for this combination, delete it so we overwrite
            $existing = ExpectedWeightStandard::where('breed', $data['breed'])
                                             ->where('organization_id', $organizationId)
                                             ->where('gender', $data['gender'])
                                             ->where('stock_type', $data['stock_type'])
                                             ->where('week_number', $week)
                                             ->first();

            if ($existing) {
                $existing->delete();
                $overwrittenCount++;
            }

            // Calculate weights for this week
            $weeksFromStart = $week - $data['week_from'];
            $growthAmount = $weeksFromStart * $data['weekly_growth_rate'];

            $minWeight = $data['min_weight_start'] + $growthAmount;
            $maxWeight = $data['max_weight_start'] + $growthAmount;
            $optimalWeight = $data['optimal_weight_start'] + $growthAmount;

            ExpectedWeightStandard::create([
                'organization_id' => $organizationId,
                'breed' => $data['breed'],
                'gender' => $data['gender'],
                'stock_type' => $data['stock_type'],
                'week_number' => $week,
                'expected_min_weight' => $minWeight,
                'expected_max_weight' => $maxWeight,
                'optimal_weight' => $optimalWeight,
                'notes' => $data['notes'],
                'created_by' => auth()->id(),
                'is_active' => true
            ]);

            $createdCount++;
        }

        // Reprocess existing weight records for this pig type to generate new alerts
        $processedCount = 0;
        try {
            $processedCount = \App\Models\PigWeight::reprocessAlertsForPigType(
                $data['breed'],
                $data['gender'],
                $data['stock_type']
            );
        } catch (\Exception $e) {
            \Log::error("Failed to reprocess weight alerts after bulk creating standards: " . $e->getMessage());
        }

        $message = "Created {$createdCount} weight standards.";
        if ($overwrittenCount > 0) {
            $message .= " Overwrote {$overwrittenCount} existing standards.";
        }
        if ($processedCount > 0) {
            $message .= " {$processedCount} existing weight record(s) have been re-evaluated against the new standards.";
        }

        return FlashHelper::successAndRedirect($message, 'weight-standards.index');
    }

    private function authorizeFarmAdmin(): void
    {
        $user = auth()->user();

        abort_unless($user && $user->isAdmin() && $user->organization_id, 403);
    }
}
