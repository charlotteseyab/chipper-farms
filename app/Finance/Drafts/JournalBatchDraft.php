<?php

namespace App\Finance\Drafts;

/**
 * Immutable collection of JournalDraft + batch-level metadata.
 *
 * The handoff unit between subscribers/services and PostingEngine.
 * One BatchDraft → one journal_batch row → one or more journals.
 *
 * Idempotency:
 *   The $idempotencyKey is required for AUTO batches (those triggered by
 *   domain events). It's nullable for MANUAL batches (manual journals
 *   don't need it — duplicate prevention isn't a concern when a human is
 *   typing each entry).
 *
 *   PostingEngine treats null idempotency_key as "do not write a
 *   journal_postings row" — manual journals don't pollute the idempotency
 *   tracker.
 */
final class JournalBatchDraft
{
    /**
     * @param  list<JournalDraft>  $journals
     */
    public function __construct(
        public readonly string $batchType,
        public readonly \DateTimeInterface $postingDate,
        public readonly array $journals,
        public readonly ?string $description = null,
        public readonly ?string $sourceModule = null,
        public readonly ?string $sourceType = null,
        public readonly ?int $sourceId = null,
        public readonly ?int $domainEventId = null,
        public readonly ?string $idempotencyKey = null,
        public readonly ?string $postingPurpose = null,
        public readonly ?string $subscriberClass = null,
        public readonly ?int $reversesBatchId = null,
    ) {
        if (count($journals) < 1) {
            throw new \InvalidArgumentException('JournalBatchDraft must have at least 1 journal');
        }

        foreach ($journals as $i => $j) {
            if (! $j instanceof JournalDraft) {
                throw new \InvalidArgumentException(
                    "JournalBatchDraft journal {$i} is not a JournalDraft instance"
                );
            }
        }

        // If idempotency_key is set, posting_purpose must also be set.
        // The two are paired in journal_postings.
        if ($idempotencyKey !== null && $postingPurpose === null) {
            throw new \InvalidArgumentException(
                'JournalBatchDraft with idempotency_key requires posting_purpose'
            );
        }
    }

    public function isAuto(): bool
    {
        return $this->idempotencyKey !== null;
    }

    public function isManual(): bool
    {
        return $this->idempotencyKey === null;
    }

    public function isReversal(): bool
    {
        return $this->reversesBatchId !== null;
    }
}
