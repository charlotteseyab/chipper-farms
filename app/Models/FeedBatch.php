<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Phase 4 — adds posting_status / posting_error / journal_batch_id to
 * the existing FeedBatch model.
 */
class FeedBatch extends Model
{
    protected $fillable = [
        'feed_type_id',
        'batch_date',
        'batch_size_kg',
        'total_cost',
        'notes',
        'mixed_by',

        // Phase 0.8 finance-readiness (Batch D)
        'organization_id',
        'document_number',
        'is_posted',
        'posted_at',
        'cost_centre_id',
        'legacy_source_id',

        // Phase 4
        'posting_status',
        'posting_error',
        'journal_batch_id',
    ];

    protected $casts = [
        'batch_date'    => 'date',
        'batch_size_kg' => 'decimal:2',
        'total_cost'    => 'decimal:2',

        // Phase 0.8
        'is_posted' => 'boolean',
        'posted_at' => 'datetime',
    ];

    public function feedType(): BelongsTo
    {
        return $this->belongsTo(FeedType::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(FeedBatchItem::class);
    }

    public function mixer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mixed_by');
    }

    public function costCentre(): BelongsTo
    {
        return $this->belongsTo(CostCentre::class, 'cost_centre_id');
    }
}
