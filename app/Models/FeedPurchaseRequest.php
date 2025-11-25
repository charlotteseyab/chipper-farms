<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeedPurchaseRequest extends Model
{
    protected $fillable = [
        'category',
        'item_name',
        'feed_type',
        'quantity',
        'unit',
        'cost_ghc',
        'requested_by',
        'needed_by',
        'notes',
        'vendor',
        'priority',
        'status',
        'is_paid',
        'paid_at',
        'decision_reason',
        'decided_by',
        'decided_at',
    ];

    protected $casts = [
        'quantity' => 'float',
        'cost_ghc' => 'float',
        'needed_by' => 'date',
        'decided_at' => 'datetime',
        'is_paid' => 'boolean',
        'paid_at' => 'datetime',
    ];

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
