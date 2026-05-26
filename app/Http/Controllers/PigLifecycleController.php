<?php

namespace App\Http\Controllers;

use App\Helpers\FlashHelper;
use App\Models\PigManagement;
use App\Services\Lifecycle\DemotePigService;
use App\Services\Lifecycle\PromotePigService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

/**
 * Pig stock-type transitions: promote to breeding / demote to standard.
 *
 * Phase E1: each action enforces PigManagementPolicy::promote/demote
 * (admin/manager only).
 */
class PigLifecycleController extends Controller
{
    use AuthorizesRequests;

    public function promote(Request $request, PromotePigService $service, $id)
    {
        $pig = PigManagement::findOrFail($id);

        $this->authorize('promote', $pig);

        $result = $service->execute($pig, $request->user());

        if (! $result->eligible) {
            return back()->withErrors(['promotion' => $result->message]);
        }

        if (class_exists(FlashHelper::class)) {
            return FlashHelper::successAndRedirect($result->message, 'breeding-stocks.index');
        }
        return redirect()->route('breeding-stocks.index')->with('success', $result->message);
    }

    public function demote(Request $request, DemotePigService $service, $id)
    {
        $pig = PigManagement::findOrFail($id);

        $this->authorize('demote', $pig);

        $result = $service->execute($pig, $request->user());

        if (! $result->eligible) {
            return back()->withErrors(['demotion' => $result->message]);
        }

        return redirect()->route('standard-stocks.index')->with('success', $result->message);
    }
}
