<?php

namespace App\Models\Storefront;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StorefrontOrderEvent extends Model
{
    use BelongsToOrganization;

    protected $table = 'storefront_order_events';

    public const TYPE_STATUS_CHANGE = 'status_change';
    public const TYPE_PAYMENT       = 'payment';
    public const TYPE_NOTE          = 'note';
    public const TYPE_SYSTEM        = 'system';

    protected $fillable = [
        'organization_id', 'order_id', 'type', 'from_status', 'to_status', 'note', 'actor_user_id',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(StorefrontOrder::class, 'order_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
