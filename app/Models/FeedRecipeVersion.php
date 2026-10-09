<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class FeedRecipeVersion extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['organization_id', 'feed_type_id', 'effective_from', 'is_active', 'ingredients', 'created_by'];

    protected $casts = ['effective_from' => 'date', 'is_active' => 'boolean', 'ingredients' => 'array'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Create a new effective version instead of rewriting feed history.'));
        static::deleting(fn () => throw new \LogicException('Feed history cannot be deleted.'));
    }

    public function feedType()
    {
        return $this->belongsTo(FeedType::class);
    }
}
