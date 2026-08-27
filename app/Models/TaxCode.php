<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaxCode extends Model
{
    use HasFactory, \App\Models\Concerns\BelongsToOrganization;

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

    /**
     * Tax on a base amount, as a decimal string.
     *
     * Takes and returns strings. The previous signature was
     * `calculateTax(float): float` and used `round($base * $rate / 100, 2)`,
     * which is binary floating point applied to money — the one thing this
     * codebase's money rules forbid outright. It happened to be unused, so no
     * posted figure was ever wrong, but it was a live trap: the first caller to
     * reach for it would have introduced representation error into tax.
     *
     * For a multi-levy treatment such as Ghana's VAT/NHIL/GETFund stack, do not
     * call this three times and add up — use TaxCalculator with a TaxProfile,
     * which knows whether the levies share a base or cascade.
     */
    public function calculateTax(string $baseAmount, int $scale = 2): string
    {
        if (! is_numeric($baseAmount)) {
            throw new \InvalidArgumentException("Tax base must be numeric, got [{$baseAmount}].");
        }

        if ($this->rate_type !== 'percent') {
            return \App\Services\Finance\Tax\TaxCalculation::round((string) $this->rate, $scale);
        }

        return \App\Services\Finance\Tax\TaxCalculation::round(
            bcdiv(bcmul($baseAmount, (string) $this->rate, 10), '100', 10),
            $scale,
        );
    }
}
