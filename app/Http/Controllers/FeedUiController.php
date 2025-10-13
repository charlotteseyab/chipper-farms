<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
// CRITICAL: This line tells PHP where the FeedSchedule model lives.
use App\Models\FeedSchedule; 
use App\Models\PigManagement; // Assuming PigManagement is used in other methods
use App\Models\GroupFeeding;
use App\Models\PigWeight;
use App\Models\FeedAllocation; // Model to track individual pig feed events
use Carbon\Carbon;

class FeedUiController extends Controller
{
    /**
     * Shows the main feed schedule (primarily for historical bulk entries).
     */
    public function schedule()
{
    $tab = request('tab', 'current'); // detect active tab
    $today = now()->toDateString();
    $statuses = request('status') ? explode(',', request('status')) : ['planned','fed','skipped'];

    if ($tab === 'current') {
        // Today tab: use $from and $to from request or default to today
        $from = request('from', $today);
        $to = request('to', $today);

        // Fetching FeedSchedule entries (originally for bulk/group schedules)
        $entries = \App\Models\FeedSchedule::with(['group.structure','structure','feedType','feeder'])
            ->whereBetween('scheduled_for', [$from, $to])
            ->whereIn('status', $statuses)
            ->orderBy('scheduled_for', 'desc') 
            ->orderBy('group_id')
            ->paginate(50);
    } else {
        // Previous schedules tab: automatically load all previous schedules by default
        $query = \App\Models\FeedSchedule::with(['group.structure','structure','feedType','feeder'])
            ->whereDate('scheduled_for', '<', $today) // all past schedules
            ->whereIn('status', $statuses)
            ->orderBy('scheduled_for', 'desc') 
            ->orderBy('group_id');

        // Apply date filter only if user selects it
        if (request()->filled('from')) {
            $query->whereDate('scheduled_for', '>=', request('from'));
        }
        if (request()->filled('to')) {
            $query->whereDate('scheduled_for', '<=', request('to'));
        }

        $entries = $query->paginate(50);

        // Set $from and $to for Blade date picker inputs (optional)
        $from = request('from') ?? null;
        $to = request('to') ?? null;
    }

    return view('feeds.schedule', compact('entries', 'from', 'to', 'statuses', 'tab'));
}
    

