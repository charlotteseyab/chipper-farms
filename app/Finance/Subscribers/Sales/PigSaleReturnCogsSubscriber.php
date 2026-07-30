<?php

namespace App\Finance\Subscribers\Sales;

use App\Domain\Events\Sales\SalesReturnRecorded;
use App\Finance\Drafts\JournalBatchDraft;
use App\Finance\Drafts\JournalDraft;
use App\Finance\Drafts\JournalLineDraft;
use App\Finance\Subscribers\Subscriber;
use App\Models\DomainEvent;
use App\Models\Finance\BiologicalAssetStageTransfer;
use App\Models\Finance\GroupCostMovement;
use App\Models\Finance\JournalBatch;
use App\Models\Finance\JournalPosting;
use App\Models\PigManagement;
use App\Models\PigSale;
use App\Models\SalesReturn;
use App\Services\Finance\BiologicalAssets\BiologicalAssetStageResolver;
use App\Services\Finance\BiologicalAssets\BiologicalAssetSubledgerService;
use App\Services\Finance\Costing\GroupCostPoolService;
use App\Finance\Resolvers\FinanceAccountResolver;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Reverses a returned PIG sale (CF-P0-008).
 *
 * A pig sale touches THREE subsystems, and all three must move together:
 *
 *   1. the group cost pool  — GroupCostPoolService::recordSaleCogs()
 *   2. the general ledger   — Dr COGS(5110) / Cr live-pig asset
 *   3. the biological-asset subledger — recordExit()
 *
 * and it soft-deletes the animal. Reversing any subset leaves the herd's
 * carrying value, the cost pool and the GL disagreeing — which is why the
 * original refund service handed all of this to finance as a note rather than
 * attempting part of it.
 *
 * WHOLE ANIMALS ONLY
 * ------------------
 * A pig is indivisible, so a pig return is always the full sale. The proportion
 * machinery that feed returns use does not apply, and RecordSalesReturnService
 * fixes the quantity at 1 for this reason.
 *
 * THE RESTOCK DECISION IS A REAL BUSINESS QUESTION
 * ------------------------------------------------
 * "Refunded" does not always mean the animal came back:
 *
 *   restock = true   the pig returns to the herd. Cost goes back to the pool and
 *                    the subledger, COGS is credited, the pig record is restored.
 *
 *   restock = false  the customer is refunded but the animal does not return —
 *                    it died in transit, or was kept. The cost STAYS consumed:
 *                    no pool entry, no subledger entry, no COGS reversal, and the
 *                    pig stays sold. Only revenue is refunded.
 *
 * Treating those identically would either resurrect an animal that no longer
 * exists or write off cost twice. The code cannot infer which happened, so it is
 * carried on the return as an explicit decision.
 */
class PigSaleReturnCogsSubscriber extends Subscriber
{
    public function __construct(
        protected FinanceAccountResolver $accountResolver,
        protected BiologicalAssetStageResolver $stageResolver,
        protected BiologicalAssetSubledgerService $bioSubledger,
        protected GroupCostPoolService $poolService,
    ) {
    }

    protected function eventType(): string
    {
        return SalesReturnRecorded::TYPE;
    }

