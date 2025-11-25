<?php

namespace App\Http\Controllers;

use App\Models\Farrowing;
use App\Models\PigletDeath;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB; // <-- REQUIRED for DATE_FORMAT

class FarrowingController extends Controller
{
    /**
     * Display a listing of the Farrowing Records with optional server-side search.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\View\View
     */
    public function index(Request $request) // <-- Now accepting $request
    {
        // Start building the query
        $query = Farrowing::with(['sow', 'boar', 'recordedBy']);

        // Check for a search query parameter and apply server-side filtering
        if ($search = $request->get('search')) {
            $searchTerm = '%' . $search . '%';

            $query->where(function ($q) use ($searchTerm) {
                // 1. Search by Sow Tag/Code (Female)
                $q->whereHas('sow', function ($sowQuery) use ($searchTerm) {
                    $sowQuery->where('ear_tag_number', 'like', $searchTerm)
                             ->orWhere('code', 'like', $searchTerm); // Added search by 'code'
                })
                // 2. Search by Boar Tag/Code (Male)
                ->orWhereHas('boar', function ($boarQuery) use ($searchTerm) {
                    $boarQuery->where('ear_tag_number', 'like', $searchTerm)
                             ->orWhere('code', 'like', $searchTerm); // Added search by 'code'
                })
                // 3. Search by Farrowing Type or Difficulty
                ->orWhere('farrowing_type', 'like', $searchTerm)
                ->orWhere('farrowing_difficulty', 'like', $searchTerm)
                // 4. Search by Farrowing Date
                // Note: Searching dates as strings can be slow/inefficient, but works for simple filtering.
                ->orWhere(DB::raw('DATE_FORMAT(farrowing_date, "%Y-%m-%d")'), 'like', $searchTerm);
            });
        }
        
        // Apply sorting and pagination to the potentially filtered query
        $farrowingRecords = $query
            ->orderBy('farrowing_date', 'desc')
            ->paginate(15)
            // CRITICAL: Append the search term to pagination links
            ->appends($request->only('search')); 

        // --- Stats Compilation (This remains unaffected by the search query, which is usually correct for overall stats) ---
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

    public function recordPigletDeath(Request $request, Farrowing $farrowing)
    {
        $data = $request->validate([
            'death_date' => 'required|date|before_or_equal:today',
            'count' => 'required|integer|min:1',
            'cause' => 'nullable|string|max:120',
            'notes' => 'nullable|string|max:1000',
        ]);

        $alreadyRecorded = (int) $farrowing->pigletDeaths()->sum('count');
        $liveBirths = (int) ($farrowing->live_births ?? 0);

        if (($alreadyRecorded + $data['count']) > $liveBirths) {
            return back()->withErrors([
                'count' => 'Recorded piglet deaths cannot exceed live births for this farrowing.',
            ])->withInput();
        }

        $data['recorded_by'] = auth()->id();
        $data['farrowing_id'] = $farrowing->id;

        \App\Models\PigletDeath::create($data);

        return back()->with('success', 'Piglet death recorded.');
    }

    public function show(Farrowing $farrowing)
    {
        $farrowing->load(['sow', 'boar', 'recordedBy', 'pigletDeaths.recordedBy']);

        $genderDistribution = $farrowing->gender_distribution;
        $pigletDeaths = $farrowing->pigletDeaths()->orderByDesc('death_date')->get();
        $totalPigletDeaths = (int) $pigletDeaths->sum('count');
        $netLivePiglets = max(0, (int) ($farrowing->live_births ?? 0) - $totalPigletDeaths);

        return view('farrowing.show', [
            'farrowing' => $farrowing,
            'genderDistribution' => $genderDistribution,
            'pigletDeaths' => $pigletDeaths,
            'totalPigletDeaths' => $totalPigletDeaths,
            'netLivePiglets' => $netLivePiglets,
        ]);
    }

    public function edit(Farrowing $farrowing)
    {
        $farrowing->load(['sow', 'boar', 'pigletDeaths']);
        
        // Get all sows and boars for dropdowns
        $sows = \App\Models\PigManagement::where('gender', 'F')
            ->where('stock_type', 'breeding')
            ->orderBy('ear_tag_number')
            ->get();
            
        $boars = \App\Models\PigManagement::where('gender', 'M')
            ->where('stock_type', 'breeding')
            ->orderBy('ear_tag_number')
            ->get();

        $recordedPigletDeaths = (int) $farrowing->pigletDeaths->sum('count');
        $netLivePiglets = max(0, (int) ($farrowing->live_births ?? 0) - $recordedPigletDeaths);

        return view('farrowing.edit', compact('farrowing', 'sows', 'boars', 'recordedPigletDeaths', 'netLivePiglets'));
    }

    public function update(Request $request, Farrowing $farrowing)
    {
        $data = $request->validate([
            'sow_id' => 'required|exists:pigs_management,id',
            'boar_id' => 'required|exists:pigs_management,id',
            'farrowing_date' => 'required|date|before_or_equal:today',
            'farrowing_time' => 'nullable|date_format:H:i',
            'total_piglets_born' => 'required|integer|min:0|max:25',
            'live_births' => 'required|integer|min:0|max:25',
            'stillbirths' => 'required|integer|min:0|max:25',
            'number_of_males' => 'nullable|integer|min:0|max:25',
            'number_of_females' => 'nullable|integer|min:0|max:25',
            'average_birth_weight' => 'nullable|numeric|min:0.1|max:5.0',
            'total_litter_weight' => 'nullable|numeric|min:0.1|max:100.0',
            'farrowing_type' => 'required|in:natural,assisted,cesarean',
            'farrowing_difficulty' => 'required|in:easy,moderate,difficult',
            'gestation_length_days' => 'nullable|integer|min:100|max:130',
            'complications' => 'nullable|string|max:1000',
            'notes' => 'nullable|string|max:1000',
            'sow_condition' => 'required|in:excellent,good,fair,poor',
            'colostrum_available' => 'boolean',
            'functional_teats' => 'nullable|integer|min:0|max:20',
            'sow_weight_after_farrowing' => 'nullable|numeric|min:50|max:500',
            'follow_up_date' => 'nullable|date|after:farrowing_date',
            'status' => 'required|in:active,inactive,archived',
        ]);

        // Validate that live births + stillbirths = total piglets born (cast to integers to avoid strict type mismatch)
        $liveBirths = (int) ($data['live_births'] ?? 0);
        $stillBirths = (int) ($data['stillbirths'] ?? 0);
        $totalBorn = (int) ($data['total_piglets_born'] ?? 0);
        if (($liveBirths + $stillBirths) !== $totalBorn) {
            return back()->withErrors([
                'total_piglets_born' => 'Total piglets born must equal live births + stillbirths.'
            ])->withInput();
        }

        // Validate that males + females = live births (only if at least one is provided and not empty)
        // Skip validation if both values are 0 (likely means fields were left empty)
        $males = (int) $data['number_of_males'];
        $females = (int) $data['number_of_females'];
        
        if ($males > 0 || $females > 0) {
            // At least one field has a non-zero value, validate the math
            if (($males + $females) !== $data['live_births']) {
                return back()->withErrors([
                    'live_births' => 'Live births must equal number of males + females.'
                ])->withInput();
            }
        }

        // Handle checkbox properly
        $data['colostrum_available'] = $request->has('colostrum_available');

        // Ensure gender fields have default values when empty
        $data['number_of_males'] = $data['number_of_males'] ?: 0;
        $data['number_of_females'] = $data['number_of_females'] ?: 0;

        $farrowing->update($data);

        return redirect()->route('farrowing.show', $farrowing)
            ->with('success', 'Farrowing record updated successfully!');
    }

    public function updatePigletDeath(Request $request, PigletDeath $pigletDeath)
    {
        $data = $request->validate([
            'death_date' => 'required|date|before_or_equal:today',
            'count' => 'required|integer|min:1',
            'cause' => 'nullable|string|max:120',
            'notes' => 'nullable|string|max:1000',
        ]);

        $farrowing = $pigletDeath->farrowing;
        $currentTotal = (int) $farrowing->pigletDeaths()->sum('count');
        $proposedTotal = $currentTotal - (int) $pigletDeath->count + $data['count'];
        $liveBirths = (int) ($farrowing->live_births ?? 0);

        if ($proposedTotal > $liveBirths) {
            return back()->withErrors([
                'count' => 'Recorded piglet deaths cannot exceed live births for this farrowing.',
            ])->withInput();
        }

        $data['recorded_by'] = auth()->id();
        $pigletDeath->update($data);

        return back()->with('success', 'Piglet death updated successfully.');
    }

    public function destroyPigletDeath(PigletDeath $pigletDeath)
    {
        $pigletDeath->delete();

        return back()->with('success', 'Piglet death record deleted.');
    }

    /**
     * Display piglet tracking page grouped by sow (mother)
     * Shows all piglets, stillbirths, and deaths organized by mother
     */
    public function pigletTracking(Request $request)
    {
        // Get all sows that have farrowing records
        $query = \App\Models\PigManagement::whereHas('farrowingsAsSow')
            ->with([
                'farrowingsAsSow' => function($q) {
                    $q->with(['pigletDeaths', 'boar'])
                      ->orderBy('farrowing_date', 'desc');
                }
            ])
            ->where('gender', 'F')
            ->where('stock_type', 'breeding');

        // Filter by sow if search provided
        if ($search = $request->get('search')) {
            $searchTerm = '%' . $search . '%';
            $query->where(function($q) use ($searchTerm) {
                $q->where('ear_tag_number', 'like', $searchTerm)
                  ->orWhere('code', 'like', $searchTerm);
            });
        }

        $sows = $query->orderBy('ear_tag_number')->get();

        // Get weaning age from config (same as dashboard)
        $weaningAge = config('breeding.cycle.birth_to_weaning_days', 45);

        // Calculate statistics for each sow and overall
        $overallStats = [
            'total_sows' => $sows->count(),
            'total_farrowings' => 0,
            'total_piglets_born' => 0,
            'total_live_births' => 0,
            'total_stillbirths' => 0,
            'total_deaths' => 0,
            'total_surviving' => 0, // Represents all live piglets ever born
            'current_surviving' => 0, // Piglets still on-farm (not yet weaned)
        ];

        // Process each sow's data
        $sowsWithStats = $sows->map(function($sow) use (&$overallStats, $weaningAge) {
            $sowFarrowings = $sow->farrowingsAsSow;
            $sowStats = [
                'total_farrowings' => $sowFarrowings->count(),
                'total_piglets_born' => 0,
                'total_live_births' => 0,
                'total_stillbirths' => 0,
                'total_deaths' => 0,
                'total_surviving' => 0,
            ];

            $farrowingsWithStats = $sowFarrowings->map(function($farrowing) use (&$sowStats, &$overallStats, $weaningAge) {
                $deaths = (int) $farrowing->pigletDeaths->sum('count');
                $liveBirths = (int) ($farrowing->live_births ?? 0);
                $stillbirths = (int) ($farrowing->stillbirths ?? 0);
                $totalBorn = (int) ($farrowing->total_piglets_born ?? 0);
                $surviving = max(0, $liveBirths - $deaths);

                // Check if this farrowing is recent and active (matches dashboard logic)
                $isRecent = $farrowing->farrowing_date && 
                           $farrowing->farrowing_date >= now()->subDays($weaningAge);
                $isActive = $farrowing->status === 'active';

                // Sow stats
                $sowStats['total_piglets_born'] += $totalBorn;
                $sowStats['total_live_births'] += $liveBirths;
                $sowStats['total_stillbirths'] += $stillbirths;
                $sowStats['total_deaths'] += $deaths;
                $sowStats['total_surviving'] += $liveBirths;

                // Overall stats (all farrowings)
                $overallStats['total_farrowings']++;
                $overallStats['total_piglets_born'] += $totalBorn;
                $overallStats['total_live_births'] += $liveBirths;
                $overallStats['total_stillbirths'] += $stillbirths;
                $overallStats['total_deaths'] += $deaths;
                $overallStats['total_surviving'] += $liveBirths;

                // Current surviving (only recent active farrowings - matches dashboard)
                if ($isRecent && $isActive) {
                    $overallStats['current_surviving'] += $surviving;
                }

                return [
                    'farrowing' => $farrowing,
                    'deaths' => $deaths,
                    'surviving' => $surviving,
                    'live_births' => $liveBirths,
                    'stillbirths' => $stillbirths,
                    'total_born' => $totalBorn,
                    'is_recent' => $isRecent && $isActive,
                ];
            });

            return [
                'sow' => $sow,
                'farrowings' => $farrowingsWithStats,
                'stats' => $sowStats,
            ];
        });

        return view('piglets.tracking', compact('sowsWithStats', 'overallStats'));
    }
}
