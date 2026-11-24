<?php

namespace App\Services\Health;

use App\Models\Finance\InventoryBalance;
use App\Models\Finance\InventoryMovement;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\MedicineStockAdjustment;
use App\Models\User;
use App\Services\Audit\AuditTrailService;
use App\Services\Finance\Inventory\InsufficientInventoryException;
use App\Services\Finance\Inventory\InventoryService;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Medicine stock, held per batch in the shared inventory ledger (item kind
 * medicine_batch). Receiving a batch and adjusting or counting it are the
 * only ways stock goes in or is corrected; doses take it out through
 * RecordDoseService. Cost is kept in the ledger but never posted to the GL
 * (decision D10). Quantities are in the medicine's stock unit.
 */
class MedicineStockService
{
    private const KIND = InventoryMovement::ITEM_KIND_MEDICINE_BATCH;

    public function __construct(
        protected InventoryService $inventory,
        protected AuditTrailService $audit,
    ) {
    }

    public function receive(User $actor, Medicine $medicine, array $data): MedicineBatch
    {
        if (! $medicine->is_active) {
            throw ValidationException::withMessages(['batch_number' => "{$medicine->name} is marked not in use. Turn it back on before receiving stock."]);
        }

        try {
            return $this->receiveBatch($actor, $medicine, $data);
        } catch (UniqueConstraintViolationException) {
            // Two people received the same batch number at the same moment; the other one saved it.
            throw ValidationException::withMessages(['batch_number' => 'Batch '.trim($data['batch_number']).' has just been received by someone else.']);
        }
    }