    public function reports(Request $request)
    {
        $from = $request->start_date ?? now()->subDays(30)->toDateString();
        $to = $request->end_date ?? now()->toDateString();

        // Get feed schedules
        $schedules = \App\Models\FeedSchedule::with(['feedType.recipe.items.ingredient'])
            ->whereBetween('scheduled_for', [$from, $to])
            ->orderBy('scheduled_for')
            ->get();

        $totalScheduledKg = (float) $schedules->sum('planned_amount');

        $byType = $schedules->groupBy('feed_type_id')->map(function ($group) {
            return [
                'name' => optional($group->first()->feedType)->name ?? ('#'.$group->first()->feed_type_id),
                'count' => $group->count(),
                'total_amount' => (float) $group->sum('planned_amount'),
            ];
        })->sortByDesc('total_amount');

        // Ingredient usage - show all ingredients with details
        $ingredientUsage = [];
        $ingredientDetails = [];
        foreach ($schedules as $sch) {
            $ft = $sch->feedType;
            if (!$ft || !$ft->recipe) continue;
            foreach ($ft->recipe->items as $ri) {
                $ingName = optional($ri->ingredient)->name ?? 'Item '.$ri->ingredient_id;
                $perKg = (float)($ri->percentage ?? 0)/100;
                $qty = $perKg * (float)$sch->planned_amount;
                
                if (!isset($ingredientUsage[$ingName])) {
                    $ingredientUsage[$ingName] = 0.0;
                    $ingredientDetails[$ingName] = [
                        'name' => $ingName,
                        'total_used' => 0.0,
                        'feed_types' => [],
                        'protein_percentage' => optional($ri->ingredient)->protein_percentage ?? 0,
                        'energy_kcal' => optional($ri->ingredient)->energy_kcal_per_kg ?? 0,
                        'cost_per_kg' => optional($ri->ingredient)->cost_per_unit ?? 0,
                    ];
                }
                $ingredientUsage[$ingName] += $qty;
                $ingredientDetails[$ingName]['total_used'] += $qty;
                $ingredientDetails[$ingName]['feed_types'][] = $ft->name;
            }
        }
        
        // Remove duplicates from feed_types arrays
        foreach ($ingredientDetails as $name => $details) {
            $ingredientDetails[$name]['feed_types'] = array_unique($details['feed_types']);
        }
        
        arsort($ingredientUsage);

        // --- Feed vs Pig Weights using latest weight per pig ---
        $fromCarbon = Carbon::parse($from);
        $toCarbon = Carbon::parse($to);

        $avgWeightByDateData = PigWeight::selectRaw('DATE(created_at) as date, AVG(weight) as avg_weight')
            ->whereIn('id', function($query) use ($fromCarbon, $toCarbon) {
                $query->select(\DB::raw('MAX(id)'))
                      ->from('pig_weights')
                      ->whereBetween('created_at', [$fromCarbon, $toCarbon])
                      ->groupBy('pig_id');
            })
            ->whereBetween('created_at', [$fromCarbon, $toCarbon])
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $avgWeightByDate = $avgWeightByDateData->pluck('avg_weight', 'date');

        $feedByDate = $schedules->groupBy('scheduled_for')->map(function ($group) {
            return (float) $group->sum('planned_amount');
        });

        // --- Structure consumption breakdowns ---
        // Prefer counting only completed feedings; if none exist, fall back to all statuses
        $completed = $schedules->where('status', 'fed');
        $source = $completed->isNotEmpty() ? $completed : $schedules;

        // Map structure IDs to names for quick lookup
        $structureNames = \App\Models\Structure::pluck('name', 'id');

        // Daily: group by structure and calendar day within range
        $dailyStructureTotals = $source
            ->groupBy(function ($item) { return optional($item->scheduled_for)->format('Y-m-d'); })
            ->map(function ($byDate) use ($structureNames) {
                return $byDate->groupBy('structure_id')->map(function ($rows, $structureId) use ($structureNames) {
                    return [
                        'structure_id' => $structureId,
                        'structure_name' => $structureNames[$structureId] ?? ('Structure #'.$structureId),
                        'total' => (float) $rows->sum('planned_amount'),
                    ];
                });
            });

        // Monthly: group by structure and YYYY-MM
        $monthlyStructureTotals = $source
            ->groupBy(function ($item) { return optional($item->scheduled_for)->format('Y-m'); })
            ->map(function ($byMonth) use ($structureNames) {
                return $byMonth->groupBy('structure_id')->map(function ($rows, $structureId) use ($structureNames) {
                    return [
                        'structure_id' => $structureId,
                        'structure_name' => $structureNames[$structureId] ?? ('Structure #'.$structureId),
                        'total' => (float) $rows->sum('planned_amount'),
                    ];
                });
            });

        // Yearly: group by structure and YYYY
        $yearlyStructureTotals = $source
            ->groupBy(function ($item) { return optional($item->scheduled_for)->format('Y'); })
            ->map(function ($byYear) use ($structureNames) {
                return $byYear->groupBy('structure_id')->map(function ($rows, $structureId) use ($structureNames) {
                    return [
                        'structure_id' => $structureId,
                        'structure_name' => $structureNames[$structureId] ?? ('Structure #'.$structureId),
                        'total' => (float) $rows->sum('planned_amount'),
                    ];
                });
            });

        return view('feeds.reports', [
            'from' => $from,
            'to' => $to,
            'totalScheduledKg' => $totalScheduledKg,
            'byType' => $byType,
            'ingredientUsage' => $ingredientUsage,
            'ingredientDetails' => $ingredientDetails,
            'avgWeightByDate' => $avgWeightByDate,
            'feedByDate' => $feedByDate,
            'dailyStructureTotals' => $dailyStructureTotals,
            'monthlyStructureTotals' => $monthlyStructureTotals,
            'yearlyStructureTotals' => $yearlyStructureTotals,
        ]);
    }

