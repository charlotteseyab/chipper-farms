<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PigManagement;
use App\Models\GroupFeeding;
use App\Models\PigWeight;
use Carbon\Carbon;

class FeedUiController extends Controller
{
    public function schedule()
    {
        $tab = request('tab', 'current'); // detect active tab
        $today = now()->toDateString();
        $statuses = request('status') ? explode(',', request('status')) : ['planned','fed','skipped'];

        if ($tab === 'current') {
            // Today tab: use $from and $to from request or default to today
            $from = request('from', $today);
            $to = request('to', $today);

            $entries = \App\Models\FeedSchedule::with(['group.structure','structure','feedType','feeder'])
                ->whereBetween('scheduled_for', [$from, $to])
                ->whereIn('status', $statuses)
                ->orderBy('scheduled_for')
                ->orderBy('group_id')
                ->paginate(50);
        } else {
            // Previous schedules tab: automatically load all previous schedules by default
            $query = \App\Models\FeedSchedule::with(['group.structure','structure','feedType','feeder'])
                ->whereDate('scheduled_for', '<', $today) // all past schedules
                ->whereIn('status', $statuses)
                ->orderBy('scheduled_for')
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

        // ✅ Correct ingredient analysis
        $ingredientUsage = [];
        $ingredientDetails = [];
        foreach ($schedules as $sch) {
            $ft = $sch->feedType;
            if (!$ft || !$ft->recipe) continue;
            foreach ($ft->recipe->items as $ri) {
                $ingName = optional($ri->ingredient)->name ?? 'Item '.$ri->ingredient_id;
                $perKg = (float)($ri->percentage ?? 0) / 100;
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

        return view('feeds.reports', [
            'from' => $from,
            'to' => $to,
            'totalScheduledKg' => $totalScheduledKg,
            'byType' => $byType,
            'ingredientUsage' => $ingredientUsage,
            'ingredientDetails' => $ingredientDetails,
            'avgWeightByDate' => $avgWeightByDate,
            'feedByDate' => $feedByDate,
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

        // ✅ FIXED: Ingredient usage calculation (same as reports)
        $ingredientUsage = [];
        $ingredientDetails = [];
        foreach ($schedules as $sch) {
            $ft = $sch->feedType;
            if (!$ft || !$ft->recipe) continue;
            foreach ($ft->recipe->items as $ri) {
                $ingName = optional($ri->ingredient)->name ?? 'Item '.$ri->ingredient_id;
                $perKg = (float)($ri->percentage ?? 0) / 100;
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
        ])->setPaper('a4', 'portrait');

        return $pdf->download("feed_reports_{$from}_to_{$to}.pdf");
    }

    public function createSchedule()
    {
        $groups = \App\Models\Group::with('structure')->orderBy('name')->get(['id','name','structure_id']);
        $structures = \App\Models\Structure::orderBy('name')->get(['id','name']);
        $feedTypes = \App\Models\FeedType::orderBy('name')->get(['id','name']);
        $onHandMap = \App\Models\FeedInventory::select('feed_type_id','quantity','unit')->get()->keyBy('feed_type_id');
        return view('feeds.schedule_create', compact('groups','structures','feedTypes','onHandMap'));
    }

    public function markFed(Request $request, \App\Models\FeedSchedule $schedule)
    {
        if ($schedule->status !== 'planned') {
            return back()->with('success', 'Entry already processed.');
        }
        $schedule->update([
            'status' => 'fed',
            'fed_at' => now(),
            'fed_by' => auth()->id(),
        ]);
        return back()->with('success', 'Marked as fed and inventory updated.');
    }

    public function markSkipped(Request $request, \App\Models\FeedSchedule $schedule)
    {
        if ($schedule->status !== 'planned') {
            return back()->with('success', 'Entry already processed.');
        }
        $schedule->update(['status' => 'skipped']);
        return back()->with('success', 'Marked as skipped.');
    }

    public function storeSchedule(Request $request)
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

        if ((float)$inv->quantity <= 0) {
            return back()->with('error', 'Cannot schedule: On-hand stock for this feed type is 0.');
        }
        if ((float)$inv->quantity < (float)$data['planned_amount']) {
            return back()->with('error', 'Cannot schedule: Planned amount exceeds on-hand stock (' . number_format((float)$inv->quantity, 2) . ' ' . $inv->unit . ').');
        }

        // Deduct now at scheduling time
        $inv->quantity = max(0, (float)$inv->quantity - (float)$data['planned_amount']);
        $inv->last_updated = now();
        $inv->save();

        \App\Models\FeedSchedule::create(array_merge($data, ['status' => 'planned']));
        return redirect()->route('feeds.schedule')->with('success', 'Feeding scheduled and stock deducted.');
    }
}
