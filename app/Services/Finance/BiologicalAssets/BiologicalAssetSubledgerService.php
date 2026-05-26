<?php

namespace App\Services\Finance\BiologicalAssets;

use App\Finance\Drafts\JournalBatchDraft;
use App\Finance\Drafts\JournalDraft;
use App\Finance\Drafts\JournalLineDraft;
use App\Finance\Posting\PostingEngine;
use App\Models\Finance\BiologicalAssetStageBalance;
use App\Models\Finance\BiologicalAssetStageTransfer;
use App\Models\Group;
use App\Models\User;
use App\Services\Finance\BiologicalAssets\Concerns\HandlesBioAssetDecimals;
use App\Support\CurrentOrganization;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Maintains the per-(group, stage) biological-asset subledger and posts the
 * GL journals that move cost between stage accounts.
 *
 * Phase 5 already maintains each group's TOTAL carrying value (GroupCostPool)
 * and posts the cost INTO live-pig inventory. Phase 7 splits that total
 * across stage accounts (1340–1370 / 1310–1330) and keeps a stage subledger
 * whose per-group sum must equal the GroupCostPool total.
 *
 * Two kinds of operation:
 *
 *   recordEntry(): cost arriving in a group's CURRENT stage (feed issuance,
 *     opening). The caller (repointed Phase 5 subscriber) has already posted
 *     the GL debit to the stage account; this method just updates the stage
 *     balance + writes the transfer audit row (from_stage = null).
 *
 *   recordExit(): cost leaving a group's CURRENT stage (sale COGS,
 *     mortality). GL credit already posted by the caller; update balance +
 *     audit (to_stage = null semantics handled via reason).
 *
 *   transferStage(): MOVE accumulated cost from one stage to another for a
 *     group (cohort ages weaner->grower, or promoted standard->breeding).
 *     This posts its OWN balanced journal: Dr to-stage / Cr from-stage, and
 *     updates both stage balances + writes the audit row. Idempotent via the
 *     posting engine's idempotency key.
 *
 * All writes are atomic. Callers that already run inside a transaction
 * (subscribers) get nested-transaction safety from the DB facade.
 */
class BiologicalAssetSubledgerService
{
    use HandlesBioAssetDecimals;

    public function __construct(
        protected CurrentOrganization $currentOrganization,
        protected BiologicalAssetStageResolver $resolver,
        protected PostingEngine $postingEngine,
    ) {
    }

    /**
     * Record cost ENTERING a group's current stage (no GL posted here — the
     * caller posted the debit to the stage account).
     */
    public function recordEntry(
        int $groupId,
        string $amount,
        int $pigCountDelta = 0,
        string $weightDelta = '0',
        string $reason = BiologicalAssetStageTransfer::REASON_FEED_ISSUE,
        ?int $journalBatchId = null,
        ?int $sourceDomainEventId = null,
        ?CarbonInterface $occurredAt = null,
    ): BiologicalAssetStageBalance {
        $stage = $this->resolver->stageForGroup($groupId);

        return DB::transaction(function () use ($groupId, $stage, $amount, $pigCountDelta, $weightDelta, $reason, $journalBatchId, $sourceDomainEventId, $occurredAt) {
            $balance = $this->lockBalance($groupId, $stage);

            $balance->carrying_value = $this->decAdd((string) $balance->carrying_value, $amount);
            $balance->pig_count      = max(0, (int) $balance->pig_count + $pigCountDelta);
            $balance->weight_kg      = $this->decAdd((string) $balance->weight_kg, $weightDelta);
            $balance->last_movement_at = ($occurredAt ?? CarbonImmutable::now());
            $balance->save();

            $this->writeTransfer(
                groupId: $groupId, from: null, to: $stage, amount: $amount,
                pigCount: $pigCountDelta, weight: $weightDelta, reason: $reason,
                batchId: $journalBatchId, eventId: $sourceDomainEventId, occurredAt: $occurredAt,
            );

            return $balance;
        });
    }

