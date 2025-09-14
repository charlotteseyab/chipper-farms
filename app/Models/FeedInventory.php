<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeedInventory extends Model
{
    protected $fillable = [
        'feed_type',
        'quantity',
        'unit',
        'last_updated',
    ];

    protected $casts = [
        'quantity' => 'float',
        'last_updated' => 'date',
    ];

    public function scopeOfType($query, $type)
    {
        return $query->where('feed_type', $type);
    }
}
