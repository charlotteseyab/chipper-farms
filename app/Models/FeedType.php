<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeedType extends Model
{
    use \App\Models\Concerns\BelongsToOrganization;

    protected $fillable = [
        'is_active',
        'organization_id',
        'name',
        'notes',
        'wastage_percent',
    ];

    protected $casts = ['wastage_percent' => 'decimal:2'];

    public function recipes()
    {
        return $this->hasMany(FeedTypeRecipe::class);
    }

    public function batches()
    {
        return $this->hasMany(FeedBatch::class);
    }
}
