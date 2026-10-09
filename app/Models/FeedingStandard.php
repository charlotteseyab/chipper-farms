<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class FeedingStandard extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['organization_id', 'stage', 'feed_type_id', 'daily_kg', 'stage_basis', 'effective_from', 'is_active', 'created_by'];

    protected $casts = ['daily_kg' => 'decimal:4', 'effective_from' => 'date', 'is_active' => 'boolean'];

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
