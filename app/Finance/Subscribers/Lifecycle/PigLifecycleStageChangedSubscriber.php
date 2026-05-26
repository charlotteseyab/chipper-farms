<?php

namespace App\Finance\Subscribers\Lifecycle;

use App\Domain\Events\Lifecycle\PigLifecycleStageChanged;
use App\Finance\Drafts\JournalBatchDraft;
use App\Finance\Drafts\JournalDraft;
use App\Finance\Drafts\JournalLineDraft;
use App\Finance\Subscribers\Subscriber;
use App\Models\DomainEvent;
use App\Models\Finance\BiologicalAssetStageBalance;
use App\Models\Finance\BiologicalAssetStageTransfer;
use App\Models\Finance\JournalBatch;
use App\Models\PigManagement;
use App\Services\Finance\BiologicalAssets\BiologicalAssetStageResolver;
use App\Services\Finance\BiologicalAssets\Concerns\HandlesBioAssetDecimals;
use App\Support\CurrentOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

/**
 * When a pig is promoted/demoted between stock types (standard <-> breeding),
 * its share of the group cost pool should move between biological-asset
 * stage accounts (e.g. a finisher promoted to breeding stock moves cost
 * 1370 -> 1310/1320/1330).
 *
 * This subscriber posts the GL transfer (Dr target-stage / Cr source-stage)
 * for the promoted pig's allocated cost, then updates the stage subledger.
 *
 * The promoted pig's cost basis is approximated as the source-stage pool's
 * per-pig average (carrying_value / pig_count) at the time of promotion —
 * the same averaging principle Phase 5 uses for sales/mortality when a
 * precise per-pig basis isn't tracked.
 *
 * Skips (no-op) when:
 *   - the pig has no group,
 *   - the from/to stages resolve to the same account,
 *   - the source stage holds no cost.
 */
class PigLifecycleStageChangedSubscriber extends Subscriber
{
    use HandlesBioAssetDecimals;

    public function __construct(
        protected CurrentOrganization $currentOrganization,
        protected BiologicalAssetStageResolver $resolver,
    ) {
    }

    protected function eventType(): string
    {
        return PigLifecycleStageChanged::TYPE;
    }

    protected function buildDraft(DomainEvent $event): ?JournalBatchDraft
    {
        $context = $this->resolveContext($event);
        if ($context === null) {
            return null;
        }

        [$pig, $groupId, $fromStage, $toStage, $amount] = array_values($context);

        $fromAcct = $this->resolver->accountForStage($fromStage);
        $toAcct   = $this->resolver->accountForStage($toStage);

        $postAmt = $this->toPostingAmount($amount);
        $occurredAt = CarbonImmutable::parse($event->occurred_at);

        return new JournalBatchDraft(
            batchType:   'auto_bio_promotion_transfer',
            postingDate: $occurredAt,
            journals:    [new JournalDraft(
                journalType: 'bio_stage_transfer',
                lines: [
                    JournalLineDraft::debit(
                        accountId: $toAcct->id,
                        amount: $postAmt,
                        description: "Promotion {$fromStage} → {$toStage} (pig #{$pig->id})",
                        dimensions: ['group_id' => $groupId],
                    ),
                    JournalLineDraft::credit(
                        accountId: $fromAcct->id,
                        amount: $postAmt,
                        description: "Promotion {$fromStage} → {$toStage} (pig #{$pig->id})",
                        dimensions: ['group_id' => $groupId],
                    ),
                ],
            )],
            description:    "Biological asset promotion transfer (pig #{$pig->id})",
            sourceModule:   'finance.biological_assets',
            sourceType:     'pig_promotion',
            sourceId:       $pig->id,
            domainEventId:  $event->id,
            idempotencyKey: sprintf('bio_promotion:%s:%d:%s', $event->organization_id ?? 'null', $event->id, $toStage),
            postingPurpose: 'bio_stage_transfer',
        );
    }