    private function receiveBatch(User $actor, Medicine $medicine, array $data): MedicineBatch
    {
        return DB::transaction(function () use ($actor, $medicine, $data) {
            $number = trim($data['batch_number']);
            if (MedicineBatch::where('medicine_id', $medicine->id)->where('batch_number', $number)->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['batch_number' => "Batch {$number} has already been received. Use an adjustment to change its stock."]);
            }

            $batch = MedicineBatch::create([
                'medicine_id' => $medicine->id, 'batch_number' => $number, 'expiry_date' => $data['expiry_date'],
                'received_at' => $data['received_at'], 'quantity_received' => $data['quantity_received'],
                'unit_cost' => $data['unit_cost'] ?? 0, 'supplier' => $data['supplier'] ?? null,
                'notes' => $data['notes'] ?? null, 'received_by' => $actor->id,
            ]);
            $movement = $this->inventory->recordReceive(
                itemKind: self::KIND, itemId: $batch->id,
                quantity: (float) $batch->quantity_received, unitCost: (float) $batch->unit_cost,
                sourceType: InventoryMovement::SOURCE_MEDICINE_BATCH_RECEIPT, sourceId: $batch->id,
                occurredAt: CarbonImmutable::parse($data['received_at']), actorUserId: $actor->id,
                reference: $number, itemNameCache: $medicine->display_name.' · '.$number, itemUnitCache: $medicine->stock_unit,
            );

            $this->audit->log('health', 'medicine_batch_received', [
                'user' => $actor, 'auditable' => $batch, 'organization_id' => $batch->organization_id, 'record_label' => $medicine->name,
                'description' => "Received {$this->qty($batch->quantity_received)} {$medicine->stock_unit} of {$medicine->display_name}, batch {$number}, expiring {$batch->expiry_date->format('d M Y')}.",
                'new_values' => $batch->only(['batch_number', 'expiry_date', 'received_at', 'quantity_received', 'unit_cost', 'supplier']),
                'metadata' => ['inventory_movement_id' => $movement->id],
            ]);

            return $batch;
        });
    }

    /** Add, remove or count (set) one batch's stock, with a reason. */
    public function adjust(User $actor, MedicineBatch $batch, string $type, string $quantity, string $reason, ?string $adjustedAt = null): MedicineStockAdjustment
    {
        if (! array_key_exists($type, MedicineStockAdjustment::TYPES)) {
            throw new \InvalidArgumentException("Unknown adjustment type {$type}.");
        }

        return DB::transaction(function () use ($actor, $batch, $type, $quantity, $reason, $adjustedAt) {
            $batch = MedicineBatch::with('medicine')->whereKey($batch->id)->lockForUpdate()->firstOrFail();
            $before = $this->onHand($batch);
            if ($type === 'count' && bccomp($quantity, $before, 4) === 0) {
                throw ValidationException::withMessages(['quantity' => 'The count matches the stock on record; nothing to change.']);
            }
            $at = $adjustedAt ? CarbonImmutable::parse($adjustedAt) : CarbonImmutable::now();

            $adjustment = MedicineStockAdjustment::create([
                'medicine_batch_id' => $batch->id, 'type' => $type, 'quantity' => $quantity,
                'quantity_before' => $before, 'quantity_after' => $before, 'reason' => $reason,
                'adjusted_at' => $at, 'adjusted_by' => $actor->id,
            ]);
            try {
                $movement = $this->inventory->recordAdjustment(
                    itemKind: self::KIND, itemId: $batch->id, type: $type === 'count' ? 'set' : $type, quantity: (float) $quantity,
                    sourceType: InventoryMovement::SOURCE_MEDICINE_STOCK_ADJUSTMENT, sourceId: $adjustment->id,
                    occurredAt: $at, actorUserId: $actor->id, reference: $batch->batch_number, notes: $reason,
                );
            } catch (InsufficientInventoryException) {
                throw ValidationException::withMessages(['quantity' => "Only {$this->qty($before)} {$batch->medicine->stock_unit} of batch {$batch->batch_number} is on record."]);
            }
            $adjustment->update(['quantity_after' => (string) $movement->quantity_on_hand_after, 'inventory_movement_id' => $movement->id]);

            $this->audit->log('health', 'medicine_stock_adjusted', [
                'user' => $actor, 'auditable' => $adjustment, 'organization_id' => $batch->organization_id, 'record_label' => $batch->medicine->name,
                'description' => MedicineStockAdjustment::TYPES[$type]." for {$batch->medicine->display_name} batch {$batch->batch_number}: "
                    ."{$this->qty($before)} to {$this->qty($movement->quantity_on_hand_after)} {$batch->medicine->stock_unit}. {$reason}",
                'old_values' => ['on_hand' => $before],
                'new_values' => ['on_hand' => (string) $movement->quantity_on_hand_after],
            ]);

            return $adjustment;
        });
    }

    public function onHand(MedicineBatch|int $batch): string
    {
        return $this->onHandByBatch([$batch instanceof MedicineBatch ? $batch->id : $batch])->first() ?? '0';
    }

    /** @return Collection<int, string> batch id => on hand */
    public function onHandByBatch(array $batchIds): Collection
    {
        return InventoryBalance::where('item_kind', self::KIND)->whereIn('item_id', $batchIds)
            ->pluck('on_hand_qty', 'item_id')->map(fn ($qty) => (string) $qty);
    }

    /** @return Collection<int, string> medicine id => on hand across its batches */
    public function onHandByMedicine(array $medicineIds): Collection
    {
        return InventoryBalance::query()
            ->join('medicine_batches', 'medicine_batches.id', '=', 'inventory_balances.item_id')
            ->where('inventory_balances.item_kind', self::KIND)
            ->whereIn('medicine_batches.medicine_id', $medicineIds)
            ->groupBy('medicine_batches.medicine_id')
            ->selectRaw('medicine_batches.medicine_id, SUM(inventory_balances.on_hand_qty) AS qty')
            ->pluck('qty', 'medicine_id')->map(fn ($qty) => (string) $qty);
    }

    /**
     * Batches that can be used on a date, earliest expiry first (decision D6):
     * active, not past expiry, with stock. Each carries an `on_hand` attribute.
     */
    public function usableBatches(Medicine $medicine, string $date): Collection
    {
        $batches = MedicineBatch::where('medicine_id', $medicine->id)->where('is_active', true)
            ->whereDate('expiry_date', '>=', CarbonImmutable::parse($date)->toDateString())
            ->whereDate('received_at', '<=', CarbonImmutable::parse($date)->toDateString())
            ->orderBy('expiry_date')->orderBy('id')->get();
        $onHand = $this->onHandByBatch($batches->modelKeys());

        return $batches->each(fn (MedicineBatch $b) => $b->setAttribute('on_hand', $onHand[$b->id] ?? '0'))
            ->filter(fn (MedicineBatch $b) => bccomp($b->on_hand, '0', 4) > 0)->values();
    }

    private function qty(string|float|null $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 4, '.', ''), '0'), '.') ?: '0';
    }
}
