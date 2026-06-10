<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreInventoryAccountMappingRequest;
use App\Models\Finance\Account;
use App\Models\Finance\InventoryAccountMapping;
use App\Support\CurrentOrganization;
use Illuminate\Http\Request;

/**
 * Phase 4 — CRUD for inventory account mappings.
 *
 * Operators set up the GL routing policy here. Each row maps an
 * (organization, item_kind, category) to specific GL accounts for
 * inventory / GR/IR / adjustments / COGS / PPV / suspense.
 *
 * Permission gating:
 *   - view_inventory_value      (index, show)
 *   - manage_inventory_mappings (create, store, edit, update, destroy)
 */
class InventoryAccountMappingController extends Controller
{
    public function __construct(protected CurrentOrganization $currentOrganization)
    {
    }

    public function index(Request $request)
    {
        $orgId = $this->currentOrganization->id();

        $mappings = InventoryAccountMapping::query()
            ->with([
                'inventoryAccount',
                'grIrAccount',
                'adjustmentAccount',
                'cogsAccount',
                'ppvAccount',
                'suspenseAccount',
            ])
            ->when(
                $orgId !== null,
                fn ($q) => $q->where('organization_id', $orgId),
                fn ($q) => $q->whereNull('organization_id'),
            )
            ->orderBy('item_kind')
            ->orderByDesc('is_default')
            ->orderBy('category')
            ->get();

        return view('inventory.account-mappings.index', [
            'mappings' => $mappings,
        ]);
    }

    public function create()
    {
        return view('inventory.account-mappings.create', [
            'accounts'  => $this->accountsForSelect(),
            'kinds'     => InventoryAccountMapping::ALLOWED_KINDS,
            'mapping'   => new InventoryAccountMapping(),
        ]);
    }

    public function store(StoreInventoryAccountMappingRequest $request)
    {
        $orgId = $this->currentOrganization->id();
        $validated = $request->validated();

        $mapping = InventoryAccountMapping::create(array_merge(
            $validated,
            [
                'organization_id'    => $orgId,
                'created_by_user_id' => $request->user()->id,
            ],
        ));

        return redirect()
            ->route('finance.inventory.account-mappings.index')
            ->with('success', sprintf(
                'Mapping created for %s / %s.',
                $mapping->item_kind,
                $mapping->isDefaultRow() ? 'default' : $mapping->category,
            ));
    }

    public function edit(InventoryAccountMapping $mapping)
    {
        $this->ensureInOrg($mapping);

        return view('inventory.account-mappings.edit', [
            'mapping'  => $mapping,
            'accounts' => $this->accountsForSelect(),
            'kinds'    => InventoryAccountMapping::ALLOWED_KINDS,
        ]);
    }

    public function update(StoreInventoryAccountMappingRequest $request, InventoryAccountMapping $mapping)
    {
        $this->ensureInOrg($mapping);

        $validated = $request->validated();
        $validated['updated_by_user_id'] = $request->user()->id;

        $mapping->update($validated);

        return redirect()
            ->route('finance.inventory.account-mappings.index')
            ->with('success', 'Mapping updated.');
    }

    public function destroy(InventoryAccountMapping $mapping)
    {
        $this->ensureInOrg($mapping);

        $label = $mapping->item_kind . ' / ' . ($mapping->isDefaultRow() ? 'default' : $mapping->category);
        $mapping->delete();

        return redirect()
            ->route('finance.inventory.account-mappings.index')
            ->with('warning', "Deleted mapping: {$label}.");
    }

    // ============================================================
    // Internals
    // ============================================================

    protected function ensureInOrg(InventoryAccountMapping $mapping): void
    {
        $orgId = $this->currentOrganization->id();
        // Null-org mappings are visible to single-tenant setups.
        if ($mapping->organization_id !== null && $mapping->organization_id !== $orgId) {
            abort(404);
        }
    }

    protected function accountsForSelect(): \Illuminate\Support\Collection
    {
        $orgId = $this->currentOrganization->id();
        return Account::query()
            ->when(
                $orgId !== null,
                fn ($q) => $q->where(fn ($q) => $q->whereNull('organization_id')->orWhere('organization_id', $orgId)),
                fn ($q) => $q->whereNull('organization_id'),
            )
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'account_type']);
    }
}
