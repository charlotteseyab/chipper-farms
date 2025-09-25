<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeedSchedule extends Model
{
    protected $fillable = [
        'group_id','structure_id','feed_type_id','planned_amount','unit','scheduled_for','status','fed_at','fed_by','notes'
    ];

    protected $casts = [
        'planned_amount' => 'float',
        'scheduled_for' => 'date',
        'fed_at' => 'datetime',
    ];

    public function group(): BelongsTo { return $this->belongsTo(Group::class); }
    public function structure(): BelongsTo { return $this->belongsTo(Structure::class); }
    public function feedType(): BelongsTo { return $this->belongsTo(FeedType::class); }
    public function feeder(): BelongsTo { return $this->belongsTo(User::class, 'fed_by'); }
}