    /**
     * Record cost LEAVING a group's current stage (GL credit already posted
     * by the caller). amount/weight are POSITIVE magnitudes being removed.
     */
    public function recordExit(
        int $groupId,
        string $amount,
        int $pigCountDelta = 0,
        string $weightDelta = '0',
        string $reason = BiologicalAssetStageTransfer::REASON_SALE_COGS,
        ?int $journalBatchId = null,
        ?int $sourceDomainEventId = null,
        ?CarbonInterface $occurredAt = null,
    ): BiologicalAssetStageBalance {
        $stage = $this->resolver->stageForGroup($groupId);

        return DB::transaction(function () use ($groupId, $stage, $amount, $pigCountDelta, $weightDelta, $reason, $journalBatchId, $sourceDomainEventId, $occurredAt) {
            $balance = $this->lockBalance($groupId, $stage);

            // Clamp at zero — never drive a stage balance negative on rounding.
            $newValue = $this->decSub((string) $balance->carrying_value, $amount);
            if ($this->decComp($newValue, '0') < 0) {
                $newValue = '0';
            }
            $newWeight = $this->decSub((string) $balance->weight_kg, $weightDelta);
            if ($this->decComp($newWeight, '0') < 0) {
                $newWeight = '0';
            }

            $balance->carrying_value = $newValue;
            $balance->pig_count      = max(0, (int) $balance->pig_count - abs($pigCountDelta));
            $balance->weight_kg      = $newWeight;
            $balance->last_movement_at = ($occurredAt ?? CarbonImmutable::now());
            $balance->save();

            $this->writeTransfer(
                groupId: $groupId, from: $stage, to: null, amount: $amount,
                pigCount: $pigCountDelta, weight: $weightDelta, reason: $reason,
                batchId: $journalBatchId, eventId: $sourceDomainEventId, occurredAt: $occurredAt,
            );

            return $balance;
        });
    }

