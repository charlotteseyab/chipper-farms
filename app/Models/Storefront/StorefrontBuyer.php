<?php

namespace App\Models\Storefront;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Counterparty;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;

class StorefrontBuyer extends Model
{
    use BelongsToOrganization;
    use Notifiable;
    use SoftDeletes;

    protected $table = 'storefront_buyers';

    protected $fillable = [
        'organization_id',
        'counterparty_id',
        'name',
        'email',
        'phone',
        'password',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'last_login_at' => 'datetime',
        ];
    }

    public function counterparty(): BelongsTo
    {
        return $this->belongsTo(Counterparty::class, 'counterparty_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(StorefrontOrder::class, 'storefront_buyer_id');
    }

}
