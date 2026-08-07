<?php

namespace App\Models\Finance;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A selectable tax treatment (Phase 2, blueprint §30).
 *
 * One profile is what a user picks — "Ghana VAT 2026 Standard" — and it knows
 * which levies it contains and how they combine. Users never select VAT, NHIL
 * and GETFund separately.
 */
class TaxProfile extends Model
{
    public const TREATMENT_STANDARD    = 'standard';
    public const TREATMENT_ZERO_RATED  = 'zero_rated';
    public const TREATMENT_EXEMPT      = 'exempt';
    public const TREATMENT_NO_TAX      = 'no_tax';

    public const APPLIES_SALE     = 'sale';
    public const APPLIES_PURCHASE = 'purchase';
    public const APPLIES_BOTH     = 'both';

    public const PRICE_INCLUSIVE = 'inclusive';
    public const PRICE_EXCLUSIVE = 'exclusive';

    protected $table = 'tax_profiles';

    protected $fillable = [
        'organization_id',
        'code',
        'name',
        'description',
        'treatment',
        'applies_to',
        'price_mode',
        'effective_from',
        'effective_to',
        'is_default_sales',
        'is_default_purchase',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'effective_from'      => 'date',
            'effective_to'        => 'date',
            'is_default_sales'    => 'boolean',
            'is_default_purchase' => 'boolean',
            'is_active'           => 'boolean',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function components(): HasMany
    {
        return $this->hasMany(TaxProfileComponent::class)->orderBy('calculation_order');
    }

    /**
     * True when this profile produces no tax at all.
     *
     * Deliberately does NOT collapse zero_rated and exempt into one concept —
     * they differ on input tax recovery, and the distinction matters to the
     * return even though both charge nothing.
     */
    public function isZeroCharging(): bool
    {
        return in_array($this->treatment, [
            self::TREATMENT_ZERO_RATED,
            self::TREATMENT_EXEMPT,
            self::TREATMENT_NO_TAX,
        ], true);
    }

    public function allowsInputRecovery(): bool
    {
        return $this->treatment === self::TREATMENT_STANDARD
            || $this->treatment === self::TREATMENT_ZERO_RATED;
    }

    public function isInclusive(): bool
    {
        return $this->price_mode === self::PRICE_INCLUSIVE;
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function scopeForOrganization(Builder $q, int $organizationId): Builder
    {
        return $q->where('organization_id', $organizationId);
    }

    public function scopeEffectiveOn(Builder $q, \DateTimeInterface|string $date): Builder
    {
        $d = $date instanceof \DateTimeInterface ? $date->format('Y-m-d') : $date;

        return $q->where('effective_from', '<=', $d)
            ->where(function ($q) use ($d) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>=', $d);
            });
    }

    public function scopeForSales(Builder $q): Builder
    {
        return $q->whereIn('applies_to', [self::APPLIES_SALE, self::APPLIES_BOTH]);
    }

    public function scopeForPurchases(Builder $q): Builder
    {
        return $q->whereIn('applies_to', [self::APPLIES_PURCHASE, self::APPLIES_BOTH]);
    }
}
