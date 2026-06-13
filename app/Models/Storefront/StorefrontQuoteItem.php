<?php

namespace App\Models\Storefront;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StorefrontQuoteItem extends Model
{
    use HasFactory;
    use BelongsToOrganization;

    protected $table = 'storefront_quote_items';

    protected $fillable = [
        'organization_id',
        'quote_id',
        'listing_id',
        'kind',
        'description',
        'quantity',
        'quantity_unit',
        'target_unit_price',
        'line_note',
    ];

    protected function casts(): array
    {
        return [
            'quantity'          => 'decimal:4',
            'target_unit_price' => 'decimal:4',
        ];
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(StorefrontQuote::class, 'quote_id');
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(StorefrontListing::class, 'listing_id');
    }
}
