<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;
class PigSale extends Model
{
    use SoftDeletes;
    // Price rates per kg
    // Get pricing from settings, fallback to defaults
    public static function getLiveWeightRate()
    {
        return \App\Models\Setting::get('sales.pricing.live_weight_price', 30.00);
    }
    
    public static function getCarcassWeightRate()
    {
        return \App\Models\Setting::get('sales.pricing.carcass_weight_price', 50.00);
    }
    protected $fillable = [
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
        'sold_by',
        'transport_cost',
        'transport_method',
        'sale_documents',
        'destination',
        'sale_method',
        'transport_details',
        'final_weight',
        'sale_notes',
    ];

    protected $casts = [
        'sale_date' => 'date',
        'sale_price' => 'decimal:2',
        'sale_weight' => 'decimal:2',
        'price_per_kg' => 'decimal:2',
        'transport_cost' => 'decimal:2',
        'sale_documents' => 'array'
    ];

    // Boot method to auto-calculate price
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($sale) {
            // Auto-calculate price if weight and weight_type are set
            if ($sale->sale_weight && $sale->weight_type) {
                $sale->price_per_kg = $sale->price_rate;
                $sale->sale_price = $sale->calculateTotalPrice();
            }
        });
    }

    // Relationships
    public function pig(): BelongsTo
    {
        return $this->belongsTo(PigManagement::class, 'pig_id')->withTrashed();
    }

    public function soldBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sold_by');
    }

    // Scopes
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

    // Accessors
    public function getFormattedSalePriceAttribute(): string
    {
        return number_format($this->sale_price, 2);
    }

    public function getPriceRateAttribute(): float
    {
        return match($this->weight_type) {
            'live' => self::getLiveWeightRate(),
            'carcass' => self::getCarcassWeightRate(),
            default => self::getLiveWeightRate()
        };
    }

    public function getFormattedPriceRateAttribute(): string
    {
        return '₵' . number_format($this->price_rate, 2) . '/kg';
    }

    public function calculateTotalPrice(): float
    {
        if (!$this->sale_weight || $this->sale_weight <= 0) {
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
        // Return the stored price per kg rate, not calculated from total price
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
            'paid' => 'bg-green-100 text-green-800',
            'partial' => 'bg-yellow-100 text-yellow-800',
            'pending' => 'bg-red-100 text-red-800',
            default => 'bg-gray-100 text-gray-800'
        };
    }

    public function getSalePurposeBadgeAttribute(): string
    {
        return match($this->sale_purpose) {
            'market' => 'bg-blue-100 text-blue-800',
            'breeding' => 'bg-green-100 text-green-800',
            'slaughter' => 'bg-red-100 text-red-800',
            'export' => 'bg-purple-100 text-purple-800',
            default => 'bg-gray-100 text-gray-800'
        };
    }

    public function getSalePurposeIconAttribute(): string
    {
        return match($this->sale_purpose) {
            'market' => '🏪',
            'breeding' => '💕',
            'slaughter' => '🥩',
            'export' => '🚢',
            default => '💰'
        };
    }

    // Static methods
    public static function getTotalSalesValue($startDate = null, $endDate = null)
    {
        $query = static::query();

        if ($startDate) {
            $query->where('sale_date', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('sale_date', '<=', $endDate);
        }

        return $query->sum('sale_price');
    }

    public static function getAverageSalePrice($purpose = null, $days = 30)
    {
        $query = static::recent($days);

        if ($purpose) {
            $query->byPurpose($purpose);
        }

        return $query->avg('sale_price');
    }

    public static function getSalesCount($startDate = null, $endDate = null)
    {
        $query = static::query();

        if ($startDate) {
            $query->where('sale_date', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('sale_date', '<=', $endDate);
        }

        return $query->count();
    }
}
