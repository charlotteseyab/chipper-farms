<?php

namespace App\Finance\Subscribers\Inventory;

use App\Finance\Drafts\JournalBatchDraft;
use App\Finance\Drafts\JournalDraft;
use App\Finance\Drafts\JournalLineDraft;
use App\Finance\Subscribers\Subscriber;
use App\Models\DomainEvent;
use App\Models\Finance\GrIrEntry;
use App\Models\Finance\InventoryMovement;
use App\Models\Finance\JournalBatch;
use App\Models\Finance\JournalPosting;
use App\Models\PurchaseRequestItem;
use App\Models\RawMaterial;
use App\Models\RawMaterialStockLog;
use App\Services\Finance\Inventory\InventoryAccountResolver;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Phase 4 — posts the GL entry for a raw material receipt.
 *
 * TWO PATHS based on the source receipt's PR linkage:
 *
 *   PR-LINKED RECEIPT (purchase_request_item_id set):
 *
 *     Dr  Inventory account     receipt_value (qty × unit_cost)
 *           Cr  GR/IR account   receipt_value
 *
 *     This is the goods-received-but-not-invoiced model. The AP invoice
 *     posting later clears GR/IR with the supplier's invoice value
 *     (possibly with PPV).
 *
 *     A gr_ir_entries row is created in OUTSTANDING status, capturing
 *     the receipt amount, the PR item link, the supplier, and the raw
 *     material. This is the operational mirror the ageing report reads.
 *
 *   DIRECT RECEIPT (no PR linkage):
 *
 *     Dr  Inventory account            receipt_value
 *           Cr  Inventory Suspense     receipt_value
 *
 *     No gr_ir_entries row. The credit sits in a clearing account
 *     until ops attributes the receipt — typically reclassed to AP
 *     (if a back-dated invoice arrives) or written off to Sundry /
 *     Inventory Adjustments (donations, samples, etc.).
 *
 * IDEMPOTENCY
 *
 *   The journal posting idempotency key is
 *     "raw_material_received_posted:RawMaterialStockLog:{id}:inventory_receipt"
 *
 *   Re-deliveries of the same domain event find the existing posting
 *   and exit cleanly (the base Subscriber catches DuplicatePostingException
 *   and marks the event processed).
 *
 *   The gr_ir_entries insert is guarded by UNIQUE(stock_log_id); a
 *   duplicate gr_ir_entries row from a retry is a no-op.
 *
 * POST-SUCCESS SIDE EFFECTS
 *
 *   onPostedSuccessfully(): set stock_log.posting_status='posted',
 *   is_posted=true, journal_batch_id, and update the inventory_movements
 *   row's journal_batch_id forward-link.
 */
class RawMaterialReceivedSubscriber extends Subscriber
{
    public function __construct(
        protected InventoryAccountResolver $accountResolver,
    ) {
    }

    protected function eventType(): string
    {
        return \App\Domain\Events\Inventory\RawMaterialReceived::TYPE;
    }

