<?php

namespace App\Services\Inventory;

use App\Domain\Events\Inventory\RawMaterialReceived;
use App\Domain\Events\Support\SourceReference;
use App\Models\Counterparty;
use App\Models\Finance\InventoryMovement;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\RawMaterial;
use App\Models\RawMaterialStockLog;
use App\Models\User;
use App\Services\DomainEvents\DispatchDomainEvent;
use App\Services\Finance\Inventory\InventoryService;
use App\Support\CurrentOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Phase 4 — receive raw material stock.
 *
 * REWRITTEN to:
 *   - Call the unified InventoryService instead of the deleted
 *     RawMaterialValuationService.
 *   - Capture supplier_id on the stock log (Phase 0.8 column was dead
 *     before).
 *   - Capture purchase_request_item_id when the receipt fulfils a PR
 *     line (Phase 4 new column).
 *   - Capture receipt_reason for direct (non-PR) receipts (audit trail
 *     for "why was this received without a PR").
 *   - Validate that EITHER a PR linkage OR an explicit receipt_reason
 *     is provided — receipts can no longer be created with neither.
 *
 * INPUT SHAPE
 *
 *   $data = [
 *     'quantity'                 => float (positive),
 *     'unit_cost'                => float (non-negative),
 *     'reference'                => ?string (e.g. GRN number),
 *     'notes'                    => ?string,
 *     'supplier_id'              => ?int (counterparty id; required unless PR-linked),
 *     'purchase_request_item_id' => ?int (PR line being fulfilled),
 *     'receipt_reason'           => ?string (required when not PR-linked,
 *                                   e.g. 'opening_stock', 'donation',
 *                                   'sample', 'returns', 'correction'),
 *   ]
 *
 * The caller is RESPONSIBLE for choosing the correct path:
 *   - PR-linked: ReceiveAgainstPurchaseRequestService is the preferred
 *     orchestrator; it sets the linkage and supplier_id from the PR.
 *   - Direct: RawMaterialController::receiveStock requires the operator
 *     to pick a supplier + reason.
 *
 * IDEMPOTENCY
 *
 *   The stock log row is the source identity. InventoryService records
 *   the movement keyed on (source_type='stock_log', source_id=log.id);
 *   retries on the same log id are a no-op (DuplicateMovementException
 *   bubbles up as a caller signal).
 *
 * DOMAIN EVENT
 *
 *   Single RawMaterialReceived event, payload includes the supplier_id
 *   and purchase_request_id (NULL when direct). The subscriber routes
 *   the GL posting accordingly.
 */
class ReceiveRawMaterialService
{
    public function __construct(
        protected DispatchDomainEvent $dispatcher,
        protected CurrentOrganization $currentOrganization,
        protected InventoryService $inventory,
    ) {
    }

    /**
     * @param  array{
     *   quantity: float,
     *   unit_cost: float,
     *   reference?: ?string,
     *   notes?: ?string,
     *   supplier_id?: ?int,
     *   purchase_request_item_id?: ?int,
     *   receipt_reason?: ?string,
     * }  $data
     */
    public function execute(
        RawMaterial $material,
        User $actor,
        array $data,
        ?PurchaseRequest $linkedPurchaseRequest = null,
    ): array {
        $organizationId = $this->currentOrganization->requireId();
        $quantity = (float) $data['quantity'];
        $unitCost = (float) ($data['unit_cost'] ?? 0);

        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Receive quantity must be positive.');
        }
        if ($unitCost < 0) {
            throw new \InvalidArgumentException('Receive unit_cost must be non-negative.');
        }

        $prItemId       = $data['purchase_request_item_id'] ?? null;
        $supplierId     = $data['supplier_id'] ?? null;
        $receiptReason  = $data['receipt_reason'] ?? null;

        // Phase 4 receipt invariant. EXACTLY one of these branches:
        //   (a) PR-linked: $prItemId set; supplier_id derived from PR
        //   (b) Direct:    $prItemId null; supplier_id explicit; receipt_reason required
        //   (c) Legacy / opening stock seeded via service: $prItemId null,
        //       supplier_id null, receipt_reason = 'opening_stock'.
        if ($prItemId !== null) {
            // Validate the PR item exists and (if it carries a raw
            // material) matches the receipt's material.
            /** @var PurchaseRequestItem|null $prItem */
            $prItem = PurchaseRequestItem::with('purchaseRequest')->find($prItemId);
            if (! $prItem) {
                throw new \DomainException("Purchase request item #{$prItemId} not found.");
            }

            if ($prItem->raw_material_id !== null
                && (int) $prItem->raw_material_id !== (int) $material->id) {
                throw new \DomainException(sprintf(
                    'PR item #%d expects raw material #%d but receipt is for raw material #%d.',
                    $prItem->id,
                    (int) $prItem->raw_material_id,
                    (int) $material->id,
                ));
            }

            // Derive supplier from PR if caller didn't override.
            if ($supplierId === null) {
                $supplierId = $prItem->purchaseRequest?->supplier_id;
            }

            if ($supplierId === null) {
                throw new \DomainException(sprintf(
                    'PR #%d has no supplier_id set. Assign a supplier on the PR before receiving.',
                    (int) $prItem->purchase_request_id,
                ));
            }

            // Default the linked PR for the event payload.
            $linkedPurchaseRequest ??= $prItem->purchaseRequest;
        } else {
            // Direct receipt. Operator must supply a supplier + reason.
            if ($supplierId === null) {
                throw new \DomainException(
                    'Direct (non-PR) raw material receipts must specify a supplier_id. '
                    . 'Use the receive-against-PR flow if this should be tied to a PR.'
                );
            }
            if (empty($receiptReason)) {
                throw new \DomainException(
                    'Direct (non-PR) raw material receipts must specify a receipt_reason '
                    . "(e.g. 'opening_stock', 'donation', 'sample', 'returns', 'correction')."
                );
            }
        }

