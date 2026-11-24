<?php

namespace App\Finance\Subscribers\Lifecycle;

use App\Finance\Drafts\JournalBatchDraft;
use App\Finance\Drafts\JournalDraft;
use App\Finance\Drafts\JournalLineDraft;
use App\Finance\Resolvers\FinanceAccountResolver;
use App\Finance\Subscribers\Subscriber;
use App\Models\DomainEvent;
use App\Models\Finance\BiologicalAssetStageTransfer;
use App\Models\Finance\GroupCostMovement;
use App\Models\Finance\JournalBatch;
use App\Models\Finance\JournalPosting;
use App\Models\PigManagement;
use App\Services\Finance\BiologicalAssets\BiologicalAssetStageResolver;
use App\Services\Finance\BiologicalAssets\BiologicalAssetSubledgerService;
use App\Services\Finance\Costing\DuplicateGroupCostMovementException;
use App\Services\Finance\Costing\GroupCostPoolService;
use Carbon\CarbonImmutable;

class PigAcquiredSubscriber extends Subscriber
{
    public function __construct(
        protected FinanceAccountResolver $accountResolver,
        protected BiologicalAssetStageResolver $stageResolver,
        protected BiologicalAssetSubledgerService $bioSubledger,
        protected GroupCostPoolService $poolService,
    ) {
    }

    /**
     * The pig the event is about: its payload's pig_id. The event's source is
     * normally that pig, but purchased-stock events were once sourced from
     * the intake, whose id is not a pig's.
     */
    protected function pigId(DomainEvent $event): int
    {
        return (int) ($event->payload['pig_id'] ?? $event->source_id);
    }

    protected function eventType(): string
    {
        return \App\Domain\Events\Lifecycle\PigAcquired::TYPE;
    }

    protected function buildDraft(DomainEvent $event): ?JournalBatchDraft
    {
        $pig = PigManagement::withTrashed()->find($this->pigId($event));
        if (! $pig) {
            return null;
        }

        $amount = (string) (float) ($event->payload['acquisition_cost'] ?? $pig->acquisition_cost ?? 0);
        if (bccomp($amount, '0', 4) <= 0) {
            return null;
        }

        $assetAccount = $this->stageResolver->accountForStage(
            $this->stageResolver->stageForPig($pig)
        );
        // Bought through a purchased-stock intake: owed to the supplier, not
        // owner's capital.
        $purchased = $event->source_module === 'purchase_stock_intake';
        $offsetAccount = $this->accountResolver->byCode($purchased
            ? (string) config('biological_assets.purchase_offset_account_code', '2050')
            : (string) config('biological_assets.acquisition_offset_account_code', '3010'));

        $description = sprintf(
            'Pig acquisition: %s',
            $pig->code ?? 'pig#' . $pig->id,
        );

        $journal = new JournalDraft(
            journalType: 'pig_acquisition',
            lines: [
                JournalLineDraft::debit(
                    accountId: $assetAccount->id,
                    amount: $amount,
                    description: $description,
                    dimensions: [
                        'source_type' => PigManagement::class,
                        'source_id' => $pig->id,
                        'reference' => $pig->code,
                    ],
                ),
                JournalLineDraft::credit(
                    accountId: $offsetAccount->id,
                    amount: $amount,
                    description: $purchased ? 'Purchased stock received, supplier bill to follow' : 'Acquisition funding / opening livestock value',
                    dimensions: [
                        'source_type' => PigManagement::class,
                        'source_id' => $pig->id,
                        'reference' => $pig->code,
                    ],
                ),
            ],
            reference: $pig->code,
            description: $description,
            currencyCode: 'GHS',
            exchangeRate: '1',
            sourceType: PigManagement::class,
            sourceId: $pig->id,
        );

        return new JournalBatchDraft(
            batchType: 'auto_pig_acquisition',
            postingDate: CarbonImmutable::parse($event->occurred_at),
            journals: [$journal],
            description: $description,
            sourceModule: 'lifecycle',
            sourceType: PigManagement::class,
            sourceId: $pig->id,
            domainEventId: $event->id,
            idempotencyKey: JournalPosting::buildIdempotencyKey(
                $event->event_type,
                PigManagement::class,
                $pig->id,
                'pig_acquisition',
            ),
            postingPurpose: 'pig_acquisition',
            subscriberClass: static::class,
        );
    }

    protected function onPostedSuccessfully(DomainEvent $event, JournalBatch $batch): void
    {
        $pig = PigManagement::withTrashed()->find($this->pigId($event));
        if (! $pig) {
            return;
        }

        $pig->update(['acquisition_journal_batch_id' => $batch->id]);

        if (! $pig->group_id) {
            return;
        }

        $amount = (string) ($pig->acquisition_cost ?? $event->payload['acquisition_cost'] ?? '0');
        $weight = (string) ($pig->current_weight_kg_cache ?? $event->payload['weight_kg'] ?? '0');

        try {
            $movement = $this->poolService->recordAcquisition(
                groupId: (int) $pig->group_id,
                pigId: (int) $pig->id,
                acquisitionCost: $amount,
                pigWeight: $weight,
                occurredAt: CarbonImmutable::parse($event->occurred_at),
                actorUserId: $event->dispatched_by_user_id,
                reference: $pig->code,
                notes: $event->payload['acquisition_source'] ?? null,
            );
        } catch (DuplicateGroupCostMovementException) {
            $movement = GroupCostMovement::query()
                ->where('source_type', GroupCostMovement::SOURCE_PIG_ACQUISITION)
                ->where('source_id', $pig->id)
                ->first();
        }

        if ($movement) {
            $movement->update(['journal_batch_id' => $batch->id]);
        }

        if (BiologicalAssetStageTransfer::query()
            ->where('source_domain_event_id', $event->id)
            ->exists()) {
            return;
        }

        $this->bioSubledger->recordEntry(
            groupId: (int) $pig->group_id,
            amount: (string) ($pig->acquisition_cost ?? $event->payload['acquisition_cost'] ?? '0'),
            pigCountDelta: 1,
            weightDelta: (string) ($pig->current_weight_kg_cache ?? $event->payload['weight_kg'] ?? '0'),
            reason: BiologicalAssetStageTransfer::REASON_ACQUISITION,
            journalBatchId: $batch->id,
            sourceDomainEventId: $event->id,
            occurredAt: CarbonImmutable::parse($event->occurred_at),
        );
    }
}
