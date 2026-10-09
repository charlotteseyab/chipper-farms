<?php

namespace App\Services\Feed;

use App\Domain\Events\Costing\FeedIssued;
use App\Domain\Events\Feed\FeedBatchProduced;
use App\Models\DomainEvent;
use App\Models\FeedBatch;
use App\Models\FeedIssue;
use App\Models\FeedProductionPlan;
use App\Models\Finance\BiologicalAssetStageTransfer;
use App\Models\Finance\InventoryBalance;
use App\Models\Finance\InventoryMovement;
use App\Models\Finance\JournalBatch;
use App\Models\Organization;
use App\Models\RawMaterial;
use App\Models\User;
use App\Services\Finance\BiologicalAssets\BiologicalAssetSubledgerService;
use App\Services\Finance\Costing\GroupCostPoolService;
use App\Services\Finance\Inventory\InventoryService;
use App\Services\Finance\JournalReversalService;
use App\Support\CurrentOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Corrects feed mistakes without deleting history. Every reversal undoes the
 * original stock movements exactly, takes the cost back out of where it went,
 * and reverses the GL entry — or, when the GL entry has not been made yet,
 * cancels the pending posting so it never is.
 */
class FeedCorrectionService
{
    public function __construct(
        protected CurrentOrganization $currentOrganization,
        protected InventoryService $inventory,
        protected GroupCostPoolService $pools,
        protected BiologicalAssetSubledgerService $bioSubledger,
        protected JournalReversalService $journalReversal,
    ) {
    }

    public function reverseIssue(User $actor, FeedIssue $issue, string $reason): FeedIssue
    {
        $orgId = $this->authorize($actor, 'manage_feed_issues', (int) $issue->organization_id);
        $reason = $this->reason($reason);

        return DB::transaction(function () use ($actor, $issue, $reason, $orgId) {
            Organization::whereKey($orgId)->lockForUpdate()->firstOrFail();
            $issue = FeedIssue::whereKey($issue->id)->lockForUpdate()->firstOrFail();
            if ($issue->isReversed()) {
                throw new \DomainException('This feeding has already been reversed.');
            }

            $glBatch = $this->settlePosting(FeedIssued::TYPE, FeedIssue::class, (int) $issue->id, $issue->journal_batch_id, $reason);
            $now = CarbonImmutable::now();
            $label = sprintf('Reversal of feeding #%d', $issue->id);

            $this->inventory->recordReversal(
                original:    $this->movement(InventoryMovement::SOURCE_FEED_ISSUE, (int) $issue->id),
                sourceType:  InventoryMovement::SOURCE_FEED_ISSUE_REVERSAL,
                sourceId:    (int) $issue->id,
                occurredAt:  $now,
                actorUserId: $actor->id,
                reference:   $label,
                notes:       $reason,
            );

            try {
                $this->pools->recordFeedIssueReversal(
                    groupId:     (int) $issue->group_id,
                    totalCost:   (string) $issue->total_cost,
                    feedIssueId: (int) $issue->id,
                    occurredAt:  $now,
                    actorUserId: $actor->id,
                    reference:   $label,
                    notes:       $reason,
                );
            } catch (\App\Services\Finance\Costing\InsufficientPoolException) {
                throw new \DomainException('This feeding\'s cost has already been passed on to pigs that were sold or died, so it can no longer be reversed. Ask finance to correct it.');
            }

            $reversal = $glBatch ? $this->journalReversal->reverse($glBatch, $actor, $reason) : null;
            if ($reversal) {
                $this->bioSubledger->recordExit(
                    groupId:        (int) $issue->group_id,
                    amount:         (string) $issue->total_cost,
                    reason:         BiologicalAssetStageTransfer::REASON_FEED_ISSUE_REVERSAL,
                    journalBatchId: (int) $reversal->id,
                    occurredAt:     $now,
                    stageOverride:  $issue->feeding_stage ? FeedStageResolver::bioStage($issue->feeding_stage) : null,
                );
            }

            $issue->update([
                'reversed_at' => $now, 'reversed_by' => $actor->id, 'reversal_reason' => $reason,
                'reversal_journal_batch_id' => $reversal?->id, 'posting_status' => FeedIssue::POSTING_REVERSED,
            ]);
            app(FeedConfigurationService::class)->audit($actor, 'feed_issue_reversed', $issue, ['quantity_kg' => (float) $issue->quantity_kg], ['reversed' => true], $reason);

            return $issue->fresh();
        }, 3);
    }