    /**
     * Move accumulated cost between two stages for a group, posting the
     * balanced GL transfer (Dr to-stage / Cr from-stage).
     *
     * @return BiologicalAssetStageTransfer|null  null if nothing to move.
     */
    public function transferStage(
        int $groupId,
        string $fromStage,
        string $toStage,
        User $actor,
        string $reason = BiologicalAssetStageTransfer::REASON_STAGE_PROGRESSION,
        ?int $sourceDomainEventId = null,
        ?CarbonInterface $occurredAt = null,
        ?string $amountOverride = null,
        int $pigCount = 0,
        string $weightDelta = '0',
    ): ?BiologicalAssetStageTransfer {
        if ($fromStage === $toStage) {
            return null;
        }

        $occurredAt ??= CarbonImmutable::now();
        $organizationId = $this->currentOrganization->id();

        return DB::transaction(function () use ($groupId, $fromStage, $toStage, $actor, $reason, $sourceDomainEventId, $occurredAt, $amountOverride, $pigCount, $weightDelta, $organizationId) {
            $from = $this->lockBalance($groupId, $fromStage);

            // Move either the full from-stage carrying value or an override.
            $amount = $amountOverride !== null
                ? $this->norm($amountOverride, 4)
                : (string) $from->carrying_value;

            if ($this->decComp($amount, '0', 4) <= 0) {
                return null; // nothing to move
            }
            // Cap at available.
            if ($this->decComp($amount, (string) $from->carrying_value, 4) > 0) {
                $amount = (string) $from->carrying_value;
            }

            $to = $this->lockBalance($groupId, $toStage);

            $fromAcct = $this->resolver->accountForStage($fromStage);
            $toAcct   = $this->resolver->accountForStage($toStage);

            $postAmt = $this->toPostingAmount($amount);

            // Post Dr to-stage / Cr from-stage.
            $draft = new JournalBatchDraft(
                batchType:      'auto_bio_stage_transfer',
                postingDate:    $occurredAt,
                journals:       [new JournalDraft(
                    journalType: 'bio_stage_transfer',
                    lines: [
                        JournalLineDraft::debit(
                            accountId: $toAcct->id,
                            amount: $postAmt,
                            description: "Stage transfer {$fromStage} → {$toStage} (group #{$groupId})",
                            dimensions: ['group_id' => $groupId],
                        ),
                        JournalLineDraft::credit(
                            accountId: $fromAcct->id,
                            amount: $postAmt,
                            description: "Stage transfer {$fromStage} → {$toStage} (group #{$groupId})",
                            dimensions: ['group_id' => $groupId],
                        ),
                    ],
                )],
                description:    "Biological asset stage transfer for group #{$groupId}",
                sourceModule:   'finance.biological_assets',
                sourceType:     'bio_stage_transfer',
                sourceId:       $groupId,
                domainEventId:  $sourceDomainEventId,
                idempotencyKey: sprintf('bio_stage_transfer:%s:%d:%s:%s:%d',
                    $organizationId ?? 'null', $groupId, $fromStage, $toStage, $occurredAt->getTimestamp()),
                postingPurpose: 'bio_stage_transfer',
            );

            $batch = $this->postingEngine->post($draft, $actor);

            // Update balances.
            $from->carrying_value = $this->decSub((string) $from->carrying_value, $amount);
            $from->pig_count      = max(0, (int) $from->pig_count - abs($pigCount));
            $from->weight_kg      = $this->decSub((string) $from->weight_kg, $weightDelta);
            if ($this->decComp((string) $from->weight_kg, '0') < 0) {
                $from->weight_kg = '0';
            }
            $from->last_movement_at = $occurredAt;
            $from->save();

            $to->carrying_value = $this->decAdd((string) $to->carrying_value, $amount);
            $to->pig_count      = (int) $to->pig_count + abs($pigCount);
            $to->weight_kg      = $this->decAdd((string) $to->weight_kg, $weightDelta);
            $to->last_movement_at = $occurredAt;
            $to->save();

            return $this->writeTransfer(
                groupId: $groupId, from: $fromStage, to: $toStage, amount: $amount,
                pigCount: $pigCount, weight: $weightDelta, reason: $reason,
                batchId: $batch->id, eventId: $sourceDomainEventId, occurredAt: $occurredAt,
            );
        });
    }

    // ----------------------------------------------------------------

    protected function lockBalance(int $groupId, string $stage): BiologicalAssetStageBalance
    {
        $organizationId = $this->currentOrganization->id();

        // firstOrCreate then lock (SQLite test DBs ignore lockForUpdate).
        $existing = BiologicalAssetStageBalance::query()
            ->when($organizationId !== null,
                fn ($q) => $q->where('organization_id', $organizationId),
                fn ($q) => $q->whereNull('organization_id'))
            ->where('group_id', $groupId)
            ->where('stage_key', $stage)
            ->lockForUpdate()
            ->first();

        if ($existing) {
            return $existing;
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

    protected function writeTransfer(
        int $groupId,
        ?string $from,
        ?string $to,
        string $amount,
        int $pigCount,
        string $weight,
        string $reason,
        ?int $batchId,
        ?int $eventId,
        ?CarbonInterface $occurredAt,
    ): BiologicalAssetStageTransfer {
        return BiologicalAssetStageTransfer::create([
            'organization_id'        => $this->currentOrganization->id(),
            'group_id'               => $groupId,
            'from_stage_key'         => $from,
            'to_stage_key'           => $to ?? $from, // exit rows store the stage in to_stage for display symmetry
            'amount'                 => $this->norm($amount, 4),
            'pig_count'              => abs($pigCount),
            'weight_kg'              => $this->norm($weight, 4),
            'reason'                 => $reason,
            'journal_batch_id'       => $batchId,
            'source_domain_event_id' => $eventId,
            'occurred_at'            => $occurredAt ?? CarbonImmutable::now(),
        ]);
    }
}
