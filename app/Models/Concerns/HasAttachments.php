<?php

namespace App\Models\Concerns;

use App\Models\Attachment;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Gives any model a `->attachments()` relation plus helpers for adding,
 * listing, and removing file attachments.
 *
 * Apply to models that should be able to carry supporting documents:
 *   - PigSale (invoice PDF, buyer ID)
 *   - PurchaseRequest (supplier invoice scan, PO, delivery note)
 *   - FeedBatch (mixing records)
 *   - etc.
 *
 * Usage:
 *   $sale->attachments()->create([...]);
 *   $sale->attachments_count;
 *   $sale->attachments_by_category('invoice')->get();
 */
trait HasAttachments
{
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function attachmentsByCategory(string $category): MorphMany
    {
        return $this->attachments()->where('category', $category);
    }
}