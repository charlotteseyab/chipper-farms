<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RawMaterial extends Model
{
    protected $fillable = [
        'name',
        'category',
        'unit',
        'quantity_in_stock',
        'bag_price',
        'bag_weight_kg',
        'cost_per_kg',
        'reorder_level',
        'notes',
    ];

    protected $casts = [
        'quantity_in_stock' => 'decimal:2',
        'bag_price' => 'decimal:2',
        'bag_weight_kg' => 'decimal:2',
        'cost_per_kg' => 'decimal:4',
        'reorder_level' => 'decimal:2',
    ];

    public function stockLogs()
    {
        return $this->hasMany(RawMaterialStockLog::class);
    }

    public function getStockStatusAttribute(): string
    {
        if ((float) $this->quantity_in_stock <= 0) {
            return 'out_of_stock';
        }

        if ((float) $this->quantity_in_stock <= (float) $this->reorder_level) {
            return 'low_stock';
        }

        return 'in_stock';
    }

    public function getStockValueAttribute(): float
    {
        return (float) $this->quantity_in_stock * (float) $this->cost_per_kg;
    }
}