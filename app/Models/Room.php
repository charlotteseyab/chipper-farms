<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Room extends Model
{
    protected $fillable = [
        'structure_id', 'name', 'code', 'capacity', 'type', 'notes'
    ];

    public function structure(): BelongsTo
    {
        return $this->belongsTo(Structure::class);
    }

    public function pigs(): HasMany
    {
        return $this->hasMany(PigManagement::class, 'room_id');
    }
}