    protected function buildDraft(DomainEvent $event): ?JournalBatchDraft
    {
        $stockLogId = (int) $event->source_id;

        /** @var RawMaterialStockLog|null $log */
        $log = RawMaterialStockLog::with(['rawMaterial', 'supplier', 'purchaseRequestItem'])
            ->find($stockLogId);

        if (! $log) {
            // The source row was deleted between dispatch and processing.
            // This shouldn't happen with normal operation, but if it
            // does, we skip rather than fail — there's nothing to post.
            \Illuminate\Support\Facades\Log::warning(
                "RawMaterialReceivedSubscriber: stock log #{$stockLogId} not found. Skipping."
            );
            return null;
        }

        if ($log->type !== 'receive') {
            // Defensive — events should only fire for receives.
            return null;
        }

        if ((float) $log->quantity <= 0) {
            // Defensive — receives should always have positive qty.
            return null;
        }

        $material = $log->rawMaterial;
        if (! $material) {
            \Illuminate\Support\Facades\Log::warning(
                "RawMaterialReceivedSubscriber: stock log #{$log->id} has no raw_material. Skipping."
            );
            return null;
        }

        $quantity      = (string) $log->quantity;
        $unitCost      = (string) $log->unit_cost;
        $receiptAmount = bcmul($quantity, $unitCost, 4);

        if (bccomp($receiptAmount, '0', 4) === 0) {
            // Zero-value receipt — no GL movement needed (e.g. free
            // sample at zero cost). Skip cleanly.
            return null;
        }

        $inventoryAccountId = $this->accountResolver->inventoryAccountForRawMaterial($material);

        $isLinked = ! empty($log->purchase_request_item_id);

        if ($isLinked) {
            $creditAccountId = $this->accountResolver->grIrAccountForRawMaterial($material);
            $creditDescription = 'GR/IR accrual (received, awaiting invoice)';
        } else {
            $creditAccountId = $this->accountResolver->suspenseAccountForRawMaterial($material);
            $creditDescription = 'Inventory suspense (direct receipt, no PR)';
        }

        $debitDescription = sprintf(
            'Inventory receipt: %s × %s @ %s',
            $material->name,
            number_format((float) $quantity, 4),
            number_format((float) $unitCost, 6),
        );

        $sourceRef = $log->reference ?? sprintf('GRN #%d', $log->id);

        $lines = [
            JournalLineDraft::debit(
                accountId:   $inventoryAccountId,
                amount:      $receiptAmount,
                description: $debitDescription,
                dimensions: [
                    'counterparty_id' => $log->supplier_id,
                    'source_type'     => RawMaterialStockLog::class,
                    'source_id'       => $log->id,
                    'reference'       => $sourceRef,
                ],
            ),
            JournalLineDraft::credit(
                accountId:   $creditAccountId,
                amount:      $receiptAmount,
                description: $creditDescription,
                dimensions: [
                    'counterparty_id' => $log->supplier_id,
                    'source_type'     => RawMaterialStockLog::class,
                    'source_id'       => $log->id,
                    'reference'       => $sourceRef,
                ],
            ),
        ];

        $description = sprintf(
            'Receipt of %s × %s — Stock Log #%d',
            $material->name,
            number_format((float) $quantity, 4),
            $log->id,
        );

        $journal = new JournalDraft(
            journalType:  'inventory_receipt',
            lines:        $lines,
            reference:    $sourceRef,
            description:  $description,
            currencyCode: 'GHS',
            exchangeRate: '1',
            sourceType:   RawMaterialStockLog::class,
            sourceId:     $log->id,
        );

        return new JournalBatchDraft(
            batchType:        'auto_inventory_receipt',
            postingDate:      CarbonImmutable::instance($log->created_at ?? now()),
            journals:         [$journal],
            description:      $description,
            sourceModule:     'inventory',
            sourceType:       RawMaterialStockLog::class,
            sourceId:         $log->id,
            domainEventId:    (int) $event->id,
            idempotencyKey:   JournalPosting::buildIdempotencyKey(
                'raw_material_received_posted',
                RawMaterialStockLog::class,
                $log->id,
                JournalPosting::PURPOSE_INVENTORY_RECEIPT,
            ),
            postingPurpose:   JournalPosting::PURPOSE_INVENTORY_RECEIPT,
            subscriberClass:  static::class,
        );
    }

    protected function onPostedSuccessfully(DomainEvent $event, JournalBatch $batch): void
    {
        $stockLogId = (int) $event->source_id;

        DB::transaction(function () use ($stockLogId, $batch) {
            /** @var RawMaterialStockLog|null $log */
            $log = RawMaterialStockLog::with(['purchaseRequestItem.purchaseRequest', 'rawMaterial'])
                ->find($stockLogId);
            if (! $log) {
                return;
            }

            $log->update([
                'is_posted'        => true,
                'posted_at'        => now(),
                'posting_status'   => 'posted',
                'posting_error'    => null,
                'journal_batch_id' => $batch->id,
            ]);

            // Forward-link the inventory movement to the journal batch.
            InventoryMovement::query()
                ->where('source_type', InventoryMovement::SOURCE_STOCK_LOG)
                ->where('source_id', $log->id)
                ->update(['journal_batch_id' => $batch->id]);

            // Create the gr_ir_entries row when PR-linked.
            if ($log->purchase_request_item_id) {
                $prItem = $log->purchaseRequestItem;
                $supplierId = $log->supplier_id ?? $prItem?->purchaseRequest?->supplier_id;
                $material = $log->rawMaterial;

                if ($prItem && $supplierId && $material) {
                    GrIrEntry::firstOrCreate(
                        ['stock_log_id' => $log->id],
                        [
                            'organization_id'           => $log->organization_id,
                            'purchase_request_item_id'  => $prItem->id,
                            'supplier_id'               => $supplierId,
                            'raw_material_id'           => $material->id,
                            'quantity'                  => abs((float) $log->quantity),
                            'receipt_unit_cost'         => (float) $log->unit_cost,
                            'receipt_amount'            => abs((float) $log->quantity) * (float) $log->unit_cost,
                            'status'                    => GrIrEntry::STATUS_OUTSTANDING,
                            'occurred_at'               => $log->created_at ?? now(),
                            'receipt_journal_batch_id'  => $batch->id,
                        ],
                    );
                }
            }
        });
    }
}
