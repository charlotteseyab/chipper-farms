<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class PigSale extends Model
{
    use SoftDeletes;

    // Get pricing from settings, fallback to defaults
    public static function getLiveWeightRate()
    {
        return \App\Models\Setting::get('sales.pricing.live_weight_price', 30.00);
    }

    public static function getCarcassWeightRate()
    {
        return \App\Models\Setting::get('sales.pricing.carcass_weight_price', 50.00);
    }

    /**
     * Phase E2 update: $fillable now includes the finance-readiness columns
     * added in Batch D so $sale->update(['is_posted' => true]) etc. actually
     * persist instead of being silently dropped by mass-assignment protection.
     */
    protected $fillable = [
        // Core sale columns
        'pig_id',
        'sale_date',
        'sale_price',
        'sale_weight',
        'weight_type',
        'price_per_kg',
        'buyer_name',
        'buyer_contact',
        'buyer_address',
        'sale_purpose',
        'payment_method',
        'payment_status',
        'notes',
        'sold_by',           // Legacy column (string).
        'sold_by_user_id',   // FK to users.id — source of truth.
        'sold_by_name',      // Denormalised audit copy.
        'transport_cost',
        'transport_method',
        'sale_documents',
        'destination',
        'sale_method',
        'transport_details',
        'final_weight',
        'sale_notes',

        // Phase 0.5 audit
        'payment_status_updated_at',
        'payment_status_updated_by_user_id',

        // Phase 0.8 finance-readiness columns (Batch D)
        'organization_id',
        'document_number',
        'is_posted',
        'posted_at',
        'customer_id',
        'payment_method_id',
        'cost_centre_id',
        'sales_channel_id',
        'tax_code_id',
        'legacy_source_id',

        // Phase 5 — COGS leg (Dr 5110 Cost of Pigs Sold / Cr 1230 Live Pig Inventory)
        'cogs_amount',
        'cogs_journal_batch_id',
        'cogs_posting_status',
        'cogs_posting_error',
        'cogs_group_id',
        'cogs_pig_weight_kg',
    ];

    protected $casts = [
        'sale_date'      => 'date',
        'sale_price'     => 'decimal:2',
        'sale_weight'    => 'decimal:2',
        'price_per_kg'   => 'decimal:2',
        'transport_cost' => 'decimal:2',
        'sale_documents' => 'array',

        // Phase 0.5 audit
        'payment_status_updated_at' => 'datetime',

        // Phase 0.8 finance-readiness casts
        'is_posted'  => 'boolean',
        'posted_at'  => 'datetime',

        // Phase 5 — COGS leg
        'cogs_amount'        => 'decimal:4',
        'cogs_pig_weight_kg' => 'decimal:4',
    ];

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($sale) {
            if ($sale->sale_weight && $sale->weight_type) {
                $sale->price_per_kg = $sale->price_rate;
                $sale->sale_price   = $sale->calculateTotalPrice();
            }
        });
    }

    public function pig(): BelongsTo
    {
        return $this->belongsTo(PigManagement::class, 'pig_id')->withTrashed();
    }

    public function soldBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sold_by_user_id');
    }

    /**
     * Phase 0.8: relations to finance-readiness counterparties / dimensions.
     * Used by Phase 1 reports and posting subscribers.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Counterparty::class, 'customer_id');
    }

    public function paymentMethodRef(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
    }

    public function costCentre(): BelongsTo
    {
        return $this->belongsTo(CostCentre::class, 'cost_centre_id');
    }

    public function salesChannel(): BelongsTo
    {
        return $this->belongsTo(SalesChannel::class, 'sales_channel_id');
    }

    public function taxCode(): BelongsTo
    {
        return $this->belongsTo(TaxCode::class, 'tax_code_id');
    }

    /**
     * Phase 5: COGS posting linkage. The COGS journal batch is SEPARATE
     * from the revenue journal batch — the two post independently so one
     * can succeed/fail without the other.
     */
    public function cogsJournalBatch(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Finance\JournalBatch::class, 'cogs_journal_batch_id');
    }

    public function cogsGroup(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'cogs_group_id');
    }

    public function getDisplaySoldByAttribute(): ?string
    {
        if (! empty($this->sold_by_name)) {
            return $this->sold_by_name;
        }

        if ($this->relationLoaded('soldBy')) {
            $related = $this->getRelation('soldBy');
            if ($related) {
                return $related->name;
            }
        } elseif ($this->sold_by_user_id) {
            $user = User::find($this->sold_by_user_id);
            if ($user) {
                return $user->name;
            }
        }

        return $this->sold_by ?: null;
    }

    public function scopeRecent($query, $days = 30)
    {
        return $query->where('sale_date', '>=', now()->subDays($days));
    }

    public function scopeByPurpose($query, $purpose)
    {
        return $query->where('sale_purpose', $purpose);
    }

    public function scopePending($query)
    {
        return $query->where('payment_status', 'pending');
    }

    public function scopePaid($query)
    {
        return $query->where('payment_status', 'paid');
    }

    public function getFormattedSalePriceAttribute(): string
    {
        return number_format($this->sale_price, 2);
    }

    public function getPriceRateAttribute(): float
    {
        return match($this->weight_type) {
            'live'    => self::getLiveWeightRate(),
            'carcass' => self::getCarcassWeightRate(),
            default   => self::getLiveWeightRate()
        };
    }

    public function getFormattedPriceRateAttribute(): string
    {
        return '₵' . number_format($this->price_rate, 2) . '/kg';
    }

    public function calculateTotalPrice(): float
    {
        if (! $this->sale_weight || $this->sale_weight <= 0) {
            return 0;
        }
        return $this->sale_weight * $this->price_rate;
    }

    public function getFormattedSaleWeightAttribute(): string
    {
        return $this->sale_weight ? number_format($this->sale_weight, 1) . ' kg' : 'Not recorded';
    }

    public function getPricePerKgAttribute(): ?float
    {
        return $this->price_per_kg ?? $this->price_rate;
    }

    public function getFormattedPricePerKgAttribute(): string
    {
        $pricePerKg = $this->price_per_kg;
        return $pricePerKg ? '₵' . number_format($pricePerKg, 2) . '/kg' : 'N/A';
    }

    public function getDaysAgoAttribute(): int
    {
        return $this->sale_date->diffInDays(now());
    }

    public function getPaymentStatusBadgeAttribute(): string
    {
        return match($this->payment_status) {
            'paid'    => 'bg-green-100 text-green-800',
            'partial' => 'bg-yellow-100 text-yellow-800',
            'pending' => 'bg-red-100 text-red-800',
            default   => 'bg-gray-100 text-gray-800'
        };
    }

    public function getSalePurposeBadgeAttribute(): string
    {
        return match($this->sale_purpose) {
            'market'    => 'bg-blue-100 text-blue-800',
            'breeding'  => 'bg-green-100 text-green-800',
            'slaughter' => 'bg-red-100 text-red-800',
            'export'    => 'bg-purple-100 text-purple-800',
            default     => 'bg-gray-100 text-gray-800'
        };
    }

    public function getSalePurposeIconAttribute(): string
    {
        return match($this->sale_purpose) {
            'market'    => '🏪',
            'breeding'  => '💕',
            'slaughter' => '🥩',
            'export'    => '🚢',
            default     => '💰'
        };
    }

    public static function getTotalSalesValue($startDate = null, $endDate = null)
    {
        $query = static::query();
        if ($startDate) $query->where('sale_date', '>=', $startDate);
        if ($endDate)   $query->where('sale_date', '<=', $endDate);
        return $query->sum('sale_price');
    }

    public static function getAverageSalePrice($purpose = null, $days = 30)
    {
        $query = static::recent($days);
        if ($purpose) $query->byPurpose($purpose);
        return $query->avg('sale_price');
    }

    public static function getSalesCount($startDate = null, $endDate = null)
    {
        $query = static::query();
        if ($startDate) $query->where('sale_date', '>=', $startDate);
        if ($endDate)   $query->where('sale_date', '<=', $endDate);
        return $query->count();
    }
}
