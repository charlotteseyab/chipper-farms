<?php

namespace App\Models\Finance;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\SalesReturn;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditNoteLine extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'credit_note_id',
        'original_invoice_line_id',
        'original_order_item_id',
        'sales_return_id',
        'returnable_type',
        'returnable_id',
        'description',
        'quantity',
        'unit_price',
        'tax_amount',
        'line_total',
        'revenue_account_id',
        'quantity_sold',
        'quantity_previously_credited',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_price' => 'decimal:4',
            'tax_amount' => 'decimal:2',
            'line_total' => 'decimal:2',
            'quantity_sold' => 'decimal:4',
            'quantity_previously_credited' => 'decimal:4',
        ];
    }

    public function creditNote(): BelongsTo
    {
        return $this->belongsTo(CreditNote::class);
    }

    public function salesReturn(): BelongsTo
    {
        return $this->belongsTo(SalesReturn::class);
    }

    public function revenueAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'revenue_account_id');
    }
}
