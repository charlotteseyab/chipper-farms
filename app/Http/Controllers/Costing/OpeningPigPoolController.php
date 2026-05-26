<?php

namespace App\Http\Controllers\Costing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Costing\StoreOpeningPigPoolRequest;
use App\Models\Finance\GroupOpeningPoolImport;
use App\Models\Group;
use App\Services\Finance\Costing\DuplicateOpeningPoolImportException;
use App\Services\Finance\Costing\OpeningPigPoolService;
use App\Support\CurrentOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Phase 5 — opening pig pool cutover.
 *
 * Mirrors the Phase 4 OpeningBalanceController for raw material / feed.
 *
 * Permission: manage_opening_pig_pool (write), view_group_pools (read).
 */
class OpeningPigPoolController extends Controller
{
    public function __construct(
        protected CurrentOrganization $currentOrganization,
        protected OpeningPigPoolService $service,
    ) {
        $this->middleware('auth');
        $this->middleware('permission:view_group_pools')->only(['index', 'show']);
        $this->middleware('permission:manage_opening_pig_pool')->only(['create', 'store']);
    }

    public function index(Request $request)
    {
        $orgId = $this->currentOrganization->id();

        $imports = GroupOpeningPoolImport::query()
            ->with('performedBy')
            ->when(
                $orgId !== null,
                fn ($q) => $q->where('organization_id', $orgId),
                fn ($q) => $q->whereNull('organization_id'),
            )
            ->orderByDesc('id')
            ->paginate(25);

        return view('costing.opening-pig-inventory.index', [
            'imports' => $imports,
        ]);
    }

    public function show(GroupOpeningPoolImport $import)
    {
        $this->ensureInOrg($import);
        $import->load(['performedBy', 'movements.group']);

        return view('costing.opening-pig-inventory.show', [
            'import' => $import,
        ]);
    }

    public function create(Request $request)
    {
        // Groups that don't yet have an opening row are the eligible
        // targets. Use a sub-query: groups whose pool has zero movements.
        $groups = Group::query()->orderBy('name')->get(['id', 'name', 'type']);

        return view('costing.opening-pig-inventory.create', [
            'groups' => $groups,
        ]);
    }

    public function store(StoreOpeningPigPoolRequest $request)
    {
        $validated = $request->validated();

        try {
            $cutover = CarbonImmutable::parse($validated['cutover_date']);
            $rows = collect($validated['rows'])->map(fn ($row) => [
                'group_id'        => (int) $row['group_id'],
                'pig_count'       => (int) $row['pig_count'],
                'total_weight_kg' => (float) $row['total_weight_kg'],
                'total_value'     => (float) $row['total_value'],
            ])->all();

            $import = $this->service->ingest(
                rows:        $rows,
                cutoverDate: $cutover,
                actor:       $request->user(),
                importMode:  GroupOpeningPoolImport::MODE_MANUAL,
                notes:       $validated['notes'] ?? null,
            );

            return redirect()
                ->route('costing.opening-pig-inventory.show', $import)
                ->with('success', "Opening pig pool ingested for {$import->row_count} group(s).");
        } catch (DuplicateOpeningPoolImportException $e) {
            return redirect()
                ->route('costing.opening-pig-inventory.show', $e->existingId)
                ->with('warning', 'A previous import with identical payload already exists.');
        } catch (\Throwable $e) {
            Log::error('OpeningPigPoolController::store failed', ['error' => $e->getMessage()]);
            return redirect()->back()
                ->withInput()
                ->with('error', 'Import failed: ' . $e->getMessage());
        }
    }

    // ============================================================

    protected function ensureInOrg(GroupOpeningPoolImport $import): void
    {
        $orgId = $this->currentOrganization->id();
        if ($import->organization_id !== null && $import->organization_id !== $orgId) {
            abort(404);
        }
    }
}