    public function reverseBatch(User $actor, FeedBatch $batch, string $reason): FeedBatch
    {
        $orgId = $this->authorize($actor, 'manage_feed_production', (int) $batch->organization_id);
        $reason = $this->reason($reason);

        return DB::transaction(function () use ($actor, $batch, $reason, $orgId) {
            Organization::whereKey($orgId)->lockForUpdate()->firstOrFail();
            $batch = FeedBatch::with(['items', 'feedType'])->whereKey($batch->id)->lockForUpdate()->firstOrFail();
            if ($batch->isReversed()) {
                throw new \DomainException('This batch has already been reversed.');
            }

            // The finished feed must be untouched: nothing fed, sold, counted
            // or mixed for this feed since. Undoing it then restores stock and
            // average cost exactly as they were before the batch.
            $produced = $this->movement(InventoryMovement::SOURCE_FEED_BATCH, (int) $batch->id);
            $lastMovementId = InventoryBalance::where('organization_id', $orgId)
                ->where('item_kind', InventoryMovement::ITEM_KIND_FINISHED_FEED)
                ->where('item_id', $batch->feed_type_id)
                ->lockForUpdate()
                ->value('last_movement_id');
            if ((int) $lastMovementId !== (int) $produced->id) {
                throw new \DomainException("{$batch->feedType?->name} from this batch has already been fed, sold, counted or added to by a later batch, so the batch can no longer be reversed. Use a stock count to correct the quantities instead.");
            }

            $glBatch = $this->settlePosting(FeedBatchProduced::TYPE, FeedBatch::class, (int) $batch->id, $batch->journal_batch_id, $reason);
            $now = CarbonImmutable::now();
            $label = sprintf('Reversal of feed batch #%d', $batch->id);

            $this->inventory->recordReversal($produced, InventoryMovement::SOURCE_FEED_BATCH_REVERSAL, (int) $batch->id, $now, $actor->id, $label, $reason);
            foreach ($batch->items as $item) {
                $this->inventory->recordReversal(
                    $this->movement(InventoryMovement::SOURCE_FEED_BATCH_ITEM, (int) $item->id),
                    InventoryMovement::SOURCE_FEED_BATCH_ITEM_REVERSAL, (int) $item->id, $now, $actor->id, $label, $reason,
                );
                RawMaterial::whereKey($item->raw_material_id)->first()?->stockLogs()->create([
                    'type'            => 'usage_reversal',
                    'quantity'        => (float) $item->quantity_used,
                    'unit_cost'       => (float) $item->unit_cost,
                    'reference'       => $label,
                    'notes'           => $reason,
                    'performed_by'    => $actor->id,
                    'organization_id' => $orgId,
                    'posting_status'  => 'skipped', // the batch's GL reversal covers it
                    'is_posted'       => false,
                ]);
            }

            $reversal = $glBatch ? $this->journalReversal->reverse($glBatch, $actor, $reason) : null;

            // Free the batch's confirmation key so its plan can be confirmed again.
            $batch->update([
                'reversed_at' => $now, 'reversed_by' => $actor->id, 'reversal_reason' => $reason,
                'reversal_journal_batch_id' => $reversal?->id, 'posting_status' => 'reversed', 'submission_key' => null,
            ]);
            FeedProductionPlan::where('organization_id', $orgId)->where('feed_batch_id', $batch->id)
                ->update(['status' => 'planned', 'feed_batch_id' => null]);
            app(FeedConfigurationService::class)->audit($actor, 'feed_batch_reversed', $batch, ['batch_size_kg' => (float) $batch->batch_size_kg], ['reversed' => true], $reason);

            return $batch->fresh();
        }, 3);
    }

