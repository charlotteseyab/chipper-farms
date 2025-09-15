<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeedInventory extends Model
{
    protected $fillable = [
        'feed_type_id',
        'quantity',
        'unit',
        'last_updated',
    ];

    protected $casts = [
        'quantity' => 'float',
        'last_updated' => 'date',
    ];

    public function feedType(): BelongsTo
    {
        return $this->belongsTo(FeedType::class);
    }
}