    public function exportReportsPdf(Request $request)
    {
        $from = $request->start_date ?? now()->subDays(30)->toDateString();
        $to = $request->end_date ?? now()->toDateString();

        $schedules = \App\Models\FeedSchedule::with(['feedType.recipe.items.ingredient'])
            ->whereBetween('scheduled_for', [$from, $to])
            ->orderBy('scheduled_for')
            ->get();

        $totalScheduledKg = (float) $schedules->sum('planned_amount');
        $byType = $schedules->groupBy('feed_type_id')->map(function ($group) {
            return [
                'name' => optional($group->first()->feedType)->name ?? ('#'.$group->first()->feed_type_id),
                'count' => $group->count(),
                'total_amount' => (float) $group->sum('planned_amount'),
            ];
        })->sortByDesc('total_amount');

        // Ingredient usage - show all ingredients with details for PDF
        $ingredientUsage = [];
        $ingredientDetails = [];
        foreach ($schedules as $sch) {
            $ft = $sch->feedType;
            if (!$ft || !$ft->recipe) continue;
            foreach ($ft->recipe->items as $ri) {
                $ingName = optional($ri->ingredient)->name ?? 'Item '.$ri->ingredient_id;
                $perKg = (float)($ri->percentage ?? 0)/100;
                $qty = $perKg * (float)$sch->planned_amount;
                
                if (!isset($ingredientUsage[$ingName])) {
                    $ingredientUsage[$ingName] = 0.0;
                    $ingredientDetails[$ingName] = [
                        'name' => $ingName,
                        'total_used' => 0.0,
                        'feed_types' => [],
                        'protein_percentage' => optional($ri->ingredient)->protein_percentage ?? 0,
                        'energy_kcal' => optional($ri->ingredient)->energy_kcal_per_kg ?? 0,
                        'cost_per_kg' => optional($ri->ingredient)->cost_per_unit ?? 0,
                    ];
                }
                $ingredientUsage[$ingName] += $qty;
                $ingredientDetails[$ingName]['total_used'] += $qty;
                $ingredientDetails[$ingName]['feed_types'][] = $ft->name;
            }
        }
        
        // Remove duplicates from feed_types arrays
        foreach ($ingredientDetails as $name => $details) {
            $ingredientDetails[$name]['feed_types'] = array_unique($details['feed_types']);
        }
        
        arsort($ingredientUsage);

        // --- Feed vs Pig Weights using latest weight per pig ---
        $fromCarbon = Carbon::parse($from);
        $toCarbon = Carbon::parse($to);

        $avgWeightByDateData = PigWeight::selectRaw('DATE(created_at) as date, AVG(weight) as avg_weight')
            ->whereIn('id', function($query) use ($fromCarbon, $toCarbon) {
                $query->select(\DB::raw('MAX(id)'))
                      ->from('pig_weights')
                      ->whereBetween('created_at', [$fromCarbon, $toCarbon])
                      ->groupBy('pig_id');
            })
            ->whereBetween('created_at', [$fromCarbon, $toCarbon])
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $avgWeightByDate = $avgWeightByDateData->pluck('avg_weight', 'date');

        $feedByDate = $schedules->groupBy('scheduled_for')->map(fn($g) => (float)$g->sum('planned_amount'));

        // =======================================================================
        // FIX: Re-added Structure consumption breakdowns logic from reports()
        // =======================================================================
        
        // --- Structure consumption breakdowns ---
        // Prefer counting only completed feedings; if none exist, fall back to all statuses
        $completed = $schedules->where('status', 'fed');
        $source = $completed->isNotEmpty() ? $completed : $schedules;

        // Map structure IDs to names for quick lookup
        $structureNames = \App\Models\Structure::pluck('name', 'id');

        // Daily: group by structure and calendar day within range
        $dailyStructureTotals = $source
            ->groupBy(function ($item) { return optional($item->scheduled_for)->format('Y-m-d'); })
            ->map(function ($byDate) use ($structureNames) {
                return $byDate->groupBy('structure_id')->map(function ($rows, $structureId) use ($structureNames) {
                    return [
                        'structure_id' => $structureId,
                        'structure_name' => $structureNames[$structureId] ?? ('Structure #'.$structureId),
                        'total' => (float) $rows->sum('planned_amount'),
                    ];
                });
            });

        // Monthly: group by structure and YYYY-MM
        $monthlyStructureTotals = $source
            ->groupBy(function ($item) { return optional($item->scheduled_for)->format('Y-m'); })
            ->map(function ($byMonth) use ($structureNames) {
                return $byMonth->groupBy('structure_id')->map(function ($rows, $structureId) use ($structureNames) {
                    return [
                        'structure_id' => $structureId,
                        'structure_name' => $structureNames[$structureId] ?? ('Structure #'.$structureId),
                        'total' => (float) $rows->sum('planned_amount'),
                    ];
                });
            });

        // Yearly: group by structure and YYYY
        $yearlyStructureTotals = $source
            ->groupBy(function ($item) { return optional($item->scheduled_for)->format('Y'); })
            ->map(function ($byYear) use ($structureNames) {
                return $byYear->groupBy('structure_id')->map(function ($rows, $structureId) use ($structureNames) {
                    return [
                        'structure_id' => $structureId,
                        'structure_name' => $structureNames[$structureId] ?? ('Structure #'.$structureId),
                        'total' => (float) $rows->sum('planned_amount'),
                    ];
                });
            });

        // =======================================================================
        // END FIX
        // =======================================================================

        $pdf = \PDF::loadView('feeds.reports_pdf', [
            'from' => $from,
            'to' => $to,
            'totalScheduledKg' => $totalScheduledKg,
            'byType' => $byType,
            'ingredientUsage' => $ingredientUsage,
            'ingredientDetails' => $ingredientDetails,
            'avgWeightByDate' => $avgWeightByDate,
            'feedByDate' => $feedByDate,
            'schedules' => $schedules,
            'dailyStructureTotals' => $dailyStructureTotals, // Now defined
            'monthlyStructureTotals' => $monthlyStructureTotals, // Now defined
            'yearlyStructureTotals' => $yearlyStructureTotals, // Now defined
        ])->setPaper('a4', 'portrait');

        return $pdf->download("feed_reports_{$from}_to_{$to}.pdf");
    }

   
    /**
     * RENAMED (Was createAllocation): This is the entry point for the new Granular Feed Allocation form.
     */
    public function createSchedule()
    {
        // Loads data for the cascading form to select pig, structure, and feed type
        $structures = \App\Models\Structure::orderBy('name')->get(['id','name']);
        $feedTypes = \App\Models\FeedType::orderBy('name')->get(['id','name']);
        $onHandMap = \App\Models\FeedInventory::select('feed_type_id','quantity','unit')->get()->keyBy('feed_type_id');

        // We assume 'feeds.create_allocation' is the view for the granular form
        return view('feeds.schedule_create', compact('structures','feedTypes','onHandMap'));
    }

