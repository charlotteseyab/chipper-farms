<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeedTypeRecipe extends Model
{
    use \App\Models\Concerns\BelongsToOrganizationThroughRelation;

    protected static function organizationRelation(): string
    {
        return 'feedType';
    }

    protected $fillable = [
        'feed_type_id',
        'raw_material_id',
        'quantity_per_100kg',
    ];

    protected $casts = [
        'quantity_per_100kg' => 'decimal:2',
    ];

    public function feedType()
    {
        return $this->belongsTo(FeedType::class);
    }

    public function rawMaterial()
    {
        return $this->belongsTo(RawMaterial::class);
    }
}
