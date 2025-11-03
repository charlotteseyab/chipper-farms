<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PigletDeath extends Model
{
    protected $fillable = [
        'farrowing_id',
        'death_date',
        'count',
        'cause',
        'notes',
        'recorded_by',
    ];

    protected $casts = [
        'death_date' => 'date',
        'count' => 'integer',
    ];

    public function farrowing(): BelongsTo
    {
        return $this->belongsTo(Farrowing::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
