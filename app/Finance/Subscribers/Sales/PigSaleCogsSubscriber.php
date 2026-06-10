<?php

namespace App\Finance\Subscribers\Sales;

use App\Finance\Drafts\JournalBatchDraft;
use App\Finance\Drafts\JournalDraft;
use App\Finance\Drafts\JournalLineDraft;
use App\Finance\Posting\PigCostingPurposes;
use App\Finance\Resolvers\FinanceAccountResolver;
use App\Finance\Subscribers\Subscriber;
use App\Models\DomainEvent;
use App\Models\Finance\BiologicalAssetStageTransfer;
use App\Models\Finance\GroupCostMovement;
use App\Models\Finance\GroupCostPool;
use App\Models\Finance\JournalBatch;
use App\Models\Finance\JournalPosting;
use App\Models\PigManagement;
use App\Models\PigSale;
use App\Services\Finance\BiologicalAssets\BiologicalAssetStageResolver;
use App\Services\Finance\BiologicalAssets\BiologicalAssetSubledgerService;
use App\Services\Finance\Costing\GroupCostPoolService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Phase 5 — posts COGS leg on pig sale.
 *
 *   Dr  5110 Cost of Pigs Sold       cogs_amount
 *         Cr  1230 Live Pig Inventory  cogs_amount
 *
 * Runs ALONGSIDE the existing PigSaleRecordedSubscriber (revenue
 * posting). Both listen to pig_sale.recorded but post independent
 * journal batches with different idempotency keys / purposes.
 *
 * Allocation formula:
 *   cogs_amount = pig_weight × (group.value_before / group.weight_before)
 *
 * Where pig_weight is sourced in this order:
 *   1. pig_sales.sale_weight (the operator-recorded sale weight)
 *   2. pigs_management.current_weight_kg_cache (fallback)
 *   3. The group's average weight (last resort)
 *
 * SKIP CONDITIONS (returns null → marked SKIPPED, cogs_amount=null on
 * pig_sales):
 *   - PigSale has no pig (pig_id null or pig deleted)
 *   - Pig has no group_id
 *   - Group pool is empty
 *   - Pig weight unresolvable
 *   - Computed cogs_amount is zero
 *
 * These all leave the revenue posting intact — only the COGS leg is
 * skipped. cogs_posting_status reflects the outcome.
 */
class PigSaleCogsSubscriber extends Subscriber
{
    public function __construct(
        protected FinanceAccountResolver $accountResolver,
        protected GroupCostPoolService $poolService,
        protected BiologicalAssetStageResolver $stageResolver,
        protected BiologicalAssetSubledgerService $bioSubledger,
    ) {
    }

    protected function eventType(): string
    {
        return \App\Domain\Events\Sales\PigSaleRecorded::TYPE;
    }

