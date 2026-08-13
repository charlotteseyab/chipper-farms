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
        // Carried across from the cart line unchanged. An invoice has to be
        // able to explain itself years later, which means the rate, the weight
        // and the animals it was worked out from live on the ORDER, not in a
        // lookup against settings that have since moved on.
        'purchase_type', 'source_category', 'requested_kg',
        'price_per_kg_snapshot', 'line_weight_kg',
        'selected_listing_pig_ids', 'selected_pig_weight_snapshot',
        'calculation_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'quantity'   => 'decimal:4',
            'unit_price' => 'decimal:2',
            'line_total' => 'decimal:2',
            'weight_kg'  => 'decimal:4',
            'requested_kg'                 => 'decimal:4',
            'price_per_kg_snapshot'        => 'decimal:4',
            'line_weight_kg'               => 'decimal:4',
            'selected_listing_pig_ids'     => 'array',
            'selected_pig_weight_snapshot' => 'array',
            'calculation_snapshot'         => 'array',
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
