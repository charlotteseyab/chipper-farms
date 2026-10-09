<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class FeedStageOverride extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['organization_id', 'pig_id', 'previous_stage', 'stage', 'reason', 'created_by'];

    protected $casts = [];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Create a new effective version instead of rewriting feed history.'));
        static::deleting(fn () => throw new \LogicException('Feed history cannot be deleted.'));
    }
}