    protected function buildDraft(DomainEvent $event): ?JournalBatchDraft
    {
        $saleId = (int) $event->source_id;

        /** @var PigSale|null $sale */
        $sale = PigSale::with('pig')->find($saleId);
        if (! $sale) {
            Log::warning("PigSaleCogsSubscriber: pig_sale #{$saleId} not found.");
            return null;
        }

        // Don't recompute if we already wrote cogs on this sale.
        if ($sale->cogs_journal_batch_id !== null && $sale->cogs_posting_status === 'posted') {
            return null;
        }

        $pig = $sale->pig;
        if (! $pig) {
            // PigSale references a missing pig — soft-deleted edge case.
            $pig = PigManagement::withTrashed()->find($sale->pig_id);
        }

        if (! $pig) {
            $this->markSkipped($sale, 'no_pig_record');
            return null;
        }

        $groupId = (int) ($pig->group_id ?? 0);
        if ($groupId <= 0) {
            $this->markSkipped($sale, 'pig_has_no_group');
            return null;
        }

        // Resolve weight at sale.
        $pigWeight = $this->resolveSaleWeight($sale, $pig);
        if (bccomp($pigWeight, '0', 4) <= 0) {
            $this->markSkipped($sale, 'no_weight_available');
            return null;
        }

        // Record the pool decrement. The pool service computes the
        // allocation and writes the cost movement.
        try {
            $movement = $this->poolService->recordSaleCogs(
                groupId:    $groupId,
                pigWeight:  $pigWeight,
                pigSaleId:  $sale->id,
                occurredAt: CarbonImmutable::parse($sale->sale_date)->endOfDay(),
                reference:  sprintf('Pig sale #%d (%s)', $sale->id, $pig->code ?? 'pig#'.$pig->id),
                notes:      null,
            );
        } catch (\App\Services\Finance\Costing\InsufficientPoolException $e) {
            // Pool can't cover the sale — refuse, mark cogs_posting_status='failed'.
            $sale->update([
                'cogs_posting_status' => 'failed',
                'cogs_posting_error'  => $e->getMessage(),
                'cogs_group_id'       => $groupId,
                'cogs_pig_weight_kg'  => $pigWeight,
            ]);
            // Re-raise: the event will be marked FAILED. Operator
            // investigates and either adjusts the pool (manual_adjustment)
            // or re-queues.
            throw $e;
        } catch (\App\Services\Finance\Costing\DuplicateGroupCostMovementException) {
            // Already recorded by a previous attempt — fetch and continue.
            $movement = GroupCostMovement::query()
                ->where('source_type', GroupCostMovement::SOURCE_PIG_SALE)
                ->where('source_id',   $sale->id)
                ->first();
            if (! $movement) {
                throw new \RuntimeException(
                    "DuplicateGroupCostMovementException for sale #{$sale->id} but no row found."
                );
            }
        }

        $cogsAmount = bcsub('0', (string) $movement->value_delta, 4);  // positive
        if (bccomp($cogsAmount, '0', 4) <= 0) {
            $this->markSkipped($sale, 'zero_cogs_allocation');
            return null;
        }

        // Persist the computed COGS on the sale.
        $sale->update([
            'cogs_amount'         => $cogsAmount,
            'cogs_group_id'       => $groupId,
            'cogs_pig_weight_kg'  => $pigWeight,
            'cogs_posting_status' => 'pending',  // flips to 'posted' in onPostedSuccessfully
            'cogs_posting_error'  => null,
        ]);

        $cogsAcct    = (int) $this->accountResolver->byCode('5110')->id;
        $livePigAcct = (int) $this->stageResolver->accountForGroup($groupId)->id;

        $description = sprintf(
            'COGS — pig sale #%d (%s, %s kg)',
            $sale->id,
            $pig->code ?? 'pig#' . $pig->id,
            number_format((float) $pigWeight, 2),
        );

        $lines = [
            JournalLineDraft::debit(
                accountId:   $cogsAcct,
                amount:      $cogsAmount,
                description: $description,
                dimensions: [
                    'source_type' => PigSale::class,
                    'source_id'   => $sale->id,
                    'reference'   => 'Pig Sale #' . $sale->id,
                ],
            ),
            JournalLineDraft::credit(
                accountId:   $livePigAcct,
                amount:      $cogsAmount,
                description: $description,
                dimensions: [
                    'source_type' => PigSale::class,
                    'source_id'   => $sale->id,
                    'reference'   => 'Pig Sale #' . $sale->id,
                ],
            ),
        ];

        $journal = new JournalDraft(
            journalType:  'pig_sale_cogs',
            lines:        $lines,
            reference:    'Pig Sale #' . $sale->id . ' (COGS)',
            description:  $description,
            currencyCode: 'GHS',
            exchangeRate: '1',
            sourceType:   PigSale::class,
            sourceId:     $sale->id,
        );

        return new JournalBatchDraft(
            batchType:        'auto_pig_sale_cogs',
            postingDate:      CarbonImmutable::parse($sale->sale_date)->endOfDay(),
            journals:         [$journal],
            description:      $description,
            sourceModule:     'sales',
            sourceType:       PigSale::class,
            sourceId:         $sale->id,
            domainEventId:    (int) $event->id,
            idempotencyKey:   JournalPosting::buildIdempotencyKey(
                'pig_sale.recorded',
                PigSale::class,
                $sale->id,
                PigCostingPurposes::PIG_SALE_COGS,
            ),
            postingPurpose:   PigCostingPurposes::PIG_SALE_COGS,
            subscriberClass:  static::class,
        );
    }

    protected function onPostedSuccessfully(DomainEvent $event, JournalBatch $batch): void
    {
        $saleId = (int) $event->source_id;

        DB::transaction(function () use ($saleId, $event, $batch) {
            PigSale::query()->where('id', $saleId)->update([
                'cogs_journal_batch_id' => $batch->id,
                'cogs_posting_status'   => 'posted',
                'cogs_posting_error'    => null,
            ]);

            GroupCostMovement::query()
                ->where('source_type', GroupCostMovement::SOURCE_PIG_SALE)
                ->where('source_id', $saleId)
                ->update(['journal_batch_id' => $batch->id]);

            if (BiologicalAssetStageTransfer::query()
                    ->where('source_domain_event_id', $event->id)
                    ->exists()) {
                return;
            }

            $sale = PigSale::with('pig')->find($saleId);
            if (! $sale) {
                return;
            }

            $groupId = (int) ($sale->cogs_group_id ?? $sale->pig?->group_id ?? 0);
            if ($groupId <= 0) {
                return;
            }

            $this->bioSubledger->recordExit(
                groupId:             $groupId,
                amount:              (string) $sale->cogs_amount,
                pigCountDelta:       1,
                weightDelta:         (string) ($sale->cogs_pig_weight_kg ?? '0'),
                reason:              BiologicalAssetStageTransfer::REASON_SALE_COGS,
                journalBatchId:      $batch->id,
                sourceDomainEventId: $event->id,
                occurredAt:          CarbonImmutable::parse($sale->sale_date)->endOfDay(),
            );
        });
    }

    /**
     * Resolve the pig's weight at sale, in this preference order.
     */
    protected function resolveSaleWeight(PigSale $sale, PigManagement $pig): string
    {
        $w = (float) ($sale->sale_weight ?? 0);
        if ($w > 0) {
            return number_format($w, 4, '.', '');
        }

        $w = (float) ($pig->current_weight_kg_cache ?? 0);
        if ($w > 0) {
            return number_format($w, 4, '.', '');
        }

        $groupId = (int) ($pig->group_id ?? 0);
        if ($groupId > 0) {
            $pool = GroupCostPool::query()
                ->where('organization_id', $sale->organization_id)
                ->where('group_id', $groupId)
                ->first();

            if ($pool && (int) $pool->on_hand_pig_count > 0 && bccomp((string) $pool->total_weight_kg, '0', 4) > 0) {
                return bcdiv((string) $pool->total_weight_kg, (string) $pool->on_hand_pig_count, 4);
            }
        }

        return '0';
    }

    protected function markSkipped(PigSale $sale, string $reason): void
    {
        $sale->update([
            'cogs_posting_status' => 'skipped',
            'cogs_posting_error'  => $reason,
        ]);
    }
}