        // Confirm supplier exists.
        if (! Counterparty::query()->whereKey($supplierId)->exists()) {
            throw new \DomainException("Supplier #{$supplierId} not found.");
        }

        return DB::transaction(function () use (
            $material, $actor, $data, $organizationId,
            $quantity, $unitCost, $supplierId, $prItemId, $receiptReason,
            $linkedPurchaseRequest,
        ) {
            // 1-2. quantity_in_stock is mirrored from the ledger by
            //      InventoryService. unit_cost keeps the last price paid.
            $material->update(['unit_cost' => $unitCost]);

            // 3. Stock log — the authoritative record of the physical
            //    receipt event, now with full FK context.
            $log = $material->stockLogs()->create([
                'type'                      => 'receive',
                'quantity'                  => $quantity,
                'unit_cost'                 => $unitCost,
                'reference'                 => $data['reference'] ?? null,
                'notes'                     => $data['notes'] ?? null,
                'performed_by'              => $actor->id,
                'organization_id'           => $organizationId,
                'supplier_id'               => $supplierId,
                'purchase_request_item_id'  => $prItemId,
                'receipt_reason'            => $receiptReason,
                'posting_status'            => 'pending',
                'is_posted'                 => false,
            ]);

            // 4. Unified inventory movement. Idempotent on stock_log_id.
            $movement = $this->inventory->recordReceive(
                itemKind:      InventoryMovement::ITEM_KIND_RAW_MATERIAL,
                itemId:        (int) $material->id,
                quantity:      $quantity,
                unitCost:      $unitCost,
                sourceType:    InventoryMovement::SOURCE_STOCK_LOG,
                sourceId:      (int) $log->id,
                occurredAt:    CarbonImmutable::instance($log->created_at ?? now()),
                actorUserId:   $actor->id,
                reference:     $data['reference'] ?? null,
                notes:         $data['notes'] ?? null,
                itemNameCache: (string) $material->name,
                itemUnitCache: (string) $material->unit,
            );

            // 5. Roll forward the PR item's running received qty
            //    (when PR-linked).
            if ($prItemId !== null) {
                $this->advancePurchaseRequestItemReceipt($prItemId, $quantity);
            }

            // 6. Domain event for the GL subscriber.
            $this->dispatcher->dispatch(new RawMaterialReceived(
                source: new SourceReference(
                    module:         'inventory',
                    sourceType:     RawMaterialStockLog::class,
                    sourceId:       (int) $log->id,
                    organizationId: $organizationId,
                ),
                occurredAt: CarbonImmutable::now(),
                dispatchedByUserId: $actor->id,
                rawMaterialId:     (int) $material->id,
                rawMaterialName:   (string) $material->name,
                quantity:          $quantity,
                unit:              (string) $material->unit,
                unitCost:          $unitCost,
                totalCost:         $quantity * $unitCost,
                reference:         $data['reference'] ?? null,
                purchaseRequestId: $linkedPurchaseRequest?->id,
            ));

            return [
                'material'  => $material->fresh(),
                'log'       => $log->fresh(),
                'movement'  => $movement,
            ];
        });
    }

    /**
     * Advance the PR item's quantity_received and close it if the
     * cumulative receipts have reached the requested qty (within
     * tolerance).
     *
     * Close-on-met is a soft signal — the receive-against-PR controller
     * can re-open if a correction is needed.
     */
    protected function advancePurchaseRequestItemReceipt(int $prItemId, float $deltaReceived): void
    {
        $prItem = PurchaseRequestItem::lockForUpdate()->find($prItemId);
        if (! $prItem) {
            return; // already validated upstream, defensive
        }

        $newReceived = (float) $prItem->quantity_received + $deltaReceived;
        $expected = (float) $prItem->quantity;
        $tolerancePct = (float) config('finance.vendor_invoices.match_tolerance_qty_pct', 5.0);

        $update = ['quantity_received' => $newReceived];

        // Close the PR item line when received quantity reaches the
        // expected amount within the qty tolerance.
        if (! $prItem->is_receipt_closed && $expected > 0) {
            $variancePct = (($newReceived - $expected) / $expected) * 100.0;
            if ($variancePct >= -1 * $tolerancePct) {
                $update['is_receipt_closed'] = true;
                $update['receipt_closed_at'] = now();
            }
        }

        $prItem->update($update);
    }
}
