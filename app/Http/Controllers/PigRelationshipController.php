<?php

namespace App\Http\Controllers;

use App\Models\PigManagement;
use App\Services\Stock\PigRelationshipAnalysisService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

/**
 * Relationship analysis endpoint.
 *
 * Phase E1: gated by PigManagementPolicy::viewAny — broad, since this is
 * an inspection endpoint (no writes). Used during create/edit forms.
 */
class PigRelationshipController extends Controller
{
    use AuthorizesRequests;

    public function __construct(protected PigRelationshipAnalysisService $analysis)
    {
    }

    public function check(Request $request)
    {
        // Reading relationships is a viewAny-class action — the same roles
        // that can view pig records (admin/manager/dataentry/accountant).
        $this->authorize('viewAny', PigManagement::class);

        $fatherId = $request->input('father_id');
        $motherId = $request->input('mother_id');

        return response()->json($this->analysis->analyze($fatherId, $motherId));
    }
}
