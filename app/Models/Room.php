<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Room extends Model
{
    use \App\Models\Concerns\BelongsToOrganization;

    protected $fillable = [
        'organization_id', 'structure_id', 'name', 'code', 'capacity', 'type', 'notes'
    ];

    public function structure(): BelongsTo
    {
        return $this->belongsTo(Structure::class);
    }

    public function pigs(): HasMany
    {
        return $this->hasMany(PigManagement::class, 'room_id');
    }

    /**
     * Determine if the room can accommodate a pig, optionally excluding a pig by ID
     */
    public function canAccommodatePig(?int $excludePigId = null): bool
    {
        // Unlimited capacity if null or zero/negative
        if (is_null($this->capacity) || (int)$this->capacity <= 0) {
            return true;
        }

        $activePigsQuery = $this->pigs()
            ->whereDoesntHave('sales')
            ->whereDoesntHave('deaths');

        if (!is_null($excludePigId)) {
            $activePigsQuery->where('id', '!=', $excludePigId);
        }

        $currentActive = (int) $activePigsQuery->count();
        return $currentActive < (int) $this->capacity;
    }

    /**
     * How many more pigs fit, by the same count canAccommodatePig() uses;
     * null when the room has no capacity limit.
     */
    public function freePlaces(): ?int
    {
        if (is_null($this->capacity) || (int) $this->capacity <= 0) {
            return null;
        }

        $currentActive = (int) $this->pigs()->whereDoesntHave('sales')->whereDoesntHave('deaths')->count();

        return max(0, (int) $this->capacity - $currentActive);
    }
}
