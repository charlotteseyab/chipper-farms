<?php

namespace App\Services\Inventory;

use App\Domain\Events\Inventory\FinishedFeedCounted;
use App\Domain\Events\Support\SourceReference;
use App\Models\FeedType;
use App\Models\Finance\InventoryBalance;
use App\Models\Finance\InventoryMovement;
use App\Models\Organization;
use App\Models\RawMaterial;
use App\Models\StockCount;
use App\Models\StockCountLine;
use App\Models\User;
use App\Services\DomainEvents\DispatchDomainEvent;
use App\Services\Finance\Inventory\InventoryService;
use App\Support\CurrentOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Records a physical stock count. For every counted item the ledger is set to
 * the counted quantity; differences are valued at average cost and posted:
 * raw materials through the existing adjustment flow, finished feed through
 * FinishedFeedCounted. Items counted with no difference are kept as a record.
 */
class StockCountService
{
    public const PERMISSIONS = [
        InventoryMovement::ITEM_KIND_RAW_MATERIAL  => 'manage_raw_materials',
        InventoryMovement::ITEM_KIND_FINISHED_FEED => 'manage_feed_production',
    ];

    public function __construct(
        protected CurrentOrganization $currentOrganization,
        protected InventoryService $inventory,
        protected AdjustRawMaterialService $adjustRawMaterial,
        protected DispatchDomainEvent $dispatcher,
    ) {
    }

    /**
     * @param  array{submission_key: string, counted_at?: ?string, reference?: ?string, notes?: ?string,
     *               lines: array<int, array{item_kind: string, item_id: int, counted_qty: float}>}  $data
     */
    public function record(User $actor, array $data): StockCount
    {
        $orgId = $this->currentOrganization->requireId();
        abort_unless((int) $actor->organization_id === $orgId, 403);

        $lines = collect($data['lines'])->values();
        if ($lines->isEmpty()) {
            throw ValidationException::withMessages(['lines' => 'Enter a counted quantity for at least one item.']);
        }
        foreach ($lines->pluck('item_kind')->unique() as $kind) {
            abort_unless(isset(self::PERMISSIONS[$kind]) && $actor->hasPermission(self::PERMISSIONS[$kind]), 403);
        }
        $countedAt = isset($data['counted_at']) ? CarbonImmutable::parse($data['counted_at']) : CarbonImmutable::now();

        if ($countedAt->isFuture()) {
            throw ValidationException::withMessages(['counted_at' => 'The count date cannot be in the future.']);
        }

        return DB::transaction(function () use ($actor, $data, $lines, $orgId, $countedAt) {
            Organization::whereKey($orgId)->lockForUpdate()->firstOrFail();
            if ($existing = StockCount::where('organization_id', $orgId)->where('submission_key', $data['submission_key'])->first()) {
                return $existing;
            }

            $count = StockCount::create([
                'organization_id' => $orgId,
                'counted_at'      => $countedAt,
                'reference'       => $data['reference'] ?? null,
                'notes'           => $data['notes'] ?? null,
                'counted_by'      => $actor->id,
                'submission_key'  => $data['submission_key'],
            ]);
            $reference = $count->reference ?: 'Stock count #'.$count->id;

            foreach ($lines as $entry) {
                $entry['item_kind'] === InventoryMovement::ITEM_KIND_RAW_MATERIAL
                    ? $this->countRawMaterial($actor, $count, $entry, $orgId, $reference)
                    : $this->countFinishedFeed($actor, $count, $entry, $orgId, $reference, $countedAt);
            }

            return $count->load('lines');
        }, 3);
    }

