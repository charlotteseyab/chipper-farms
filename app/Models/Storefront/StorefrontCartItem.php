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
        'organization_id', 'cart_id', 'listing_id',
        'quantity', 'price_snapshot', 'price_basis',
    ];

    protected function casts(): array
    {
        return [
            'quantity'       => 'decimal:4',
            'price_snapshot' => 'decimal:2',
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
