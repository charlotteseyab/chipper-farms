<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    use HasFactory, \App\Models\Concerns\BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'code',
        'name',
        'description',
        'is_cash',
        'is_bank',
        'is_mobile_money',
        'is_credit',
        'requires_reference',
        'gl_account_hint',
        'display_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_cash'            => 'boolean',
            'is_bank'            => 'boolean',
            'is_mobile_money'    => 'boolean',
            'is_credit'          => 'boolean',
            'requires_reference' => 'boolean',
            'display_order'      => 'integer',
            'is_active'          => 'boolean',
        ];
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true)->orderBy('display_order')->orderBy('name');
    }
}
