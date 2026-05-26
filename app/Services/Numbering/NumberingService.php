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
    ): string {
        $year ??= (int) date('Y');

        return DB::transaction(function () use ($sequenceType, $prefix, $year, $organizationId, $padding, $formatTemplate) {
            $sequence = DocumentSequence::query()
                ->where('sequence_type', $sequenceType)
                ->where('year', $year)
                ->when(
                    $organizationId !== null,
                    fn ($q) => $q->where('organization_id', $organizationId),
                    fn ($q) => $q->whereNull('organization_id'),
                )
                ->lockForUpdate()
                ->first();

            if (! $sequence) {
                $sequence = DocumentSequence::create([
                    'organization_id' => $organizationId,
                    'sequence_type'   => $sequenceType,
                    'prefix'          => $prefix,
                    'year'            => $year,
                    'next_number'     => 1,
                    'padding'         => $padding,
                    'format_template' => $formatTemplate,
                    'is_active'       => true,
                ]);

                // Re-select with lock now that it exists, to make behaviour
                // uniform with the found-existing path.
                $sequence = DocumentSequence::where('id', $sequence->id)
                    ->lockForUpdate()
                    ->first();
            }

            $current = (int) $sequence->next_number;
            $rendered = $sequence->renderNumber($current);

            $sequence->increment('next_number');

            return $rendered;
        });
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