    /**
     * RENAMED (Was storeSchedule): Stores the feed schedule for an individual pig. Deducts stock immediately.
     */
    public function storeSchedule(Request $request)
    {
        // 1. Validation for Granular Pig Feed Allocation
        $data = $request->validate([
            'structure_id' => 'required|exists:structures,id',
            'room_id' => 'required|exists:rooms,id',
            'pig_id' => 'required|exists:pigs_management,id', 
            'feed_type_id' => 'required|exists:feed_types,id',
            'feed_amount' => 'required|numeric|min:0.01|max:100000',
            'unit' => 'required|string|max:10',
            'fed_at' => 'required|date|before_or_equal:today', 
            'notes' => 'nullable|string|max:1000',
        ]);

        // 2. Inventory Deduction Logic
        $inv = \App\Models\FeedInventory::firstOrCreate(
            ['feed_type_id' => $data['feed_type_id']],
            ['quantity' => 0, 'unit' => $data['unit'] ?? 'kg', 'last_updated' => now()]
        );

        $feedAmountKg = (float)$data['feed_amount'];
        $inventoryUnit = strtolower($inv->unit);
        $deductionAmount = $feedAmountKg;

        // --- UNIT CONVERSION LOGIC ---
        $kgPerBag = 25.0; // Assume 25 kg per bag for conversion

        if ($inventoryUnit === 'bag' || $inventoryUnit === 'bags') {
            if ($kgPerBag <= 0) {
                 return back()->with('error', 'Inventory error: Bag weight conversion is zero.');
            }
            $deductionAmount = $feedAmountKg / $kgPerBag;
        }
        // --- END UNIT CONVERSION LOGIC ---

        // 3. Stock Check
        if ((float)$inv->quantity < $deductionAmount) {
            return back()->with('error', 'Cannot schedule: Amount (' . number_format($deductionAmount, 2) . ' ' . $inventoryUnit . '(s)) exceeds on-hand stock (' . number_format((float)$inv->quantity, 2) . ' ' . $inventoryUnit . '(s)).');
        }

        // 4. Deduct from Inventory
        $inv->quantity = max(0, (float)$inv->quantity - $deductionAmount);
        $inv->last_updated = now();
        $inv->save();
        
        // 5. Create a planned FeedSchedule entry (do not auto-mark as fed)
        \App\Models\FeedSchedule::create([
            'structure_id' => $data['structure_id'],
            'feed_type_id' => $data['feed_type_id'],
            'planned_amount' => $data['feed_amount'],
            'unit' => $data['unit'],
            'scheduled_for' => $data['fed_at'],
            'status' => 'planned',
            'notes' => $data['notes'] ?? null,
        ]);

        return redirect()->route('feeds.schedule')->with('success', 'Feed scheduled successfully.');
    }

    
    public function markFed(Request $request, FeedSchedule $schedule) // Changed type hint to use imported class
    {
        if ($schedule->status !== 'planned') {
            return back()->with('success', 'Entry already processed.');
        }
        
        // Assign ID to the fed_by_id column as requested
        $schedule->status = 'fed';
        $schedule->fed_at = now();
        $schedule->fed_by = auth()->id(); 
        // NOTE: feeder_id is intentionally left as is (likely NULL/0) for 'fed' actions
        $schedule->save();
        
        return back()->with('success', 'Marked as fed and inventory updated.');
    }

