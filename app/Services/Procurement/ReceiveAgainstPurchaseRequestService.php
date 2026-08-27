<?php

namespace App\Services\Procurement;

use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\RawMaterial;
use App\Models\User;
use App\Services\Inventory\ReceiveRawMaterialService;
use App\Support\CurrentOrganization;
use Illuminate\Support\Facades\DB;

/**
 * Phase 4 — guided receive flow for PR-linked goods.
 *
 * Orchestrates ReceiveRawMaterialService with PR linkage pre-applied.
 * The user picks a PR → picks a line → enters actual qty + unit cost.
 * This service:
 *
 *   1. Validates the PR is approved and still open for receiving. A fully
 *      prepaid request has status 'paid' and remains receivable.
 *   2. Validates the PR item is open (not is_receipt_closed).
 *   3. Validates the PR item has raw_material_id set.
 *   4. Pre-fills supplier_id from the PR.
 *   5. Pre-fills purchase_request_item_id on the receipt.
 *   6. Calls ReceiveRawMaterialService with all that context.
 *   7. Updates the PR item's running quantity_received (the inner service
 *      handles this; we delegate).
 *
 * QTY TOLERANCE
 *
 *   Over-receipt by more than the qty tolerance is REFUSED unless the
 *   caller passes $allowOverReceipt=true (operator override). The
 *   default policy is conservative: typos in qty are a common error
 *   and silently over-receiving creates inventory ghosts.
 *
 * UNIT COST
 *
 *   The receipt unit cost can differ from the PR unit price — that's
 *   normal (suppliers' last-minute price adjustments). The variance is
 *   captured at the receipt-stock-log level and feeds into PPV when
 *   the AP invoice posts later.
 */
class ReceiveAgainstPurchaseRequestService
{
    public function __construct(
        protected CurrentOrganization $currentOrganization,
        protected ReceiveRawMaterialService $receiveService,
    ) {
    }

    /**
     * @param  array{
     *   quantity: float,
     *   unit_cost: float,
     *   reference?: ?string,
     *   notes?: ?string,
     * }  $data
     */
    public function execute(
        PurchaseRequestItem $prItem,
        User $actor,
        array $data,
        bool $allowOverReceipt = false,
    ): array {
        $prItem->loadMissing(['rawMaterial', 'purchaseRequest']);

        $pr = $prItem->purchaseRequest;
        if (! $pr) {
            throw new \DomainException("PR item #{$prItem->id} is orphaned (no PR row).");
        }

        if (! in_array($pr->status, ['approved', 'paid'], true)) {
            throw new \DomainException(sprintf(
                "Cannot receive against PR %s: status is '%s' (must be approved or paid).",
                $pr->request_no,
                $pr->status,
            ));
        }

        if ($prItem->is_receipt_closed) {
            throw new \DomainException(sprintf(
                "PR item #%d is already closed for receipts. Re-open it first if a correction is needed.",
                $prItem->id,
            ));
        }

        if (! $prItem->raw_material_id) {
            throw new \DomainException(sprintf(
                "PR item #%d does not reference a catalogued raw material. "
                . "Edit the PR to link a raw material before receiving against it.",
                $prItem->id,
            ));
        }

        $material = $prItem->rawMaterial;
        if (! $material) {
            throw new \DomainException(sprintf(
                "PR item #%d's raw_material_id %d does not resolve to a raw material row.",
                $prItem->id,
                (int) $prItem->raw_material_id,
            ));
        }

        if ($pr->supplier_id === null) {
            throw new \DomainException(sprintf(
                "PR %s has no supplier_id set. Assign a supplier to the PR before receiving.",
                $pr->request_no,
            ));
        }

        $quantity = (float) $data['quantity'];
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Receive quantity must be positive.');
        }

        // Over-receipt protection.
        $tolerancePct = (float) config('finance.vendor_invoices.match_tolerance_qty_pct', 5.0);
        $alreadyReceived = (float) $prItem->quantity_received;
        $expected = (float) $prItem->quantity;
        $projectedTotal = $alreadyReceived + $quantity;

        if ($expected > 0 && $projectedTotal > $expected) {
            $variancePct = (($projectedTotal - $expected) / $expected) * 100.0;
            if ($variancePct > $tolerancePct && ! $allowOverReceipt) {
                throw new \DomainException(sprintf(
                    "Receiving %s would over-receive against PR item #%d by %.2f%% "
                    . "(expected %s, already received %s, this receipt %s, projected total %s). "
                    . "Use override permission to proceed.",
                    $quantity,
                    $prItem->id,
                    $variancePct,
                    $expected,
                    $alreadyReceived,
                    $quantity,
                    $projectedTotal,
                ));
            }
        }

        return DB::transaction(function () use ($prItem, $material, $pr, $actor, $data, $quantity) {
            return $this->receiveService->execute(
                material: $material,
                actor:    $actor,
                data: [
                    'quantity'                  => $quantity,
                    'unit_cost'                 => (float) $data['unit_cost'],
                    'reference'                 => $data['reference'] ?? null,
                    'notes'                     => $data['notes'] ?? null,
                    'supplier_id'               => (int) $pr->supplier_id,
                    'purchase_request_item_id'  => (int) $prItem->id,
                    'receipt_reason'            => null, // not needed for PR-linked
                ],
                linkedPurchaseRequest: $pr,
            );
        });
    }

    /**
     * Receive several delivered PR lines under one delivery reference. The
     * outer transaction guarantees that a validation/posting failure on any
     * line rolls back every line in the delivery.
     *
     * @param array<int, array{purchase_request_item_id:int, quantity:float, unit_cost:float, notes?:?string}> $receipts
     */
    public function executeMany(
        PurchaseRequest $purchaseRequest,
        User $actor,
        array $receipts,
        ?string $reference = null,
        ?string $deliveryNotes = null,
        bool $allowOverReceipt = false,
    ): array {
        if ($receipts === []) {
            throw new \InvalidArgumentException('Select at least one material to receive.');
        }

        return DB::transaction(function () use (
            $purchaseRequest, $actor, $receipts, $reference, $deliveryNotes, $allowOverReceipt
        ): array {
            $results = [];

            foreach ($receipts as $receipt) {
                $item = PurchaseRequestItem::query()
                    ->where('purchase_request_id', $purchaseRequest->id)
                    ->lockForUpdate()
                    ->find($receipt['purchase_request_item_id']);

                if (! $item) {
                    throw new \DomainException('One of the selected receipt lines does not belong to this purchase request.');
                }

                $lineNotes = trim((string) ($receipt['notes'] ?? ''));
                $results[] = $this->execute(
                    prItem: $item,
                    actor: $actor,
                    data: [
                        'quantity' => (float) $receipt['quantity'],
                        'unit_cost' => (float) $receipt['unit_cost'],
                        'reference' => $reference,
                        'notes' => $lineNotes !== '' ? $lineNotes : $deliveryNotes,
                    ],
                    allowOverReceipt: $allowOverReceipt,
                );
            }

            return $results;
        });
    }
}
