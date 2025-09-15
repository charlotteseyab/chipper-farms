<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeedingStandard extends Model
{
    protected $fillable = [
        'pig_category',
        'feed_type_id',
        'daily_amount',
        'unit',
    ];

    public function feedType(): BelongsTo
    {
        return $this->belongsTo(FeedType::class);
    }
}
