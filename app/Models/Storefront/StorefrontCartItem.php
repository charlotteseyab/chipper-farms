<?php

namespace App\Models\Storefront;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StorefrontCartItem extends Model
{
    use BelongsToOrganization;

    protected $table = 'storefront_cart_items';

    protected $fillable = [
        'organization_id', 'cart_id', 'listing_id', 'line_key',
        'quantity', 'price_snapshot', 'price_basis',
        // How this line is being bought, and everything the price was worked
        // out from. Snapshots rather than lookups: a farm that changes its live
        // rate tomorrow must not silently reprice a basket filled today.
        'purchase_type', 'source_category', 'requested_kg',
        'price_per_kg_snapshot', 'line_weight_kg',
        'selected_listing_pig_ids', 'selected_pig_weight_snapshot',
        'calculation_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'quantity'                     => 'decimal:4',
            'price_snapshot'               => 'decimal:2',
            'requested_kg'                 => 'decimal:4',
            'price_per_kg_snapshot'        => 'decimal:4',
            'line_weight_kg'               => 'decimal:4',
            'selected_listing_pig_ids'     => 'array',
            'selected_pig_weight_snapshot' => 'array',
            'calculation_snapshot'         => 'array',
        ];
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(StorefrontCart::class, 'cart_id');
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(StorefrontListing::class, 'listing_id');
    }

    /** Line total = unit price snapshot × quantity (bcmath, 2dp). */
    public function lineTotal(): string
    {
        return bcmul((string) $this->price_snapshot, (string) $this->quantity, 2);
    }
}