    protected function buildDraft(DomainEvent $event): ?JournalBatchDraft
    {
        $return = SalesReturn::find($event->source_id);

        if (! $return) {
            Log::error("PigSaleReturnCogsSubscriber: event #{$event->id} references missing SalesReturn {$event->source_id}");

            return null;
        }

        // One event serves both feed and pig returns; skip anything not a pig.
        if ($return->returnable_type !== PigSale::class) {
            return null;
        }

        // The animal did not come back — cost stays consumed. See the class
        // docblock. Revenue is reversed elsewhere.
        if (! $return->restock) {
            return null;
        }

        // Already reversed. A replayed event must not credit COGS twice or add a
        // second animal to the pool.
        if ($return->cogs_reversal_batch_id !== null) {
            return null;
        }

        $sale = PigSale::find($return->returnable_id);

        if (! $sale) {
            $this->markReturnFailed($return, 'pig_sale_missing');

            return null;
        }

        if ($sale->cogs_journal_batch_id === null || $sale->cogs_posting_status !== 'posted') {
            // The sale's own COGS has not posted yet — a return can legitimately
            // be raised while the outbox is still draining. Retryable.
            $this->markReturnFailed($return, 'original_cogs_not_posted');

            return null;
        }

        $groupId = (int) ($sale->cogs_group_id ?? 0);

        if ($groupId <= 0) {
            $this->markReturnFailed($return, 'sale_has_no_costing_group');

            return null;
        }

        // The cost the sale actually charged, and the weight it removed. Read
        // from the SALE, never recomputed: the group's per-kg basis has moved
        // since, so recomputing would return a different amount than was taken
        // and leave the pool permanently out by the difference.
        $cogsAmount = (string) $sale->cogs_amount;
        $pigWeight  = (string) ($sale->cogs_pig_weight_kg ?? '0');

        if (bccomp($cogsAmount, '0', 4) <= 0) {
            $this->markReturnFailed($return, 'original_cogs_amount_is_zero');

            return null;
        }

        // --- 1. Return the cost to the group pool ---------------------------
        try {
            $poolMovement = $this->poolService->recordSalesReturn(
                groupId:       $groupId,
                pigWeight:     $pigWeight,
                returnedValue: $cogsAmount,
                salesReturnId: (int) $return->id,
                occurredAt:    CarbonImmutable::instance($event->occurred_at),
                actorUserId:   $event->dispatched_by_user_id,
                reference:     'Sales Return #' . $return->id,
                notes:         'Return of Pig Sale #' . $sale->id,
            );
        } catch (\App\Services\Finance\Costing\DuplicateGroupCostMovementException) {
            // A previous attempt already returned the cost. Continue so the GL
            // and subledger legs can catch up — this is the partial-failure
            // retry path, not an error.
            $poolMovement = GroupCostMovement::query()
                ->where('source_type', GroupCostMovement::SOURCE_SALES_RETURN)
                ->where('source_id', $return->id)
                ->first();
        } catch (\Throwable $e) {
            $this->markReturnFailed($return, 'pool_return_failed: ' . $e->getMessage());

            return null;
        }

        $return->forceFill(['cogs_reversed_amount' => $cogsAmount])->save();

        // --- 2. The GL reversal --------------------------------------------
        $cogsAcct    = (int) $this->accountResolver->byCode('5110')->id;
        $livePigAcct = (int) $this->stageResolver->accountForGroup($groupId)->id;

        $pig = PigManagement::withTrashed()->find($sale->pig_id);

        $description = sprintf(
            'COGS reversal — sales return #%d (pig sale #%d, %s)',
            $return->id,
            $sale->id,
            $pig?->code ?? 'pig#' . $sale->pig_id,
        );

        $dims = [
            'source_type' => SalesReturn::class,
            'source_id'   => $return->id,
            'reference'   => 'Sales Return #' . $return->id,
        ];

        // Exact mirror of the sale: Dr live-pig asset / Cr COGS.
        $journal = new JournalDraft(
            journalType:  'pig_sale_cogs',
            lines: [
                JournalLineDraft::debit($livePigAcct, $cogsAmount, $description, $dims),
                JournalLineDraft::credit($cogsAcct, $cogsAmount, $description, $dims),
            ],
            reference:    'Sales Return #' . $return->id,
            description:  $description,
            currencyCode: 'GHS',
            exchangeRate: '1',
            sourceType:   SalesReturn::class,
            sourceId:     (int) $return->id,
        );

        return new JournalBatchDraft(
            batchType:      'auto_sales_return_cogs',
            // The RETURN's date. A return is a new transaction, not a
            // retroactive edit of the sale's period.
            postingDate:    CarbonImmutable::instance($event->occurred_at),
            journals:       [$journal],
            description:    $description,
            sourceModule:   'sales',
            sourceType:     SalesReturn::class,
            sourceId:       (int) $return->id,
            domainEventId:  (int) $event->id,
            idempotencyKey: JournalPosting::buildIdempotencyKey(
                $event->event_type,
                SalesReturn::class,
                (int) $return->id,
                JournalPosting::PURPOSE_SALES_RETURN_COGS,
            ),
            postingPurpose:  JournalPosting::PURPOSE_SALES_RETURN_COGS,
            subscriberClass: static::class,
        );
    }

