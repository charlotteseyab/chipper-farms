<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Http\Requests\Procurement\StoreReceiveAgainstPurchaseRequestRequest;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
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
        $this->middleware('auth');
        $this->middleware('permission:receive_against_pr');
    }

    public function create(Request $request, PurchaseRequest $purchaseRequest)
    {
        if ($purchaseRequest->status !== 'approved') {
            return redirect()
                ->route('purchase-requests.show', $purchaseRequest)
                ->with('error', sprintf(
                    "Cannot receive against PR %s: status is '%s' (must be 'approved').",
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

        /** @var PurchaseRequestItem $prItem */
        $prItem = PurchaseRequestItem::with('rawMaterial', 'purchaseRequest')
            ->where('purchase_request_id', $purchaseRequest->id)
            ->findOrFail($validated['purchase_request_item_id']);

        $allowOverReceipt = (bool) ($validated['allow_over_receipt'] ?? false);
        if ($allowOverReceipt && ! $request->user()->hasPermission('override_match_variance')) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'You do not have permission to override over-receipt.');
        }

        try {
            $this->service->execute(
                prItem: $prItem,
                actor:  $request->user(),
                data: [
                    'quantity'  => (float) $validated['quantity'],
                    'unit_cost' => (float) $validated['unit_cost'],
                    'reference' => $validated['reference'] ?? null,
                    'notes'     => $validated['notes'] ?? null,
                ],
                allowOverReceipt: $allowOverReceipt,
            );

            return redirect()
                ->route('procurement.receive-against-pr.create', $purchaseRequest)
                ->with('success', sprintf(
                    'Received %s for PR item #%d. %s',
                    number_format((float) $validated['quantity'], 2),
                    $prItem->id,
                    $prItem->fresh()->is_receipt_closed ? '(line now closed)' : '',
                ));
        } catch (\Throwable $e) {
            Log::error('ReceiveAgainstPurchaseRequest store failed', [
                'pr'    => $purchaseRequest->id,
                'item'  => $prItem->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }
}
