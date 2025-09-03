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
}


