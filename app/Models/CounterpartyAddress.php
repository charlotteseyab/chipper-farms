<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CounterpartyAddress extends Model
{
    use HasFactory;
    use SoftDeletes;
    use BelongsToOrganization;

    public const TYPE_BILLING  = 'billing';
    public const TYPE_SHIPPING = 'shipping';
    public const TYPE_PICKUP   = 'pickup';
    public const TYPE_OFFICE   = 'office';
    public const TYPE_OTHER    = 'other';

    protected $fillable = [
        'organization_id',
        'counterparty_id',
        'address_type',
        'label',
        'attention_to',
        'address_line_1',
        'address_line_2',
        'city',
        'region',
        'postal_code',
        'country',
        'latitude',
        'longitude',
        'ghana_post_gps',
        'is_default',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'latitude'   => 'decimal:7',
            'longitude'  => 'decimal:7',
            'is_default' => 'boolean',
            'is_active'  => 'boolean',
        ];
    }

    public function counterparty(): BelongsTo
    {
        return $this->belongsTo(Counterparty::class);
    }

    public function scopeOfType(Builder $q, string $type): Builder
    {
        return $q->where('address_type', $type);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function scopeDefault(Builder $q): Builder
    {
        return $q->where('is_default', true);
    }

    /**
     * Render the address as a single string (for display + invoicing).
     */
    public function asSingleLine(): string
    {
        $parts = array_filter([
            $this->attention_to ? 'c/o ' . $this->attention_to : null,
            $this->address_line_1,
            $this->address_line_2,
            $this->city,
            $this->region,
            $this->postal_code,
            $this->ghana_post_gps,
        ]);
        return implode(', ', $parts);
    }
}
