<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
// Assuming your User model is here. Adjust if necessary.
use App\Models\User; 

class FeedSchedule extends Model
{
    protected $fillable = [
        'group_id',
        'structure_id',
        'feed_type_id',
        'planned_amount',
        'unit',
        'scheduled_for',
        'status',
        'fed_at',
        'notes',
        // CRITICAL FIXES: 
        'skip_reason', // Needed for the skip reason to save
        'feeder_id',   // Used for 'Skipped By' (Controller: markSkipped)
        'fed_by',      // Used for 'Fed By' (Controller: markFed)
    ];

    protected $casts = [
        'planned_amount' => 'float',
        'scheduled_for' => 'date',
        'fed_at' => 'datetime',
    ];

    public function group(): BelongsTo { return $this->belongsTo(Group::class); }
    public function structure(): BelongsTo { return $this->belongsTo(Structure::class); }
    public function feedType(): BelongsTo { return $this->belongsTo(FeedType::class); }
    
    // --- RELATIONSHIP FIXES BELOW ---

    /**
     * FIX 1: Corrected the existing relationship to use the 'feeder_id' column, 
     * which is used by the markSkipped controller method.
     */
    public function feeder(): BelongsTo
    { 
        return $this->belongsTo(User::class, 'feeder_id'); 
    }

    /**
     * FIX 2: Added the missing 'fedBy' relationship.
     * This uses the 'fed_by_id' column, which is used by the markFed controller method.
     */
    public function fedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fed_by');
    }
}
