<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Finance\Account;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerCategory extends Model
{
    use HasFactory;
    use SoftDeletes;
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'code',
        'name',
        'description',
        'revenue_account_id',
        'ar_account_id',
        'default_payment_terms_days',
        'default_credit_limit',
        'default_discount_percent',
        'is_active',
        'display_order',
    ];

    protected function casts(): array
    {
        return [
            'default_payment_terms_days' => 'integer',
            'default_credit_limit'       => 'decimal:2',
            'default_discount_percent'   => 'decimal:2',
            'is_active'                  => 'boolean',
            'display_order'              => 'integer',
        ];
    }

    public function revenueAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'revenue_account_id');
    }

    public function arAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'ar_account_id');
    }

    public function counterparties(): HasMany
    {
        return $this->hasMany(Counterparty::class, 'customer_category_id');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true)
                 ->orderBy('display_order')
                 ->orderBy('name');
    }
}
