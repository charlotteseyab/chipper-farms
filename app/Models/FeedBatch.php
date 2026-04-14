<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeedBatch extends Model
{
    protected $fillable = [
        'feed_type_id',
        'batch_date',
        'batch_size_kg',
        'total_cost',
        'notes',
        'mixed_by',
    ];

    protected $casts = [
        'batch_date' => 'date',
        'batch_size_kg' => 'decimal:2',
        'total_cost' => 'decimal:2',
    ];

    public function feedType()
    {
        return $this->belongsTo(FeedType::class);
    }

    public function items()
    {
        return $this->hasMany(FeedBatchItem::class);
    }

    public function mixer()
    {
        return $this->belongsTo(User::class, 'mixed_by');
    }
}