<?php

namespace App\Finance\Subscribers\Lifecycle;

use App\Finance\Drafts\JournalBatchDraft;
use App\Finance\Drafts\JournalDraft;
use App\Finance\Drafts\JournalLineDraft;
use App\Finance\Posting\PigCostingPurposes;
use App\Finance\Resolvers\FinanceAccountResolver;
use App\Finance\Subscribers\Subscriber;
use App\Models\DomainEvent;
use App\Models\Finance\GroupCostMovement;
use App\Models\Finance\JournalBatch;
use App\Models\Finance\JournalPosting;
use App\Models\Finance\PigMortalityWriteoff;
use App\Models\PigDeath;
use App\Services\Deaths\WriteOffPigMortalityService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

/**
 * Phase 5 — posts the GL leg for a pig death.
 *
 *   Dr  5320 Mortality Loss          writeoff_amount
 *         Cr  1230 Live Pig Inventory  writeoff_amount
 *
 * The writeoff_amount is computed by WriteOffPigMortalityService.
 *
 * IMPORTANT: This subscriber ALSO calls the writeoff service if no
 * writeoff row exists yet. The service is idempotent — if the death
 * was recorded but the subscriber crashed before the service ran, a
 * retry will compute + persist the writeoff.
 *
 * Skip conditions (returns null → marked SKIPPED):
 *   - Dead pig has no group_id (memo-only death).
 *   - Group pool is completely empty (no value to write off).
 *   - Computed writeoff_amount is zero (e.g. zero weight at death AND
 *     zero pool weight).
 */
class PigDeathRecordedSubscriber extends Subscriber
{
    public function __construct(
        protected FinanceAccountResolver $accountResolver,
        protected WriteOffPigMortalityService $writeoffService,
    ) {
    }

    protected function eventType(): string
    {
        return \App\Domain\Events\Lifecycle\PigDeathRecorded::TYPE;
    }

    protected function buildDraft(DomainEvent $event): ?JournalBatchDraft
    {
        $deathId = (int) $event->source_id;

        /** @var PigDeath|null $death */
        $death = PigDeath::find($deathId);
        if (! $death) {
            Log::warning("PigDeathRecordedSubscriber: pig_death #{$deathId} not found.");
            return null;
        }

        // Compute (or retrieve existing) writeoff. Service is idempotent.
        $writeoff = $this->writeoffService->execute($death);
        if (! $writeoff) {
            // Service decided no writeoff applies (no group / empty pool / zero weight).
            return null;
        }

        if (bccomp((string) $writeoff->writeoff_amount, '0', 4) <= 0) {
            return null;
        }

        $mortalityAcct = (int) $this->accountResolver->byCode('5320')->id;
        $bioGroupId  = (int) ($writeoff->group_id ?? $pig?->group_id);
        $livePigAcct = (int) $this->stageResolver->accountForGroup($bioGroupId)->id;

        $this->bioSubledger->recordExit(
                      groupId:             $bioGroupId,
                      amount:              (string) $writeoff->writeoff_amount,
                      pigCountDelta:       1,
                      weightDelta:         (string) ($writeoff->pig_weight_at_death_kg ?? '0'),
                      reason:              \App\Models\Finance\BiologicalAssetStageTransfer::REASON_MORTALITY,
                      journalBatchId:      $batch->id,
                      sourceDomainEventId: $domainEventId,
                    );

        $description = sprintf(
            'Mortality loss — pig %s (death #%d), group #%d',
            $event->payload()['pig_code'] ?? "pig#{$death->pig_id}",
            $death->id,
            $writeoff->group_id,
        );

        $lines = [
            JournalLineDraft::debit(
                accountId:   $mortalityAcct,
                amount:      (string) $writeoff->writeoff_amount,
                description: $description,
                dimensions: [
                    'source_type' => PigDeath::class,
                    'source_id'   => $death->id,
                    'reference'   => 'Death #' . $death->id,
                ],
            ),
            JournalLineDraft::credit(
                accountId:   $livePigAcct,
                amount:      (string) $writeoff->writeoff_amount,
                description: $description,
                dimensions: [
                    'source_type' => PigDeath::class,
                    'source_id'   => $death->id,
                    'reference'   => 'Death #' . $death->id,
                ],
            ),
        ];

        $journal = new JournalDraft(
            journalType:  'mortality_writeoff',
            lines:        $lines,
            reference:    'Death #' . $death->id,
            description:  $description,
            currencyCode: 'GHS',
            exchangeRate: '1',
            sourceType:   PigDeath::class,
            sourceId:     $death->id,
        );

        return new JournalBatchDraft(
            batchType:        'auto_mortality_writeoff',
            postingDate:      CarbonImmutable::parse($death->death_date)->endOfDay(),
            journals:         [$journal],
            description:      $description,
            sourceModule:     'lifecycle',
            sourceType:       PigDeath::class,
            sourceId:         $death->id,
            domainEventId:    (int) $event->id,
            idempotencyKey:   JournalPosting::buildIdempotencyKey(
                'pig_death.recorded',
                PigDeath::class,
                $death->id,
                PigCostingPurposes::PIG_MORTALITY_WRITEOFF,
            ),
            postingPurpose:   PigCostingPurposes::PIG_MORTALITY_WRITEOFF,
            subscriberClass:  static::class,
        );
    }

    protected function onPostedSuccessfully(DomainEvent $event, JournalBatch $batch): void
    {
        $deathId = (int) $event->source_id;

        // Forward-link the writeoff row and the underlying cost movement.
        PigMortalityWriteoff::query()
            ->where('pig_death_id', $deathId)
            ->update(['journal_batch_id' => $batch->id]);

        GroupCostMovement::query()
            ->where('source_type', GroupCostMovement::SOURCE_PIG_DEATH)
            ->where('source_id', $deathId)
            ->update(['journal_batch_id' => $batch->id]);

        // Stamp the PigDeath itself for back-compat reporting that reads is_posted.
        PigDeath::query()
            ->where('id', $deathId)
            ->update(['is_posted' => true, 'posted_at' => now()]);
    }
}
