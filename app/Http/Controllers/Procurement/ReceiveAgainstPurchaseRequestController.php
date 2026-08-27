<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Http\Requests\Procurement\StoreReceiveAgainstPurchaseRequestRequest;
use App\Models\PurchaseRequest;
use App\Services\Procurement\ReceiveAgainstPurchaseRequestService;
use App\Support\CurrentOrganization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Phase 4 — guided "Receive against PR" flow.
 *
 * Page paths:
 *   /procurement/purchase-requests/{pr}/receive          (form)
 *   POST /procurement/purchase-requests/{pr}/receive     (submit)
 *
 * Permission: receive_against_pr.
 */
class ReceiveAgainstPurchaseRequestController extends Controller
{
    public function __construct(
        protected CurrentOrganization $currentOrganization,
        protected ReceiveAgainstPurchaseRequestService $service,
    ) {
    }

    public function create(Request $request, PurchaseRequest $purchaseRequest)
    {
        if (! in_array($purchaseRequest->status, ['approved', 'paid'], true)) {
            return redirect()
                ->route('purchase-requests.show', $purchaseRequest)
                ->with('error', sprintf(
                    "Cannot receive against PR %s: status is '%s' (must be approved or paid).",
                    $purchaseRequest->request_no,
                    $purchaseRequest->status,
                ));
        }

        $purchaseRequest->load(['items.rawMaterial', 'supplier']);

        // Only show lines that:
        //   - have a raw_material_id (catalogued items)
        //   - are not closed
        $receivableItems = $purchaseRequest->items
            ->filter(fn ($i) => $i->raw_material_id && ! $i->is_receipt_closed)
            ->values();

        return view('procurement.receipts.create', [
            'purchaseRequest' => $purchaseRequest,
            'items'           => $receivableItems,
        ]);
    }

    public function store(
        StoreReceiveAgainstPurchaseRequestRequest $request,
        PurchaseRequest $purchaseRequest,
    ) {
        $validated = $request->validated();

        $receipts = collect($validated['receipts'])
            ->filter(fn ($receipt) => (bool) ($receipt['selected'] ?? false))
            ->values()
            ->all();

        $allowOverReceipt = (bool) ($validated['allow_over_receipt'] ?? false);
        if ($allowOverReceipt && ! $request->user()->hasPermission('override_match_variance')) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'You do not have permission to override over-receipt.');
        }

        try {
            $results = $this->service->executeMany(
                purchaseRequest: $purchaseRequest,
                actor:  $request->user(),
                receipts: $receipts,
                reference: $validated['reference'] ?? null,
                deliveryNotes: $validated['notes'] ?? null,
                allowOverReceipt: $allowOverReceipt,
            );

            return redirect()
                ->route('finance.procurement.receive-against-pr.create', $purchaseRequest)
                ->with('success', sprintf(
                    'Delivery recorded successfully for %d material line%s.',
                    count($results),
                    count($results) === 1 ? '' : 's',
                ));
        } catch (\Throwable $e) {
            Log::error('ReceiveAgainstPurchaseRequest store failed', [
                'pr'    => $purchaseRequest->id,
                'items' => collect($receipts)->pluck('purchase_request_item_id')->all(),
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }
}
