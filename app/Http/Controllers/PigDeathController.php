<?php

namespace App\Http\Controllers;

use App\Http\Requests\Deaths\RecordPigDeathRequest;
use App\Models\PigDeath;
use App\Models\PigletDeath;
use App\Models\PigManagement;
use App\Services\Deaths\DeathsExportService;
use App\Services\Deaths\RecordPigDeathService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

/**
 * Pig deaths — form + recording + listing + exports.
 *
 * Phase E1: every action enforces PigDeathPolicy. Tenant isolation via trait.
 */
class PigDeathController extends Controller
{
    use AuthorizesRequests;

    public function form($id)
    {
        $pig = PigManagement::findOrFail($id);

        $this->authorize('create', PigDeath::class);

        if ($pig->deaths()->exists()) {
            return back()->withErrors(['death' => 'This pig is already recorded as deceased.']);
        }

        return view('pigs.death', compact('pig'));
    }

    public function record(RecordPigDeathRequest $request, RecordPigDeathService $service, $id)
    {
        $pig = PigManagement::findOrFail($id);

        $this->authorize('create', PigDeath::class);

        if ($pig->deaths()->exists()) {
            return back()->withErrors(['death' => 'This pig is already recorded as deceased.']);
        }

        $service->execute($pig, $request->user(), $request->normalisedForService());

        return redirect()->route('deaths.index')
            ->with('success', "Death of pig {$pig->code} recorded successfully.");
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', PigDeath::class);

        $adultQuery = PigDeath::with(['pig'])
            ->whereHas('pig', fn ($q) => $q->whereRaw('DATEDIFF(death_date, date_of_birth) >= 60'));

        $pigletQuery = PigDeath::with(['pig'])
            ->whereHas('pig', fn ($q) => $q->whereRaw('DATEDIFF(death_date, date_of_birth) < 60'));

        foreach ([$adultQuery, $pigletQuery] as $q) {
            if ($request->filled('death_cause')) {
                $q->where('death_cause', 'like', '%' . $request->death_cause . '%');
            }
            if ($request->filled('date_from')) {
                $q->where('death_date', '>=', $request->date_from);
            }
            if ($request->filled('date_to')) {
                $q->where('death_date', '<=', $request->date_to);
            }
        }

        $deaths       = $adultQuery->orderBy('death_date', 'desc')->paginate(10);
        $pigletDeaths = $pigletQuery->orderBy('death_date', 'desc')->paginate(10);

        $deaths->appends($request->query());
        $pigletDeaths->appends($request->query());

        $pigletDeathEventsQuery = PigletDeath::with(['farrowing.sow']);
        if ($request->filled('death_cause')) {
            $pigletDeathEventsQuery->where('cause', 'like', '%' . $request->death_cause . '%');
        }
        if ($request->filled('date_from')) {
            $pigletDeathEventsQuery->where('death_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $pigletDeathEventsQuery->where('death_date', '<=', $request->date_to);
        }
        $pigletDeathEvents = $pigletDeathEventsQuery->orderBy('death_date', 'desc')->paginate(10);
        $pigletDeathEvents->appends($request->query());

        $stats = [
            'total_deaths' => PigDeath::count() + (int) PigletDeath::sum('count'),
            'recent_deaths' => PigDeath::recent(30)->count(),
            'total_piglet_deaths' => PigDeath::whereHas('pig', fn ($q) => $q->whereRaw('DATEDIFF(death_date, date_of_birth) < 60'))->count() + (int) PigletDeath::sum('count'),
            'recent_piglet_deaths' => PigDeath::recent(30)->whereHas('pig', fn ($q) => $q->whereRaw('DATEDIFF(death_date, date_of_birth) < 60'))->count(),
            'total_adult_deaths' => PigDeath::whereHas('pig', fn ($q) => $q->whereRaw('DATEDIFF(death_date, date_of_birth) >= 60'))->count(),
            'recent_adult_deaths' => PigDeath::recent(30)->whereHas('pig', fn ($q) => $q->whereRaw('DATEDIFF(death_date, date_of_birth) >= 60'))->count(),
        ];

        if ($request->ajax()) {
            return response()->json([
                'html' => view('deaths._tables', compact('deaths', 'pigletDeaths', 'pigletDeathEvents'))->render(),
                'url' => $request->fullUrl(),
            ]);
        }

        return view('deaths.index', compact('deaths', 'pigletDeaths', 'pigletDeathEvents', 'stats'));
    }

    public function show($id)
    {
        $death = PigDeath::with(['pig', 'recordedBy'])->findOrFail($id);
        $this->authorize('view', $death);
        return view('deaths.show', compact('death'));
    }

    public function exportCsv(Request $request, DeathsExportService $service)
    {
        $this->authorize('export', PigDeath::class);
        return $service->csv($request);
    }

    public function exportPdf(Request $request, DeathsExportService $service)
    {
        $this->authorize('export', PigDeath::class);
        return $service->pdf($request);
    }
}