    public function cancelPlan(User $actor, FeedProductionPlan $plan, string $reason): FeedProductionPlan
    {
        $orgId = $this->authorize($actor, 'manage_feed_production', (int) $plan->organization_id);
        $reason = $this->reason($reason);

        return DB::transaction(function () use ($actor, $plan, $reason, $orgId) {
            Organization::whereKey($orgId)->lockForUpdate()->firstOrFail();
            $plan = FeedProductionPlan::whereKey($plan->id)->lockForUpdate()->firstOrFail();
            if ($plan->status !== 'planned') {
                throw new \DomainException($plan->status === 'cancelled'
                    ? 'This plan is already cancelled.'
                    : 'This plan has been produced. Reverse its batch instead.');
            }
            $plan->update(['status' => 'cancelled', 'cancelled_at' => now(), 'cancelled_by' => $actor->id, 'cancellation_reason' => $reason]);
            app(FeedConfigurationService::class)->audit($actor, 'feed_production_plan_cancelled', $plan, ['status' => 'planned'], ['status' => 'cancelled'], $reason);

            return $plan;
        }, 3);
    }

    /**
     * Returns the posted GL batch to reverse, or null when there is none.
     * A posting not yet made (or dead-lettered) is cancelled so it never runs;
     * one in flight cannot be settled safely, so the user retries shortly.
     */
    private function settlePosting(string $eventType, string $sourceType, int $sourceId, ?int $journalBatchId, string $reason): ?JournalBatch
    {
        $event = DomainEvent::where('event_type', $eventType)->where('source_type', $sourceType)->where('source_id', $sourceId)
            ->lockForUpdate()->first();

        if ($event && in_array($event->status, [DomainEvent::STATUS_PENDING, DomainEvent::STATUS_FAILED], true)) {
            $cancelled = DomainEvent::whereKey($event->id)
                ->whereIn('status', [DomainEvent::STATUS_PENDING, DomainEvent::STATUS_FAILED])
                ->update(['status' => DomainEvent::STATUS_SKIPPED, 'processed_at' => now(), 'processing_error' => 'Not posted: the source was reversed first. '.$reason]);
            if ($cancelled === 1) {
                return null;
            }
            $event->refresh();
        }
        if ($event && $event->status === DomainEvent::STATUS_PROCESSING) {
            throw new \DomainException('This is being posted to the accounts right now. Try again in a minute.');
        }

        $batch = $journalBatchId ? JournalBatch::find($journalBatchId) : null;
        if (! $batch && $event?->status === DomainEvent::STATUS_PROCESSED) {
            $batch = JournalBatch::where('domain_event_id', $event->id)->first();
        }

        return $batch && $batch->isPosted() && $batch->reversed_by_batch_id === null ? $batch : null;
    }

    private function movement(string $sourceType, int $sourceId): InventoryMovement
    {
        return InventoryMovement::where('source_type', $sourceType)->where('source_id', $sourceId)->firstOr(
            fn () => throw new \DomainException('The stock record for this entry is missing, so it cannot be reversed automatically.')
        );
    }

    private function authorize(User $actor, string $permission, int $organizationId): int
    {
        $orgId = $this->currentOrganization->requireId();
        abort_unless($actor->hasPermission($permission) && (int) $actor->organization_id === $orgId && $organizationId === $orgId, 403);

        return $orgId;
    }

    private function reason(string $reason): string
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 5) {
            throw ValidationException::withMessages(['reason' => 'Say why this is being corrected (at least 5 characters).']);
        }

        return mb_substr($reason, 0, 2000);
    }
}
