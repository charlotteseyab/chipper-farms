<?php

namespace App\Services\Numbering;

use App\Models\DocumentSequence;
use Illuminate\Support\Facades\DB;

/**
 * Allocates document numbers atomically across concurrent writers.
 *
 * Use case: a pig sale is being finalised. The controller needs an invoice
 * number like 'INV-2026-000123'. If two concurrent sales hit the allocator
 * at the same moment, both must receive distinct numbers — no duplicates,
 * no gaps that weren't caused by a failed transaction.
 *
 * Implementation:
 *   - Runs inside a DB transaction.
 *   - SELECT ... FOR UPDATE on the sequence row to serialise readers.
 *   - Reads next_number, renders the formatted string, increments next_number,
 *     commits.
 *
 * Concurrency properties:
 *   - No lost increments (row lock prevents TOCTOU).
 *   - If the caller's outer transaction rolls back, the number is consumed
 *     anyway (SELECT FOR UPDATE commits the sequence write as part of the
 *     outer transaction; on rollback the number is lost — this is the
 *     accepted behaviour). Gaps in document numbers caused by rollback
 *     are industry-standard and explainable ("voided PR 2026-000057").
 *
 * Usage:
 *
 *   $number = app(NumberingService::class)->next(
 *       sequenceType: 'pig_sale',
 *       prefix: 'INV',
 *       year: 2026,
 *       organizationId: $org->id,
 *   );
 *   // $number === 'INV-2026-000124'
 *
 * If the sequence row does not exist for the given (org, type, year),
 * the service creates it with next_number=1 and returns that first number.
 */
class NumberingService
{
    /**
     * Allocate the next document number for the given sequence.
     *
     * @param  string  $sequenceType  e.g. 'pig_sale', 'purchase_request', 'pig_death'
     * @param  string  $prefix        e.g. 'INV', 'PR', 'PD'
     * @param  int|null  $year        Defaults to the current year.
     * @param  int|null  $organizationId
     * @param  int  $padding          Zero-padding width. Default 6 → 000001.
     * @return string                 The rendered document number.
     */
    public function next(
        string $sequenceType,
        string $prefix,
        ?int $year = null,
        ?int $organizationId = null,
        int $padding = 6,
        ?string $formatTemplate = null,
        ?int $initialNumber = null,
    ): string {
        $year ??= (int) date('Y');

        return DB::transaction(function () use ($sequenceType, $prefix, $year, $organizationId, $padding, $formatTemplate, $initialNumber) {
            $sequence = $this->lockSequence($sequenceType, $year, $organizationId);

            if (! $sequence) {
                // FIRST-USE RACE (CF-P1-013)
                //
                // This branch used to call DocumentSequence::create() directly.
                // Two writers allocating the first number for the same
                // (org, type, year) both find no row and both insert. The
                // migration's unique key then rejects one of them with a
                // QueryException, so the very first invoice or receipt of a
                // period could fail under concurrent load.
                //
                // insertOrIgnore makes the insert non-fatal for the loser of the
                // race; whichever writer lost then re-selects the winner's row
                // under a lock and allocates from it normally.
                $this->insertSequenceIgnoringConflict(
                    $sequenceType, $prefix, $year, $organizationId, $padding, $formatTemplate,
                    $initialNumber,
                );

                $sequence = $this->lockSequence($sequenceType, $year, $organizationId);
            }

            if (! $sequence) {
                // Neither our insert nor a competitor's produced a visible row.
                // Fail loudly rather than returning a number we cannot prove is
                // unique.
                throw new \RuntimeException(sprintf(
                    'Could not allocate a document number: sequence (org=%s, type=%s, year=%d) '
                    . 'could not be created or located.',
                    $organizationId === null ? 'null' : $organizationId,
                    $sequenceType,
                    $year,
                ));
            }

            $current = (int) $sequence->next_number;
            $rendered = $sequence->renderNumber($current);

            $sequence->increment('next_number');

            return $rendered;
        });
    }

    /**
     * Select the sequence row FOR UPDATE, serialising concurrent allocators.
     *
     * Ordered by id and limited to one row deliberately. The unique key is
     * (organization_id, sequence_type, year), and MySQL/MariaDB treat NULLs as
     * DISTINCT in a unique index — verified against MariaDB 11.4, where two rows
     * with organization_id = NULL, the same type and the same year coexist
     * happily. So for GLOBAL (org-less) sequences the unique key provides no
     * protection at all and duplicate rows are possible.
     *
     * Ordering by id makes every allocator converge on the same row — the
     * lowest-id one — so allocations still serialise on a single lock and cannot
     * hand out duplicate numbers even if a duplicate row already exists. That
     * turns an unbounded correctness bug into a cosmetic dead row.
     */
    protected function lockSequence(string $sequenceType, int $year, ?int $organizationId): ?DocumentSequence
    {
        return DocumentSequence::query()
            ->where('sequence_type', $sequenceType)
            ->where('year', $year)
            ->when(
                $organizationId !== null,
                fn ($q) => $q->where('organization_id', $organizationId),
                fn ($q) => $q->whereNull('organization_id'),
            )
            ->orderBy('id')
            ->lockForUpdate()
            ->first();
    }

    /**
     * Insert the sequence row, tolerating a concurrent writer having won.
     *
     * insertOrIgnore bypasses Eloquent events, so timestamps are set explicitly.
     */
    protected function insertSequenceIgnoringConflict(
        string $sequenceType,
        string $prefix,
        int $year,
        ?int $organizationId,
        int $padding,
        ?string $formatTemplate,
        ?int $initialNumber = null,
    ): void {
        $now = now();

        DocumentSequence::query()->insertOrIgnore([
            'organization_id' => $organizationId,
            'sequence_type'   => $sequenceType,
            'prefix'          => $prefix,
            'year'            => $year,
            // $initialNumber lets a caller adopt an existing series without
            // colliding with it. Storefront order references and customer codes
            // were previously derived from count()+1, so live data already
            // occupies SO-000001.. and CUST-00001.. — a sequence starting at 1
            // would collide with rows the unique key protects. The caller passes
            // the highest number already in use plus one, which is consulted
            // only when the sequence row is first created.
            'next_number'     => $initialNumber !== null && $initialNumber > 0 ? $initialNumber : 1,
            'padding'         => $padding,
            'format_template' => $formatTemplate,
            'is_active'       => true,
            'created_at'      => $now,
            'updated_at'      => $now,
        ]);
    }

    /**
     * Peek the next number without consuming it.
     * Useful for display purposes only — do NOT persist the peeked number
     * as the final document number, as it may be taken by another writer.
     */
    public function peek(
        string $sequenceType,
        string $prefix,
        ?int $year = null,
        ?int $organizationId = null,
        int $padding = 6,
    ): string {
        $year ??= (int) date('Y');

        $sequence = DocumentSequence::query()
            ->where('sequence_type', $sequenceType)
            ->where('year', $year)
            ->when(
                $organizationId !== null,
                fn ($q) => $q->where('organization_id', $organizationId),
                fn ($q) => $q->whereNull('organization_id'),
            )
            ->first();

        $current = $sequence ? (int) $sequence->next_number : 1;
        $padded  = str_pad((string) $current, $padding, '0', STR_PAD_LEFT);

        if ($sequence && $sequence->format_template) {
            return strtr($sequence->format_template, [
                '{prefix}' => $prefix,
                '{year}'   => (string) $year,
                '{number}' => $padded,
            ]);
        }

        return sprintf('%s-%d-%s', $prefix, $year, $padded);
    }
}