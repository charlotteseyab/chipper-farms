<?php

namespace App\Finance\Subscribers\Costing;

use App\Finance\Drafts\JournalBatchDraft;
use App\Finance\Drafts\JournalDraft;
use App\Finance\Drafts\JournalLineDraft;
use App\Finance\Posting\PigCostingPurposes;
use App\Finance\Subscribers\Subscriber;
use App\Models\DomainEvent;
use App\Models\FeedIssue;
use App\Models\Finance\BiologicalAssetStageTransfer;
use App\Models\Finance\GroupCostMovement;
use App\Models\Finance\JournalBatch;
use App\Models\Finance\JournalPosting;
use App\Services\Finance\BiologicalAssets\BiologicalAssetStageResolver;
use App\Services\Finance\BiologicalAssets\BiologicalAssetSubledgerService;
use App\Services\Finance\Inventory\InventoryAccountResolver;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Phase 5 — posts the GL leg of a feed issuance event.
 *
 *   Dr  1230 Live Pig Inventory       total_cost
 *         Cr  1220 Finished Feed Inventory  total_cost
 *
 * Both accounts are sourced via InventoryAccountResolver (Phase 4)
 * for finished feed; the 1230 fallback is hardcoded here for now.
 *
 * Idempotent on JournalPosting key:
 *   "feed.issued:FeedIssue:{id}:feed_issue"
 *
 * Skips when total_cost is zero (e.g. opening WAC of zero). The
 * group_cost_movements row still exists for audit.
 */
class FeedIssuedSubscriber extends Subscriber
{
    public function __construct(
        protected InventoryAccountResolver $inventoryResolver,
        protected BiologicalAssetStageResolver $stageResolver,
        protected BiologicalAssetSubledgerService $bioSubledger,
    ) {
    }

    protected function eventType(): string
    {
        return \App\Domain\Events\Costing\FeedIssued::TYPE;
    }

    protected function buildDraft(DomainEvent $event): ?JournalBatchDraft
    {
        $issueId = (int) $event->source_id;

        /** @var FeedIssue|null $issue */
        $issue = FeedIssue::with('feedType')->find($issueId);
        if (! $issue) {
            Log::warning("FeedIssuedSubscriber: feed_issue #{$issueId} not found.");
            return null;
        }

        $totalCost = (string) $issue->total_cost;
        if (bccomp($totalCost, '0', 4) <= 0) {
            // Zero-cost issuance — group_cost_movement exists for audit,
            // but no GL post needed.
            return null;
        }

        $finishedFeedAcct = $this->inventoryResolver->inventoryAccountForFeedType($issue->feedType);
        $livePigAcct = (int) $this->stageResolver->accountForGroup($issue->group_id)->id;

        $description = sprintf(
            'Feed issued: %s × %s kg → group %s (Feed Issue #%d)',
            $issue->feedType->name,
            number_format((float) $issue->quantity_kg, 4),
            'group#' . $issue->group_id,
            $issue->id,
        );

        $lines = [
            JournalLineDraft::debit(
                accountId:   $livePigAcct,
                amount:      $totalCost,
                description: 'Capitalise feed issuance into Live Pig Inventory',
                dimensions: [
                    'source_type' => FeedIssue::class,
                    'source_id'   => $issue->id,
                    'reference'   => 'Feed Issue #' . $issue->id,
                ],
            ),
            JournalLineDraft::credit(
                accountId:   $finishedFeedAcct,
                amount:      $totalCost,
                description: 'Consumption of finished feed',
                dimensions: [
                    'source_type' => FeedIssue::class,
                    'source_id'   => $issue->id,
                    'reference'   => 'Feed Issue #' . $issue->id,
                ],
            ),
        ];

        $journal = new JournalDraft(
            journalType:  'feed_issue',
            lines:        $lines,
            reference:    'Feed Issue #' . $issue->id,
            description:  $description,
            currencyCode: 'GHS',
            exchangeRate: '1',
            sourceType:   FeedIssue::class,
            sourceId:     $issue->id,
        );

        return new JournalBatchDraft(
            batchType:        'auto_feed_issue',
            postingDate:      CarbonImmutable::instance($issue->issued_at ?? now()),
            journals:         [$journal],
            description:      $description,
            sourceModule:     'feed',
            sourceType:       FeedIssue::class,
            sourceId:         $issue->id,
            domainEventId:    (int) $event->id,
            idempotencyKey:   JournalPosting::buildIdempotencyKey(
                'feed.issued',
                FeedIssue::class,
                $issue->id,
                PigCostingPurposes::FEED_ISSUE,
            ),
            postingPurpose:   PigCostingPurposes::FEED_ISSUE,
            subscriberClass:  static::class,
        );
    }

    protected function onPostedSuccessfully(DomainEvent $event, JournalBatch $batch): void
    {
        $issueId = (int) $event->source_id;

        DB::transaction(function () use ($issueId, $event, $batch) {
            FeedIssue::query()
                ->where('id', $issueId)
                ->update([
                    'posting_status'   => FeedIssue::POSTING_POSTED,
                    'posting_error'    => null,
                    'journal_batch_id' => $batch->id,
                ]);

            GroupCostMovement::query()
                ->where('source_type', GroupCostMovement::SOURCE_FEED_ISSUE)
                ->where('source_id', $issueId)
                ->update(['journal_batch_id' => $batch->id]);

            if (BiologicalAssetStageTransfer::query()
                    ->where('source_domain_event_id', $event->id)
                    ->exists()) {
                return;
            }

            $issue = FeedIssue::query()->find($issueId);
            if (! $issue) {
                return;
            }

            $this->bioSubledger->recordEntry(
                groupId:             (int) $issue->group_id,
                amount:              (string) $issue->total_cost,
                pigCountDelta:       0,
                weightDelta:         '0',
                reason:              BiologicalAssetStageTransfer::REASON_FEED_ISSUE,
                journalBatchId:      $batch->id,
                sourceDomainEventId: $event->id,
                occurredAt:          $issue->issued_at ? CarbonImmutable::instance($issue->issued_at) : null,
            );
        });
    }
}
