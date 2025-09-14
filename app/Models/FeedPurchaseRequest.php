<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeedPurchaseRequest extends Model
{
    protected $fillable = [
        'feed_type',
        'quantity',
        'unit',
        'cost_ghc',
        'requested_by',
        'needed_by',
        'notes',
        'status',
    ];

    protected $casts = [
        'quantity' => 'float',
        'cost_ghc' => 'float',
        'needed_by' => 'date',
    ];

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
