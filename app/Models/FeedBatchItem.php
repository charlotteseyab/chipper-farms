<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeedBatchItem extends Model
{
    protected $fillable = [
        'feed_batch_id',
        'raw_material_id',
        'quantity_used',
        'unit_cost',
        'total_cost',
    ];

    protected $casts = [
        'quantity_used' => 'decimal:2',
        'unit_cost' => 'decimal:2',
        'total_cost' => 'decimal:2',
    ];

    public function feedBatch()
    {
        return $this->belongsTo(FeedBatch::class);
    }

    public function rawMaterial()
    {
        return $this->belongsTo(RawMaterial::class);
    }
}