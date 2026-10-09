<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class FeedProductionPlan extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['organization_id', 'feed_type_id', 'planned_quantity_kg', 'production_date', 'recipe_snapshot', 'status', 'feed_batch_id', 'submission_key', 'created_by', 'cancelled_at', 'cancelled_by', 'cancellation_reason'];

    protected $casts = ['production_date' => 'date', 'planned_quantity_kg' => 'decimal:4', 'recipe_snapshot' => 'array', 'cancelled_at' => 'datetime'];

    public function feedType()
    {
        return $this->belongsTo(FeedType::class);
    }
}
