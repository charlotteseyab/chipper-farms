<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaxCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'code',
        'name',
        'rate_type',
        'rate',
        'applies_to',
        'is_recoverable',
        'is_withholding',
        'is_compound',
        'gl_account_hint',
        'display_order',
        'is_active',
        'effective_from',
        'effective_to',
    ];

    protected function casts(): array
    {
        return [
            'rate'             => 'decimal:4',
            'is_recoverable'   => 'boolean',
            'is_withholding'   => 'boolean',
            'is_compound'      => 'boolean',
            'display_order'    => 'integer',
            'is_active'        => 'boolean',
            'effective_from'   => 'date',
            'effective_to'     => 'date',
        ];
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function scopeEffectiveOn(Builder $q, \DateTimeInterface|string $date): Builder
    {
        $d = $date instanceof \DateTimeInterface ? $date->format('Y-m-d') : $date;
        return $q->where(function ($q) use ($d) {
            $q->whereNull('effective_from')->orWhere('effective_from', '<=', $d);
        })->where(function ($q) use ($d) {
            $q->whereNull('effective_to')->orWhere('effective_to', '>=', $d);
        });
    }

    public function scopeForSales(Builder $q): Builder
    {
        return $q->whereIn('applies_to', ['sale', 'both']);
    }

    public function scopeForPurchases(Builder $q): Builder
    {
        return $q->whereIn('applies_to', ['purchase', 'both']);
    }

    public function calculateTax(float $baseAmount): float
    {
        if ($this->rate_type === 'percent') {
            return round($baseAmount * ((float) $this->rate / 100), 2);
        }
        return round((float) $this->rate, 2);  // fixed amount
    }
}