    private function countRawMaterial(User $actor, StockCount $count, array $entry, int $orgId, string $reference): void
    {
        $material = RawMaterial::where('organization_id', $orgId)->whereKey($entry['item_id'])->lockForUpdate()->firstOrFail();
        $line = $this->line($count, $entry, $orgId, $material->name, (string) $material->unit);
        if ((float) $line->difference_qty === 0.0) {
            return;
        }

        $result = $this->adjustRawMaterial->execute($material, $actor, [
            'adjustment_type' => 'set',
            'quantity'        => (float) $entry['counted_qty'],
            'reference'       => $reference,
            'adjusted_at'     => $count->counted_at->toDateTimeString(),
            'notes'           => sprintf('Stock count: counted %s %s, system had %s.', $this->qty($line->counted_qty), $material->unit, $this->qty($line->system_qty)),
        ]);

        $line->update([
            'raw_material_stock_log_id' => $result['log']->id,
            'inventory_movement_id'     => $result['movement']->id,
            'value_delta'               => $result['movement']->value_delta,
            'posting_status'            => StockCountLine::POSTING_PENDING,
        ]);
    }

    private function countFinishedFeed(User $actor, StockCount $count, array $entry, int $orgId, string $reference, CarbonImmutable $countedAt): void
    {
        $feedType = FeedType::where('organization_id', $orgId)->findOrFail($entry['item_id']);
        $line = $this->line($count, $entry, $orgId, $feedType->name, 'kg');
        if ((float) $line->difference_qty === 0.0) {
            return;
        }

        $movement = $this->inventory->recordAdjustment(
            itemKind:    InventoryMovement::ITEM_KIND_FINISHED_FEED,
            itemId:      (int) $feedType->id,
            type:        'set',
            quantity:    (float) $entry['counted_qty'],
            sourceType:  InventoryMovement::SOURCE_STOCK_COUNT_LINE,
            sourceId:    (int) $line->id,
            occurredAt:  $countedAt,
            actorUserId: $actor->id,
            reference:   $reference,
        );

        $line->update([
            'inventory_movement_id' => $movement->id,
            'value_delta'           => $movement->value_delta,
            'posting_status'        => StockCountLine::POSTING_PENDING,
        ]);

        $this->dispatcher->dispatch(new FinishedFeedCounted(
            source: new SourceReference(module: 'inventory', sourceType: StockCountLine::class, sourceId: (int) $line->id, organizationId: $orgId),
            occurredAt: $countedAt,
            dispatchedByUserId: $actor->id,
            feedTypeId: (int) $feedType->id,
            feedTypeName: (string) $feedType->name,
            quantityDelta: (float) $movement->quantity_delta,
            countedQuantity: (float) $entry['counted_qty'],
        ));
    }

    /** Saves the line with the ledger quantity read under the balance lock. */
    private function line(StockCount $count, array $entry, int $orgId, string $name, string $unit): StockCountLine
    {
        $system = (float) (InventoryBalance::where('organization_id', $orgId)
            ->where('item_kind', $entry['item_kind'])
            ->where('item_id', $entry['item_id'])
            ->lockForUpdate()
            ->value('on_hand_qty') ?? 0);
        // Setting today's balance from an older count would erase movements
        // made after the physical count. Recount rather than rewrite that history.
        if (InventoryMovement::where('organization_id', $orgId)
            ->where('item_kind', $entry['item_kind'])->where('item_id', $entry['item_id'])
            ->where('occurred_at', '>', $count->counted_at)->exists()) {
            throw ValidationException::withMessages(['counted_at' => "{$name} has stock movements after this count time. Recount the item and enter the new count time."]);
        }
        $counted = round((float) $entry['counted_qty'], 4);

        return $count->lines()->create([
            'item_kind'      => $entry['item_kind'],
            'item_id'        => (int) $entry['item_id'],
            'item_name'      => $name,
            'unit'           => $unit,
            'system_qty'     => round($system, 4),
            'counted_qty'    => $counted,
            'difference_qty' => round($counted - $system, 4),
            'posting_status' => StockCountLine::POSTING_NOT_REQUIRED,
        ]);
    }

    private function qty(mixed $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 4), '0'), '.');
    }
}
