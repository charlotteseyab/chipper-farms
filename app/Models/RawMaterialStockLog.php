<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RawMaterialStockLog extends Model
{
    protected $fillable = [
        'raw_material_id',
        'type',
        'quantity',
        'unit_cost',
        'reference',
        'notes',
        'performed_by',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_cost' => 'decimal:2',
    ];

    public function rawMaterial()
    {
        return $this->belongsTo(RawMaterial::class);
    }

    public function performer()
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}