<?php

namespace App\Models\Storefront;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StorefrontCart extends Model
{
    use BelongsToOrganization;

    protected $table = 'storefront_carts';

    public const STATUS_ACTIVE    = 'active';
    public const STATUS_CONVERTED = 'converted';
    public const STATUS_ABANDONED = 'abandoned';

    protected $fillable = ['organization_id', 'user_id', 'status'];

    public function items(): HasMany
    {
        return $this->hasMany(StorefrontCartItem::class, 'cart_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function subtotal(): string
    {
        $total = '0.00';
        foreach ($this->items as $item) {
            $total = bcadd($total, $item->lineTotal(), 2);
        }
        return $total;
    }
}