    /**
     * Mark a feed schedule entry as skipped and save reason. Still uses feeder_id.
     */
    public function markSkipped(Request $request, FeedSchedule $schedule)
    {
        // 1. Validate the reason provided by the modal
        $request->validate([
            // Reason must be required, a string, and up to 500 chars.
            'skip_reason' => 'required|string|max:500', 
        ]);

        if ($schedule->status !== 'planned') {
            return back()->with('success', 'Entry already processed.');
        }
        
        // use feeder_id for skipped actions.
        $schedule->status = 'skipped';
        $schedule->skip_reason = $request->skip_reason; // Save the reason from the modal
        $schedule->feeder_id = auth()->id(); // *** Continues to use feeder_id for skips ***
        // NOTE: fed_by_id is intentionally left as is (likely NULL/0) for 'skipped' actions
        $schedule->save();
        
        return back()->with('success', 'Marked as skipped. Reason recorded.');
    }

    /**
     * RENAMED (Was storeSchedule): Handles bulk feed scheduling (for groups/structures).
     * This method deducts stock at the time of *scheduling*.
     */
    public function storeGroupSchedule(Request $request)
    {
        $data = $request->validate([
            'group_id' => 'nullable|exists:groups,id',
            'structure_id' => 'required_without:group_id|nullable|exists:structures,id',
            'feed_type_id' => 'required|exists:feed_types,id',
            'planned_amount' => 'required|numeric|min:0.1|max:100000',
            'unit' => 'required|string|max:10',
            'scheduled_for' => 'required|date|after_or_equal:today',
            'notes' => 'nullable|string|max:1000',
        ]);
        
        $inv = \App\Models\FeedInventory::firstOrCreate(
            ['feed_type_id' => $data['feed_type_id']],
            ['quantity' => 0, 'unit' => $data['unit'] ?? 'kg', 'last_updated' => now()]
        );

        $plannedKg = (float)$data['planned_amount'];
        $inventoryUnit = strtolower($inv->unit);
        $deductionAmount = $plannedKg; // Default to kg if inventory is also in kg

        // --- NEW UNIT CONVERSION LOGIC ---
        // Assumption: If the inventory unit is 'bag', we need a conversion factor.
        $kgPerBag = 25.0; 

        if ($inventoryUnit === 'bag' || $inventoryUnit === 'bags') {
            if ($kgPerBag <= 0) {
                 return back()->with('error', 'Inventory error: Bag weight conversion is zero.');
            }
            $deductionAmount = $plannedKg / $kgPerBag; 
        }
        // --- END NEW UNIT CONVERSION LOGIC ---

        // Stock checks use the $deductionAmount (which is either in kg or bags now)
        if ((float)$inv->quantity <= 0) {
            return back()->with('error', 'Cannot schedule: On-hand stock for this feed type is 0 ' . $inventoryUnit . '(s).');
        }
        
        if ((float)$inv->quantity < $deductionAmount) {
            return back()->with('error', 'Cannot schedule: Planned amount (' . number_format($deductionAmount, 2) . ' ' . $inventoryUnit . '(s)) exceeds on-hand stock (' . number_format((float)$inv->quantity, 2) . ' ' . $inventoryUnit . '(s)).');
        }

        // Deduct now at scheduling time
        $inv->quantity = max(0, (float)$inv->quantity - $deductionAmount);
        $inv->last_updated = now();
        $inv->save();

        \App\Models\FeedSchedule::create(array_merge($data, ['status' => 'planned']));
        return redirect()->route('feeds.schedule')->with('success', 'Group feeding scheduled and stock deducted.');
    }

    /**
     * Display the specified feed schedule entry details.
     *
     * @param \App\Models\FeedSchedule $schedule
     * @return \Illuminate\View\View
     */
    public function showSchedule(FeedSchedule $schedule)
    {
        // CRITICAL UPDATE: Load both feeder (for skips) and fedBy (for fed actions)
        $schedule->load([
            'feedType', 
            'structure', 
            'group', 
            'feeder', // used for skipped actions
            'fedBy',  // used for fed actions
        ]);

        return view('feeds.schedule_show', [
            'entry' => $schedule,
        ]);
    }

}
