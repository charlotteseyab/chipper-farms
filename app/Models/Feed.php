<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class Feed extends Model
{
    protected $fillable = [
        'pig_id',
        'feed_type',
        'feed_amount',
        'feed_unit',
        'feed_date',
        'feed_time',
        'notes',
        'recorded_by',
        'feed_cost',
        'supplier',
        'batch_number',
        'expiry_date'
    ];

    protected $casts = [
        'feed_date' => 'date',
        'feed_time' => 'datetime',
        'feed_amount' => 'decimal:2',
        'feed_cost' => 'decimal:2',
        'expiry_date' => 'date'
    ];

    /**
     * Get the pig this feed record belongs to
     */
    public function pig(): BelongsTo
    {
        return $this->belongsTo(PigManagement::class, 'pig_id');
    }

    /**
     * Get the user who recorded this feed
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * Get formatted feed amount
     */
    public function getFormattedAmountAttribute(): string
    {
        return number_format($this->feed_amount, 2) . ' ' . $this->feed_unit;
    }

    /**
     * Get formatted feed cost
     */
    public function getFormattedCostAttribute(): string
    {
        return $this->feed_cost ? '$' . number_format($this->feed_cost, 2) : 'N/A';
    }

    /**
     * Get feed type display name
     */
    public function getFeedTypeDisplayAttribute(): string
    {
        return match($this->feed_type) {
            'starter' => 'Starter Feed',
            'grower' => 'Grower Feed',
            'finisher' => 'Finisher Feed',
            'breeding' => 'Breeding Feed',
            'lactating' => 'Lactating Feed',
            'maintenance' => 'Maintenance Feed',
            'supplement' => 'Supplement',
            'medicated' => 'Medicated Feed',
            default => ucfirst(str_replace('_', ' ', $this->feed_type))
        };
    }

    /**
     * Get feed type color for UI
     */
    public function getFeedTypeColorAttribute(): string
    {
        return match($this->feed_type) {
            'starter' => 'blue',
            'grower' => 'green',
            'finisher' => 'orange',
            'breeding' => 'purple',
            'lactating' => 'pink',
            'maintenance' => 'gray',
            'supplement' => 'yellow',
            'medicated' => 'red',
            default => 'gray'
        };
    }

    /**
     * Get feed type badge class
     */
    public function getFeedTypeBadgeAttribute(): string
    {
        $color = $this->feed_type_color;
        return "bg-{$color}-100 text-{$color}-800";
    }

    /**
     * Scope for recent feed records
     */
    public function scopeRecent($query, $days = 7)
    {
        return $query->where('feed_date', '>=', now()->subDays($days));
    }

    /**
     * Scope for specific feed type
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('feed_type', $type);
    }

    /**
     * Scope for specific pig
     */
    public function scopeForPig($query, $pigId)
    {
        return $query->where('pig_id', $pigId);
    }

    /**
     * Get total feed cost for a date range
     */
    public static function getTotalCost($startDate = null, $endDate = null)
    {
        $query = static::query();
        
        if ($startDate) {
            $query->where('feed_date', '>=', $startDate);
        }
        
        if ($endDate) {
            $query->where('feed_date', '<=', $endDate);
        }
        
        return $query->sum('feed_cost') ?? 0;
    }

    /**
     * Get total feed amount for a date range
     */
    public static function getTotalAmount($startDate = null, $endDate = null, $unit = 'kg')
    {
        $query = static::query()->where('feed_unit', $unit);
        
        if ($startDate) {
            $query->where('feed_date', '>=', $startDate);
        }
        
        if ($endDate) {
            $query->where('feed_date', '<=', $endDate);
        }
        
        return $query->sum('feed_amount') ?? 0;
    }
}