    /**
     * Subledger entry and pig restoration, once the GL leg is committed.
     *
     * Deliberately after the posting, mirroring PigSaleCogsSubscriber: the
     * subledger transfer records the journal batch it corresponds to, which is
     * only known once the batch exists.
     */
    protected function onPostedSuccessfully(DomainEvent $event, JournalBatch $batch): void
    {
        $return = SalesReturn::find($event->source_id);

        if (! $return || $return->returnable_type !== PigSale::class) {
            return;
        }

        DB::transaction(function () use ($return, $event, $batch) {
            $return->forceFill([
                'cogs_reversal_batch_id' => $batch->id,
                'posting_error'          => null,
            ])->save();

            GroupCostMovement::query()
                ->where('source_type', GroupCostMovement::SOURCE_SALES_RETURN)
                ->where('source_id', $return->id)
                ->whereNull('journal_batch_id')
                ->update(['journal_batch_id' => $batch->id]);

            $sale = PigSale::find($return->returnable_id);

            if (! $sale) {
                return;
            }

            // --- 3. Biological-asset subledger ------------------------------
            //
            // Guarded on the domain event, matching how the sale side guards its
            // exit, so a replay cannot add the animal's value twice.
            $alreadyEntered = BiologicalAssetStageTransfer::query()
                ->where('source_domain_event_id', $event->id)
                ->where('reason', BiologicalAssetStageTransfer::REASON_SALES_RETURN)
                ->exists();

            $groupId = (int) ($sale->cogs_group_id ?? 0);

            if (! $alreadyEntered && $groupId > 0) {
                $transfer = $this->bioSubledger->recordEntry(
                    groupId:             $groupId,
                    amount:              (string) $sale->cogs_amount,
                    pigCountDelta:       1,
                    weightDelta:         (string) ($sale->cogs_pig_weight_kg ?? '0'),
                    reason:              BiologicalAssetStageTransfer::REASON_SALES_RETURN,
                    journalBatchId:      $batch->id,
                    sourceDomainEventId: (int) $event->id,
                    occurredAt:          CarbonImmutable::instance($event->occurred_at),
                );

                $return->forceFill([
                    'biological_transfer_id' => $transfer->id ?? null,
                ])->save();
            }

            // --- 4. Bring the animal back -----------------------------------
            $this->restorePig($return, $sale);

            $return->forceFill([
                'status'    => SalesReturn::STATUS_POSTED,
                'posted_at' => now(),
            ])->save();
        });
    }

    /**
     * Undo the soft delete the sale applied to the pig.
     *
     * RecordPigSaleService calls $pig->delete() when recording a sale, so the
     * animal disappears from every ordinary query. A return that reverses the
     * accounting but leaves the pig deleted produces a herd whose biological
     * asset value counts an animal that the livestock module says does not
     * exist — the exact GL/operational divergence this work exists to prevent.
     *
     * Only restores a pig that is actually trashed. A pig already restored (by
     * a retry, or manually by staff) is left alone rather than touched again.
     */
    protected function restorePig(SalesReturn $return, PigSale $sale): void
    {
        $pig = PigManagement::withTrashed()->find($sale->pig_id);

        if (! $pig) {
            Log::warning('sales return: pig record no longer exists, cannot restore', [
                'sales_return_id' => $return->id,
                'pig_sale_id'     => $sale->id,
                'pig_id'          => $sale->pig_id,
            ]);

            return;
        }

        if ($pig->trashed()) {
            $pig->restore();
        }

        $return->forceFill(['pig_restored' => true])->save();
    }

    /**
     * Named markReturnFailed() rather than fail(): Subscriber extends a queued
     * job which already exposes a PUBLIC fail() from InteractsWithQueue, so a
     * protected fail() here is a fatal access-level conflict.
     */
    protected function markReturnFailed(SalesReturn $return, string $reason): void
    {
        $return->forceFill([
            'status'        => SalesReturn::STATUS_FAILED,
            'posting_error' => $reason,
        ])->save();

        Log::warning('pig sales return could not proceed', [
            'sales_return_id' => $return->id,
            'reason'          => $reason,
        ]);
    }
}
