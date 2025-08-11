<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class PigSale extends Model
{
    protected $fillable = [
        'pig_id',
        'sale_date',
        'sale_price',
        'sale_weight',
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
        'sale_documents'
    ];

    protected $casts = [
        'sale_date' => 'date',
        'sale_price' => 'decimal:2',
        'sale_weight' => 'decimal:2',
        'transport_cost' => 'decimal:2',
        'sale_documents' => 'array'
    ];

    // Relationships
    public function pig()
    {
        return $this->belongsTo(PigManagement::class, 'pig_id');
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
        return '$' . number_format($this->sale_price, 2);
    }

    public function getFormattedSaleWeightAttribute(): string
    {
        return $this->sale_weight ? number_format($this->sale_weight, 1) . ' kg' : 'Not recorded';
    }

    public function getPricePerKgAttribute(): ?float
    {
        if (!$this->sale_weight || $this->sale_weight <= 0) {
            return null;
        }
        return $this->sale_price / $this->sale_weight;
    }

    public function getFormattedPricePerKgAttribute(): string
    {
        $pricePerKg = $this->price_per_kg;
        return $pricePerKg ? '$' . number_format($pricePerKg, 2) . '/kg' : 'N/A';
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
