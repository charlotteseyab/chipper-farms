<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FiscalYear extends Model
{
    use HasFactory, \App\Models\Concerns\BelongsToOrganization;

    public const STATUS_OPEN   = 'open';
    public const STATUS_CLOSED = 'closed';
    public const STATUS_LOCKED = 'locked';

    protected $fillable = [
        'organization_id',
        'name',
        'fiscal_year',
        'start_date',
        'end_date',
        'status',
        'is_current',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'fiscal_year' => 'integer',
            'start_date'  => 'date',
            'end_date'    => 'date',
            'is_current'  => 'boolean',
        ];
    }

    public function periods(): HasMany
    {
        return $this->hasMany(FiscalPeriod::class);
    }

    public function scopeOpen(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_OPEN);
    }

    public function scopeCurrent(Builder $q): Builder
    {
        return $q->where('is_current', true);
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    public function isLocked(): bool
    {
        return $this->status === self::STATUS_LOCKED;
    }
}
