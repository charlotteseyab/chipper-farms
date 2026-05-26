<?php

namespace App\Models\Concerns;

use App\Models\AccountingDimensionLink;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Gives any finance-source model a `->dimensionLinks()` relation plus
 * helpers for tagging, untagging, and querying by dimension.
 *
 * Apply to source models that participate in accounting segmentation:
 *   - PigSale (cost centre, sales channel, structure)
 *   - PurchaseRequest (cost centre)
 *   - FeedBatch (cost centre, structure)
 *   - PigDeath (cost centre, structure)
 *   - RawMaterialStockLog (cost centre)
 *
 * Usage:
 *   $sale->tagDimension('cost_centre', $costCentreId, 100.0);
 *   $sale->tagDimension('sales_channel', $channelId, 100.0);
 *   $sale->dimensionLinks()->where('dimension_type', 'cost_centre')->get();
 *   $sale->untagDimension('cost_centre', $costCentreId);
 */
trait HasAccountingDimensions
{
    public function dimensionLinks(): MorphMany
    {
        return $this->morphMany(AccountingDimensionLink::class, 'documentable');
    }

    /**
     * Tag this document with a dimension. Does not deduplicate — if the
     * caller wants unique-per-dimension-type behaviour, it should call
     * untagDimensionType() first.
     */
    public function tagDimension(string $dimensionType, int $dimensionId, float $allocationPercentage = 100.0, ?int $organizationId = null): AccountingDimensionLink
    {
        return $this->dimensionLinks()->create([
            'dimension_type'        => $dimensionType,
            'dimension_id'          => $dimensionId,
            'allocation_percentage' => $allocationPercentage,
            'organization_id'       => $organizationId
                ?? ($this->organization_id ?? null),
        ]);
    }

    /**
     * Remove a specific dimension link.
     */
    public function untagDimension(string $dimensionType, int $dimensionId): int
    {
        return $this->dimensionLinks()
            ->where('dimension_type', $dimensionType)
            ->where('dimension_id', $dimensionId)
            ->delete();
    }

    /**
     * Remove all links of a given type from this document.
     * Useful when re-tagging ("replace the cost centre allocation").
     */
    public function untagDimensionType(string $dimensionType): int
    {
        return $this->dimensionLinks()
            ->where('dimension_type', $dimensionType)
            ->delete();
    }

    /**
     * Get all linked entities of a given dimension type.
     * Returns an array of resolved Eloquent models (may contain nulls if
     * a dimension was deleted and the link is stale).
     */
    public function dimensionsOfType(string $dimensionType): array
    {
        return $this->dimensionLinks()
            ->where('dimension_type', $dimensionType)
            ->get()
            ->map(fn (AccountingDimensionLink $link) => $link->dimension)
            ->filter()
            ->values()
            ->all();
    }
}