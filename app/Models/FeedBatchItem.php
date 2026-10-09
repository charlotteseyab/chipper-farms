<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeedBatchItem extends Model
{
    use \App\Models\Concerns\BelongsToOrganizationThroughRelation;

    protected static function organizationRelation(): string
    {
        return 'feedBatch';
    }

    protected $fillable = [
        'feed_batch_id',
        'raw_material_id',
        'quantity_used',
        'unit_cost',
        'total_cost',
    ];

    protected $casts = [
        'quantity_used' => 'decimal:4',
        'unit_cost' => 'decimal:6',
        'total_cost' => 'decimal:4',
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
