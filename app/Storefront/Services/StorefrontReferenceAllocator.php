<?php

namespace App\Storefront\Services;

use App\Models\Storefront\StorefrontListing;
use App\Models\Storefront\StorefrontOrder;
use App\Models\Storefront\StorefrontQuote;
use App\Services\Numbering\NumberingService;

/**
 * Single allocator for storefront business references (CF-P1-014).
 *
 * Why this class exists rather than a method on each service
 * ---------------------------------------------------------
 * Order references were generated in TWO places from a row count:
 * CheckoutService::nextReference() and QuoteService::nextOrderReference(). Both
 * counted `storefront_orders` and both formatted 'SO-{count+1}'. Because they
 * are independent, a checkout and a quote-to-order conversion happening near
 * each other could compute the same count and produce the SAME reference —
 * against a table with a unique key on (organization_id, reference), so one of
 * them fails outright. Duplicating the allocation logic was the root cause, so
 * the fix is one allocator both call, sharing one sequence.
 *
 * The register lists only CheckoutService and CustomerAccountService under
 * CF-P1-014. A repository-wide sweep for count-based identifiers found three
 * more: QuoteService's order reference, QuoteService's quote reference, and
 * PushToStorefrontService's listing reference. All are routed through here.
 *
 * Two properties of count-based numbering made it unsafe:
 *
 *  1. Concurrency — N writers read the same count and derive the same next
 *     value. `withTrashed()` was present, which shows the authors were aware of
 *     the deleted-row problem, but it does nothing about the race.
 *  2. Deletions shift the series — any hard delete makes the next generated
 *     reference collide with an existing row, permanently rather than once.
 *
 * NON-ANNUAL SEQUENCES
 * --------------------
 * These reference formats carry no year component. NumberingService keys
 * sequences on (organization, type, year), so passing the calendar year would
 * restart every series each January and immediately collide with the prior
 * year's references. year: 0 is used to mean "one continuous series per
 * organisation". Sequences that SHOULD reset annually (invoices, receipts) keep
 * passing a real year, unchanged.
 *
 * ADOPTING AN EXISTING SERIES
 * ---------------------------
 * Live databases already contain SO-, QUO- and LIST- references produced by the
 * old count-based code. A fresh sequence starting at 1 would collide with them,
 * so each allocation passes initialNumber — the highest suffix already in use
 * plus one — which NumberingService consults only when creating the sequence
 * row. That makes the switchover continuous rather than a renumbering.
 */
class StorefrontReferenceAllocator
{
    public function __construct(protected NumberingService $numbering)
    {
    }

    /**
     * Allocate a storefront order reference: SO-000123.
     *
     * Used by both checkout and quote-to-order conversion, deliberately sharing
     * one sequence.
     */
    public function orderReference(?int $organizationId): string
    {
        return $this->numbering->next(
            sequenceType: 'storefront_order',
            prefix: 'SO',
            year: 0,
            organizationId: $organizationId,
            padding: 6,
            formatTemplate: '{prefix}-{number}',
            initialNumber: $this->highestSuffix(
                StorefrontOrder::query()->withTrashed(),
                'reference',
                'SO-',
                $organizationId,
            ) + 1,
        );
    }

    /**
     * Allocate a quote reference: QUO-000045.
     */
    public function quoteReference(?int $organizationId): string
    {
        return $this->numbering->next(
            sequenceType: 'storefront_quote',
            prefix: 'QUO',
            year: 0,
            organizationId: $organizationId,
            padding: 6,
            formatTemplate: '{prefix}-{number}',
            initialNumber: $this->highestSuffix(
                StorefrontQuote::query()->withTrashed(),
                'reference',
                'QUO-',
                $organizationId,
            ) + 1,
        );
    }

    /**
     * Allocate a listing reference: LIST-000007.
     */
    public function listingReference(?int $organizationId): string
    {
        return $this->numbering->next(
            sequenceType: 'storefront_listing',
            prefix: 'LIST',
            year: 0,
            organizationId: $organizationId,
            padding: 6,
            formatTemplate: '{prefix}-{number}',
            initialNumber: $this->highestSuffix(
                StorefrontListing::query()->withTrashed(),
                'reference',
                'LIST-',
                $organizationId,
            ) + 1,
        );
    }

    /**
     * Highest numeric suffix among existing references with the given prefix.
     *
     * The suffix is fixed-width and zero-padded, so a lexicographic MAX() is
     * also the numeric maximum — true for up to 999999 which the padding
     * implies. Only consulted when a sequence row does not yet exist, so the
     * cost is paid once per organisation per series.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     */
    protected function highestSuffix($query, string $column, string $prefix, ?int $organizationId): int
    {
        $highest = $query
            ->where('organization_id', $organizationId)
            ->where($column, 'like', $prefix . '%')
            ->max($column);

        if (! is_string($highest) || ! preg_match('/(\d+)$/', $highest, $matches)) {
            return 0;
        }

        return (int) $matches[1];
    }
}
