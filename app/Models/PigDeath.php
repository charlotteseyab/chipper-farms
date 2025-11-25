<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class PigDeath extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'pig_id',
        'death_date',
        'death_cause',
        'age_at_death',
        'final_weight',
        'death_location',
        'veterinary_notes',
        'disposal_method',
        'disposal_date',
        'death_notes',
        'recorded_by'
    ];

    protected $casts = [
        'death_date' => 'date',
        'disposal_date' => 'date',
        'final_weight' => 'decimal:2',
        'age_at_death' => 'integer'
    ];

    // Relationships
    public function pig(): BelongsTo
    {
        return $this->belongsTo(PigManagement::class, 'pig_id')->withTrashed();
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    // Accessors
    public function getDeathCauseDisplayAttribute(): string
    {
        return match($this->death_cause) {
            'disease' => 'Disease',
            'accident' => 'Accident',
            'natural' => 'Natural Causes',
            'stillbirth' => 'Stillbirth',
            'crushing' => 'Crushing (Sow)',
            'starvation' => 'Starvation',
            'hypothermia' => 'Hypothermia',
            'dehydration' => 'Dehydration',
            'congenital' => 'Congenital Defect',
            'unknown' => 'Unknown',
            'other' => 'Other',
            default => 'Unknown'
        };
    }

    public function getDisposalMethodDisplayAttribute(): string
    {
        return match($this->disposal_method) {
            'burial' => 'Burial',
            'incineration' => 'Incineration',
            'rendering' => 'Rendering',
            'other' => 'Other',
            default => 'Burial'
        };
    }

    public function getAgeAtDeathDisplayAttribute(): string
    {
        if (!$this->age_at_death) {
            return 'Unknown';
        }

        $years = floor($this->age_at_death / 365);
        $months = floor(($this->age_at_death % 365) / 30);
        $days = $this->age_at_death % 30;

        $parts = [];
        if ($years > 0) $parts[] = $years . ' year' . ($years > 1 ? 's' : '');
        if ($months > 0) $parts[] = $months . ' month' . ($months > 1 ? 's' : '');
        if ($days > 0) $parts[] = $days . ' day' . ($days > 1 ? 's' : '');

        return implode(', ', $parts) ?: '0 days';
    }

    // Scopes
    public function scopeByCause($query, $cause)
    {
        return $query->where('death_cause', $cause);
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('death_date', [$startDate, $endDate]);
    }

    public function scopeRecent($query, $days = 30)
    {
        return $query->where('death_date', '>=', now()->subDays($days));
    }

    // Helper methods
    public function calculateAgeAtDeath(): int
    {
        if (!$this->pig || !$this->pig->date_of_birth) {
            return 0;
        }

        return $this->pig->date_of_birth->diffInDays($this->death_date);
    }

    public function updateAgeAtDeath(): void
    {
        $this->update(['age_at_death' => $this->calculateAgeAtDeath()]);
    }

    // Boot method to automatically calculate age at death
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($death) {
            if ($death->pig && $death->pig->date_of_birth && $death->death_date) {
                $death->age_at_death = $death->calculateAgeAtDeath();
            }
        });

        static::updating(function ($death) {
            if ($death->isDirty(['death_date']) && $death->pig && $death->pig->date_of_birth) {
                $death->age_at_death = $death->calculateAgeAtDeath();
            }
        });
    }
}