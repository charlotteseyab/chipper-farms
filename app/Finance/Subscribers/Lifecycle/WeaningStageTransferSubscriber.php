<?php

namespace App\Finance\Subscribers\Lifecycle;

use App\Finance\Drafts\JournalBatchDraft;
use App\Finance\Drafts\JournalDraft;
use App\Finance\Drafts\JournalLineDraft;
use App\Finance\Exceptions\DuplicatePostingException;
use App\Finance\Posting\PostingEngine;
use App\Finance\Subscribers\Subscriber;
use App\Models\DomainEvent;
use App\Models\Finance\BiologicalAssetStageBalance;
use App\Models\Finance\BiologicalAssetStageTransfer;
use App\Models\Finance\JournalBatch;
use App\Models\Finance\JournalPosting;
use App\Models\Farrowing;
use App\Models\Weaning;
use App\Services\Finance\BiologicalAssets\BiologicalAssetStageResolver;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class WeaningStageTransferSubscriber extends Subscriber
{
    public function __construct(
        protected BiologicalAssetStageResolver $stageResolver,
        protected PostingEngine $postingEngine,
    ) {
    }

    protected function eventType(): string
    {
        return \App\Domain\Events\Lifecycle\LitterWeaned::TYPE;
    }

    public function handle(int $domainEventId): void
    {
        $event = DomainEvent::find($domainEventId);
        if (! $event || ! in_array($event->event_type, [
            \App\Domain\Events\Lifecycle\LitterWeaned::TYPE,
            \App\Domain\Events\Lifecycle\PigWeaned::TYPE,
        ], true)) {
            return;
        }

        if (BiologicalAssetStageTransfer::query()->where('source_domain_event_id', $event->id)->exists()) {
            $this->markEvent($event, DomainEvent::STATUS_PROCESSED);
            return;
        }

        $context = $this->resolveContext($event);
        if ($context === null) {
            $this->markEvent($event, DomainEvent::STATUS_SKIPPED, 'No group or pig count available for weaning transfer');
            return;
        }

        $batch = null;
        if (bccomp($context['amount'], '0', 4) > 0) {
            try {
                $batch = $this->postingEngine->post($this->draft($event, $context), $event->dispatchedBy);
            } catch (DuplicatePostingException $e) {
                $batch = JournalBatch::find($e->existingBatchId);
            }
        }

        DB::transaction(function () use ($event, $context, $batch) {
            $from = $this->stageBalance($event, $context['group_id'], 'piglet', createIfMissing: true);
            $to = $this->stageBalance($event, $context['group_id'], 'weaner', createIfMissing: true);

            $from->carrying_value = $this->maxZero(bcsub((string) $from->carrying_value, $context['amount'], 4));
            $from->pig_count = max(0, (int) $from->pig_count - $context['pig_count']);
            $from->weight_kg = $this->maxZero(bcsub((string) $from->weight_kg, $context['weight_kg'], 4));
            $from->last_movement_at = CarbonImmutable::parse($event->occurred_at);
            $from->save();

            $to->carrying_value = bcadd((string) $to->carrying_value, $context['amount'], 4);
            $to->pig_count = (int) $to->pig_count + $context['pig_count'];
            $to->weight_kg = bcadd((string) $to->weight_kg, $context['weight_kg'], 4);
            $to->last_movement_at = CarbonImmutable::parse($event->occurred_at);
            $to->save();

            BiologicalAssetStageTransfer::create([
                'organization_id' => $event->organization_id,
                'group_id' => $context['group_id'],
                'from_stage_key' => 'piglet',
                'to_stage_key' => 'weaner',
                'amount' => $context['amount'],
                'pig_count' => $context['pig_count'],
                'weight_kg' => $context['weight_kg'],
                'reason' => BiologicalAssetStageTransfer::REASON_WEANING,
                'journal_batch_id' => $batch?->id,
                'source_domain_event_id' => $event->id,
                'occurred_at' => CarbonImmutable::parse($event->occurred_at),
            ]);
        });

        $this->markEvent($event, DomainEvent::STATUS_PROCESSED);
    }

    protected function buildDraft(DomainEvent $event): ?JournalBatchDraft
    {
        return null;
    }

    /**
     * @return array{group_id:int,pig_count:int,weight_kg:string,amount:string,source_type:string,source_id:int}|null
     */
    protected function resolveContext(DomainEvent $event): ?array
    {
        $groupId = (int) ($event->payload['group_id'] ?? 0);
        $pigCount = $event->event_type === \App\Domain\Events\Lifecycle\PigWeaned::TYPE
            ? 1
            : max(0, (int) ($event->payload['surviving_piglets'] ?? 0));

        if ($groupId <= 0 || $pigCount <= 0) {
            return null;
        }

        $weight = '0';
        if ($event->event_type === \App\Domain\Events\Lifecycle\PigWeaned::TYPE) {
            $weight = number_format((float) ($event->payload['weaning_weight_kg'] ?? 0), 4, '.', '');
        } else {
            $avg = (float) ($event->payload['average_birth_weight_kg'] ?? 0);
            $weight = $avg > 0 ? number_format($avg * $pigCount, 4, '.', '') : '0';
        }

        $from = $this->stageBalance($event, $groupId, 'piglet');
        $amount = '0';
        if ($from && (int) $from->pig_count > 0 && bccomp((string) $from->carrying_value, '0', 4) > 0) {
            $perPig = bcdiv((string) $from->carrying_value, (string) max(1, (int) $from->pig_count), 4);
            $amount = bcmul($perPig, (string) $pigCount, 4);
            if (bccomp($amount, (string) $from->carrying_value, 4) > 0) {
                $amount = (string) $from->carrying_value;
            }
        }

        return [
            'group_id' => $groupId,
            'pig_count' => $pigCount,
            'weight_kg' => $weight,
            'amount' => $amount,
            'source_type' => $event->event_type === \App\Domain\Events\Lifecycle\PigWeaned::TYPE
                ? Weaning::class
                : Farrowing::class,
            'source_id' => (int) $event->source_id,
        ];
    }

    /**
     * @param array{group_id:int,pig_count:int,weight_kg:string,amount:string,source_type:string,source_id:int} $context
     */
    protected function draft(DomainEvent $event, array $context): JournalBatchDraft
    {
        $fromAcct = $this->stageResolver->accountForStage('piglet');
        $toAcct = $this->stageResolver->accountForStage('weaner');
        $description = sprintf('Weaning stage transfer for group #%d', $context['group_id']);

        return new JournalBatchDraft(
            batchType: 'auto_weaning_stage_transfer',
            postingDate: CarbonImmutable::parse($event->occurred_at),
            journals: [new JournalDraft(
                journalType: 'bio_stage_transfer',
                lines: [
                    JournalLineDraft::debit(
                        accountId: $toAcct->id,
                        amount: $context['amount'],
                        description: $description,
                        dimensions: ['group_id' => $context['group_id']],
                    ),
                    JournalLineDraft::credit(
                        accountId: $fromAcct->id,
                        amount: $context['amount'],
                        description: $description,
                        dimensions: ['group_id' => $context['group_id']],
                    ),
                ],
                reference: 'Weaning #' . $event->source_id,
                description: $description,
                currencyCode: 'GHS',
                exchangeRate: '1',
                sourceType: $context['source_type'],
                sourceId: $context['source_id'],
            )],
            description: $description,
            sourceModule: 'lifecycle',
            sourceType: $context['source_type'],
            sourceId: $context['source_id'],
            domainEventId: $event->id,
            idempotencyKey: JournalPosting::buildIdempotencyKey(
                $event->event_type,
                $context['source_type'],
                $context['source_id'],
                'weaning_stage_transfer',
            ),
            postingPurpose: 'weaning_stage_transfer',
            subscriberClass: static::class,
        );
    }

    protected function stageBalance(
        DomainEvent $event,
        int $groupId,
        string $stage,
        bool $createIfMissing = false,
    ): ?BiologicalAssetStageBalance {
        $query = BiologicalAssetStageBalance::query()
            ->where('organization_id', $event->organization_id)
            ->where('group_id', $groupId)
            ->where('stage_key', $stage);

        $row = $query->lockForUpdate()->first();
        if ($row || ! $createIfMissing) {
            return $row;
        }

        return BiologicalAssetStageBalance::create([
            'organization_id' => $event->organization_id,
            'group_id' => $groupId,
            'stage_key' => $stage,
            'carrying_value' => '0',
            'pig_count' => 0,
            'weight_kg' => '0',
        ]);
    }

    protected function maxZero(string $value): string
    {
        return bccomp($value, '0', 4) < 0 ? '0' : $value;
    }
}
