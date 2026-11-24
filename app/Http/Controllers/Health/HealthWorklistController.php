<?php

namespace App\Http\Controllers\Health;

use App\Http\Controllers\Controller;
use App\Services\Health\HealthWorklistService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/** The treatment worklist: doses due and overdue, and follow-ups, for one day. */
class HealthWorklistController extends Controller
{
    public function index(Request $request, HealthWorklistService $worklist)
    {
        abort_unless($request->user()->hasPermission('view_health_records'), 403);
        $data = $request->validate(['date' => ['nullable', 'date_format:Y-m-d'], 'mine' => ['nullable', 'boolean']]);
        $day = isset($data['date']) ? CarbonImmutable::parse($data['date']) : CarbonImmutable::today();
        $mine = ($data['mine'] ?? false) ? (int) $request->user()->id : null;

        $slots = $worklist->dueOn($day, $mine);
        $overdue = $day->isToday() ? $worklist->overdueBefore($day, $mine) : collect();
        $followUps = $worklist->followUpsDue($day, $mine);

        return view('health.worklist', [
            'day' => $day, 'mine' => (bool) $mine, 'isToday' => $day->isToday(),
            'slots' => $slots, 'overdue' => $overdue, 'followUps' => $followUps,
            'glance' => app(\App\Services\Health\HealthGlanceService::class)->worklist($slots, $overdue, $followUps, $day),
        ]);
    }
}
