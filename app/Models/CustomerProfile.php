<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerProfile extends Model
{
    use HasFactory;
    use SoftDeletes;
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'counterparty_id',
        'user_id',
        'email_subscribed',
        'sms_subscribed',
        'whatsapp_subscribed',
        'default_shipping_address_id',
        'referral_code',
        'loyalty_points',
        'first_order_at',
        'last_order_at',
        'order_count',
        'lifetime_value',
    ];

    protected function casts(): array
    {
        return [
            'email_subscribed'    => 'boolean',
            'sms_subscribed'      => 'boolean',
            'whatsapp_subscribed' => 'boolean',
            'loyalty_points'      => 'integer',
            'first_order_at'      => 'datetime',
            'last_order_at'       => 'datetime',
            'order_count'         => 'integer',
            'lifetime_value'      => 'decimal:2',
        ];
    }

    public function counterparty(): BelongsTo
    {
        return $this->belongsTo(Counterparty::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function defaultShippingAddress(): BelongsTo
    {
        return $this->belongsTo(CounterpartyAddress::class, 'default_shipping_address_id');
    }
}