    protected function onPostedSuccessfully(DomainEvent $event, JournalBatch $batch): void
    {
        $context = $this->resolveContext($event);
        if ($context === null) {
            return;
        }
        [$pig, $groupId, $fromStage, $toStage, $amount] = array_values($context);

        // Move one pig's cost from the source stage balance to the target.
        $from = $this->stageBalance($groupId, $fromStage);
        if ($from) {
            $from->carrying_value = $this->maxZero($this->decSub((string) $from->carrying_value, $amount));
            $from->pig_count      = max(0, (int) $from->pig_count - 1);
            $from->last_movement_at = CarbonImmutable::parse($event->occurred_at);
            $from->save();
        }

        $to = $this->stageBalance($groupId, $toStage, createIfMissing: true);
        $to->carrying_value = $this->decAdd((string) $to->carrying_value, $amount);
        $to->pig_count      = (int) $to->pig_count + 1;
        $to->last_movement_at = CarbonImmutable::parse($event->occurred_at);
        $to->save();

        BiologicalAssetStageTransfer::create([
            'organization_id'        => $event->organization_id,
            'group_id'               => $groupId,
            'from_stage_key'         => $fromStage,
            'to_stage_key'           => $toStage,
            'amount'                 => $this->norm($amount, 4),
            'pig_count'              => 1,
            'weight_kg'              => '0',
            'reason'                 => BiologicalAssetStageTransfer::REASON_PROMOTION,
            'journal_batch_id'       => $batch->id,
            'source_domain_event_id' => $event->id,
            'occurred_at'            => CarbonImmutable::parse($event->occurred_at),
        ]);
    }

    /**
     * Resolve [pig, groupId, fromStage, toStage, amount] or null to skip.
     *
     * @return array{pig: PigManagement, group_id: int, from: string, to: string, amount: string}|null
     */
    protected function resolveContext(DomainEvent $event): ?array
    {
        $payload = $event->payload;
        $payload = is_array($payload) ? $payload : json_decode((string) $payload, true);
        $pigId = (int) ($payload['pig_id'] ?? 0);
        if ($pigId <= 0) {
            return null;
        }

        $pig = PigManagement::withTrashed()->find($pigId);
        if (! $pig || ! $pig->group_id) {
            return null;
        }
        $groupId = (int) $pig->group_id;

        // Source stage = where this pig's cohort cost currently sits (the
        // group's prior fattening stage); target = resolved post-change stage.
        $toStage = $this->resolver->stageForPig($pig);

        // Find the source stage: the stage balance row for this group with
        // cost in it that is NOT the target. Prefer the resolver's mapping
        // of the FROM stock type if it was fattening.
        $fromStage = $this->guessFromStage($groupId, $toStage);
        if ($fromStage === null || $fromStage === $toStage) {
            return null;
        }

        // Per-pig average cost basis from the source stage balance.
        $from = $this->stageBalance($groupId, $fromStage);
        if (! $from || $this->decComp((string) $from->carrying_value, '0') <= 0 || (int) $from->pig_count <= 0) {
            return null;
        }
        $amount = bcdiv((string) $from->carrying_value, (string) max(1, (int) $from->pig_count), 4);
        if ($this->decComp($amount, '0') <= 0) {
            return null;
        }

        return ['pig' => $pig, 'group_id' => $groupId, 'from' => $fromStage, 'to' => $toStage, 'amount' => $amount];
    }

    /**
     * Pick the source stage: the group's stage balance with the largest
     * carrying value that is not the target stage. This handles the common
     * case (one active fattening stage) robustly without needing the exact
     * prior stage key.
     */
    protected function guessFromStage(int $groupId, string $excludeStage): ?string
    {
        $organizationId = $this->currentOrganization->id();

        $row = BiologicalAssetStageBalance::query()
            ->when($organizationId !== null,
                fn ($q) => $q->where('organization_id', $organizationId),
                fn ($q) => $q->whereNull('organization_id'))
            ->where('group_id', $groupId)
            ->where('stage_key', '!=', $excludeStage)
            ->where('carrying_value', '>', 0)
            ->orderByDesc('carrying_value')
            ->first();

        return $row?->stage_key;
    }

    protected function stageBalance(int $groupId, string $stage, bool $createIfMissing = false): ?BiologicalAssetStageBalance
    {
        $organizationId = $this->currentOrganization->id();

        $row = BiologicalAssetStageBalance::query()
            ->when($organizationId !== null,
                fn ($q) => $q->where('organization_id', $organizationId),
                fn ($q) => $q->whereNull('organization_id'))
            ->where('group_id', $groupId)
            ->where('stage_key', $stage)
            ->first();

        if ($row || ! $createIfMissing) {
            return $row;
        }

        return BiologicalAssetStageBalance::create([
            'organization_id' => $organizationId,
            'group_id'        => $groupId,
            'stage_key'       => $stage,
            'carrying_value'  => '0',
            'pig_count'       => 0,
            'weight_kg'       => '0',
        ]);
    }

    protected function maxZero(string $v): string
    {
        return $this->decComp($v, '0') < 0 ? '0' : $this->norm($v, 4);
    }
}
