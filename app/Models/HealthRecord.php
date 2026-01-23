<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class HealthRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'pig_id',
        'record_date',
        'condition',
        'severity',
        'symptoms',
        'diagnosis',
        'treatment_plan',
        'recorded_by',
    ];

    protected $casts = [
        'record_date' => 'date',
    ];

    public function pig(): BelongsTo
    {
        return $this->belongsTo(PigManagement::class, 'pig_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}


