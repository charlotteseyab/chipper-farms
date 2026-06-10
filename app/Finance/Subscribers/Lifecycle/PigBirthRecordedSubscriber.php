<?php

namespace App\Finance\Subscribers\Lifecycle;

use App\Finance\Drafts\JournalBatchDraft;
use App\Finance\Subscribers\Subscriber;
use App\Models\DomainEvent;
use App\Models\Finance\BiologicalAssetStageTransfer;
use App\Services\Finance\BiologicalAssets\BiologicalAssetSubledgerService;
use Carbon\CarbonImmutable;

class PigBirthRecordedSubscriber extends Subscriber
{
    public function __construct(
        protected BiologicalAssetSubledgerService $bioSubledger,
    ) {
    }

    protected function eventType(): string
    {
        return \App\Domain\Events\Lifecycle\PigBirthRecorded::TYPE;
    }

    public function handle(int $domainEventId): void
    {
        $event = DomainEvent::find($domainEventId);
        if (! $event || $event->event_type !== $this->eventType()) {
            return;
        }

        if (BiologicalAssetStageTransfer::query()->where('source_domain_event_id', $event->id)->exists()) {
            $this->markEvent($event, DomainEvent::STATUS_PROCESSED);
            return;
        }

        $groupId = (int) ($event->payload['group_id'] ?? 0);
        $liveBirths = max(0, (int) ($event->payload['live_births'] ?? 0));
        if ($groupId <= 0 || $liveBirths <= 0) {
            $this->markEvent($event, DomainEvent::STATUS_SKIPPED, 'No group or live births for birth finance entry');
            return;
        }

        $averageWeight = (float) ($event->payload['average_birth_weight_kg'] ?? 0);
        $totalWeight = $averageWeight > 0
            ? number_format($averageWeight * $liveBirths, 4, '.', '')
            : '0';

        $this->bioSubledger->recordEntry(
            groupId: $groupId,
            amount: '0',
            pigCountDelta: $liveBirths,
            weightDelta: $totalWeight,
            reason: BiologicalAssetStageTransfer::REASON_BIRTH,
            journalBatchId: null,
            sourceDomainEventId: $event->id,
            occurredAt: CarbonImmutable::parse($event->occurred_at),
            stageOverride: 'piglet',
        );

        $this->markEvent($event, DomainEvent::STATUS_PROCESSED);
    }

    protected function buildDraft(DomainEvent $event): ?JournalBatchDraft
    {
        return null;
    }
}
