<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Structure extends Model
{
    protected $fillable = [
        'name', 'description', 'capacity', 'status', 'created_by'
    ];

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    public function getIsFullAttribute(): bool
    {
        if (!$this->capacity) {
            return false; // No capacity limit means never full
        }
        return $this->rooms()->count() >= $this->capacity;
    }

    public function getCurrentRoomCountAttribute(): int
    {
        return $this->rooms()->count();
    }

    public function getAvailableCapacityAttribute(): int
    {
        if (!$this->capacity) {
            return -1; // -1 indicates unlimited capacity
        }
        return max(0, $this->capacity - $this->rooms()->count());
    }

    public function getTotalPigsAttribute(): int
    {
        // Count individual pigs with ear tags (weaned pigs)
        $weanedPigs = $this->rooms()->withCount('pigs')->get()->sum('pigs_count');
        
        // Count piglets from active litters (pre-weaning)
        $weaningAge = config('breeding.cycle.birth_to_weaning_days', 45);
        $activeLitters = $this->rooms()
            ->whereHas('pigs.farrowingsAsSow', function($query) use ($weaningAge) {
                $query->where('farrowing_date', '>=', now()->subDays($weaningAge))
                      ->where('status', 'active');
            })
            ->with(['pigs.farrowingsAsSow' => function($query) use ($weaningAge) {
                $query->where('farrowing_date', '>=', now()->subDays($weaningAge))
                      ->where('status', 'active');
            }])
            ->get();
            
        $pigletsCount = 0;
        foreach ($activeLitters as $room) {
            foreach ($room->pigs as $sow) {
                foreach ($sow->farrowingsAsSow as $farrowing) {
                    // Use net live piglets (live births minus recorded piglet deaths)
                    $pigletsCount += method_exists($farrowing, 'net_live_piglets')
                        ? $farrowing->net_live_piglets
                        : $farrowing->live_births;
                }
            }
        }
        
        return $weanedPigs + $pigletsCount;
    }
}


