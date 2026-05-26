<?php

namespace App\Http\Controllers;

use App\Models\PigletDeath;
use App\Models\PigManagement;
use App\Services\Archive\RestoreArchivedRecordService;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

/**
 * Archived pigs (soft-deleted) and archived piglet death records.
 *
 * Phase E1: viewAny gated by PigManagementPolicy. Restore is admin-only
 * via PigManagementPolicy::restore.
 */
class PigArchiveController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request)
    {
        // Viewing archived records uses the same broad permission as viewing
        // pigs in general — admin/manager/dataentry/accountant. Restore
        // remains admin-only (per-instance check below).
        $this->authorize('viewAny', PigManagement::class);

        $weaningAge = config('breeding.cycle.birth_to_weaning_days', 45);

        $archived = PigManagement::onlyTrashed()
            ->with(['deaths', 'sales'])
            ->orderByDesc('deleted_at');

        $search = (string) $request->string('search');
        if ($search !== '') {
            $archived->where(function ($q) use ($search) {
                $q->where('ear_tag_number', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $category = (string) $request->string('category');
        if ($category === 'piglet') {
            $archived->whereNotNull('date_of_birth')
                ->whereDate('date_of_birth', '>=', Carbon::now()->subDays($weaningAge));
        } elseif ($category === 'adult') {
            $archived->where(function ($q) use ($weaningAge) {
                $q->whereNull('date_of_birth')
                  ->orWhereDate('date_of_birth', '<', Carbon::now()->subDays($weaningAge));
            });
        }

        $pigs = $archived->paginate(15)->withQueryString();

        $pigletDeaths = PigletDeath::onlyTrashed()
            ->with(['farrowing.sow', 'recordedBy'])
            ->orderByDesc('deleted_at')
            ->paginate(10);

        return view('pigs.archived', compact('pigs', 'category', 'weaningAge', 'pigletDeaths'));
    }

    public function restorePig(Request $request, RestoreArchivedRecordService $service, $id)
    {
        $pig = PigManagement::withTrashed()->findOrFail($id);

        // Per-instance: admin only (with same-org guard).
        $this->authorize('restore', $pig);

        $service->restorePig($pig, $request->user());

        return redirect()->route('pigs.archived')
            ->with('success', "Pig {$pig->code} has been restored to active records.");
    }

    public function restorePigletDeath(Request $request, RestoreArchivedRecordService $service, $id)
    {
        $pigletDeath = PigletDeath::onlyTrashed()->findOrFail($id);

        // Restoring a piglet-death record is treated as a delete-class action
        // on PigDeath (it's reversing an archive). Use PigDeath delete ability
        // — admin-only. Note: PigletDeath is a different model; we're using
        // PigDeath policy as the closest semantic match because PigletDeath
        // doesn't have its own policy.
        if (! $request->user()->isAdmin()) {
            abort(403, 'Only administrators can restore piglet death records.');
        }

        $service->restorePigletDeath($pigletDeath, $request->user());

        return redirect()->route('pigs.archived')->with('success', 'Piglet death record restored.');
    }
}
