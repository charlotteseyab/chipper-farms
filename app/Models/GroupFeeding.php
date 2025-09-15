<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroupFeeding extends Model
{
    protected $fillable = [
        'group_id',
        'feed_type_id',
        'amount',
        'unit',
        'date',
        'wastage',
        'recorded_by',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }
    public function feedType(): BelongsTo
    {
        return $this->belongsTo(FeedType::class);
    }
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
