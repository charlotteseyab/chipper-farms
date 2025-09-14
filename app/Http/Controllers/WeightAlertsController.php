<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\WeightAlert;
use App\Models\PigManagement;
use App\Helpers\FlashHelper;

class WeightAlertsController extends Controller
{
    /**
     * Display a listing of weight alerts
     */
    public function index(Request $request)
    {
        // Subquery: get latest alert id for each pig_id
        $latestSub = \DB::table('weight_alerts as wa2')
            ->selectRaw('MAX(id) as max_id, pig_id')
            ->groupBy('pig_id');

        // Main query: join on id to get only latest per pig
        $query = WeightAlert::with(['pig', 'weightRecord'])
            ->joinSub($latestSub, 'latest', function($join) {
                $join->on('weight_alerts.id', '=', 'latest.max_id');
            });

        // Apply filters (only those that make sense for latest alerts)
        if ($request->alert_type) {
            $query->where('weight_alerts.alert_type', $request->alert_type);
        }
        if ($request->severity) {
            $query->where('weight_alerts.severity', $request->severity);
        }
        if ($request->pig_id) {
            $query->where('weight_alerts.pig_id', $request->pig_id);
        }
        if ($request->has('acknowledged')) {
            $acknowledged = $request->boolean('acknowledged');
            $query->where('weight_alerts.acknowledged', $acknowledged);
        }
        if ($request->date_from) {
            $query->whereDate('weight_alerts.created_at', '>=', $request->date_from);
        }
        if ($request->date_to) {
            $query->whereDate('weight_alerts.created_at', '<=', $request->date_to);
        }

        // Prioritize under_weight alerts at the top, then order by newest
        $alerts = $query
            ->orderByRaw("CASE WHEN weight_alerts.alert_type = 'under_weight' THEN 0 ELSE 1 END")
            ->orderBy('weight_alerts.created_at', 'desc')
            ->select('weight_alerts.*') // select only columns from weight_alerts
            ->paginate(15);
        $alerts->appends($request->query());

        // Get data for filters
        $pigs = PigManagement::orderBy('ear_tag_number')->get();
        $alertTypes = WeightAlert::distinct()->pluck('alert_type')->filter();
        $severities = WeightAlert::distinct()->pluck('severity')->filter();

        // Get summary statistics
        $stats = [
            'total_alerts' => WeightAlert::count(),
            'unacknowledged' => WeightAlert::where('acknowledged', false)->count(),
            'high_severity' => WeightAlert::where('severity', 'high')->where('acknowledged', false)->count(),
            'under_weight' => WeightAlert::where('alert_type', 'under_weight')->where('acknowledged', false)->count(),
            'over_weight' => WeightAlert::where('alert_type', 'over_weight')->where('acknowledged', false)->count(),
            'no_standard' => WeightAlert::where('alert_type', 'no_standard')->where('acknowledged', false)->count(),
        ];

        return view('weight-alerts.index', compact('alerts', 'pigs', 'alertTypes', 'severities', 'stats'));
    }

    /**
     * Show the form for creating a new weight alert (manual creation)
     */
    public function create()
    {
        $pigs = PigManagement::orderBy('ear_tag_number')->get();
        return view('weight-alerts.create', compact('pigs'));
    }

    /**
     * Display the specified weight alert
     */
    public function show(WeightAlert $weightAlert)
    {
        $weightAlert->load(['pig', 'weightRecord', 'acknowledgedBy']);
        return view('weight-alerts.show', compact('weightAlert'));
    }

    /**
     * Acknowledge a weight alert
     */
    public function acknowledge(Request $request, WeightAlert $weightAlert)
    {
        $data = $request->validate([
            'acknowledgment_notes' => 'nullable|string|max:1000'
        ]);

        $weightAlert->update([
            'acknowledged' => true,
            'acknowledged_by' => auth()->id(),
            'acknowledged_at' => now(),
            'acknowledgment_notes' => $data['acknowledgment_notes'] ?? null
        ]);

        return FlashHelper::successAndRedirect(
            'Weight alert acknowledged successfully!',
            'weight-alerts.index'
        );
    }

    /**
     * Bulk acknowledge multiple alerts
     */
    public function bulkAcknowledge(Request $request)
    {
        $data = $request->validate([
            'alert_ids' => 'required|array',
            'alert_ids.*' => 'exists:weight_alerts,id',
            'acknowledgment_notes' => 'nullable|string|max:1000'
        ]);

        $updatedCount = WeightAlert::whereIn('id', $data['alert_ids'])
            ->where('acknowledged', false)
            ->update([
                'acknowledged' => true,
                'acknowledged_by' => auth()->id(),
                'acknowledged_at' => now(),
                'acknowledgment_notes' => $data['acknowledgment_notes'] ?? null
            ]);

        return FlashHelper::successAndRedirect(
            "Successfully acknowledged {$updatedCount} weight alert(s)!",
            'weight-alerts.index'
        );
    }

    /**
     * Remove the specified weight alert
     */
    public function destroy(WeightAlert $weightAlert)
    {
        $pigEarTag = $weightAlert->pig->ear_tag_number ?? 'Unknown';
        $weightAlert->delete();

        return FlashHelper::successAndRedirect(
            "Weight alert for pig {$pigEarTag} deleted successfully!",
            'weight-alerts.index'
        );
    }

    /**
     * Get dashboard data for weight alerts
     */
    public function dashboard()
    {
        $recentAlerts = WeightAlert::with(['pig', 'weightRecord'])
            ->where('acknowledged', false)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $stats = [
            'total_unacknowledged' => WeightAlert::where('acknowledged', false)->count(),
            'high_severity' => WeightAlert::where('severity', 'high')->where('acknowledged', false)->count(),
            'medium_severity' => WeightAlert::where('severity', 'medium')->where('acknowledged', false)->count(),
            'low_severity' => WeightAlert::where('severity', 'low')->where('acknowledged', false)->count(),
            'under_weight' => WeightAlert::where('alert_type', 'under_weight')->where('acknowledged', false)->count(),
            'over_weight' => WeightAlert::where('alert_type', 'over_weight')->where('acknowledged', false)->count(),
            'no_standard' => WeightAlert::where('alert_type', 'no_standard')->where('acknowledged', false)->count(),
        ];

        return view('weight-alerts.dashboard', compact('recentAlerts', 'stats'));
    }
}
