<?php

namespace App\Models\Storefront;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StorefrontOrderItem extends Model
{
    use BelongsToOrganization;

    protected $table = 'storefront_order_items';

    protected $fillable = [
        'organization_id', 'order_id', 'listing_id',
        'source_type', 'source_id', 'kind', 'description',
        'quantity', 'quantity_unit', 'price_basis', 'unit_price', 'line_total', 'weight_kg',
    ];

    protected function casts(): array
    {
        return [
            'quantity'   => 'decimal:4',
            'unit_price' => 'decimal:2',
            'line_total' => 'decimal:2',
            'weight_kg'  => 'decimal:4',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(StorefrontOrder::class, 'order_id');
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(StorefrontListing::class, 'listing_id');
    }
}
