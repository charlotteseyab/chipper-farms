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
        'source_type', 'source_id', 'posted_sale_type', 'posted_sale_id', 'kind', 'description',
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

    /**
     * The exact animals an approver committed to this line.
     *
     * A line for three pigs has three allocations and produces three
     * `pig_sales` rows, which is why `posted_sale_id` cannot carry this.
     */
    public function pigAllocations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(StorefrontOrderItemPigAllocation::class, 'storefront_order_item_id');
    }

    /**
     * The real sales this line produced, read through the allocations.
     *
     * `/sales` remains the source of truth; this is the join that lets an order
     * line point at it.
     */
    public function pigSales(): \Illuminate\Database\Eloquent\Relations\HasManyThrough
    {
        return $this->hasManyThrough(
            \App\Models\PigSale::class,
            StorefrontOrderItemPigAllocation::class,
            'storefront_order_item_id',
            'id',
            'id',
            'pig_sale_id',
        );
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
