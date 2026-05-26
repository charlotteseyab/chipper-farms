<?php

namespace App\Models\Finance;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\CostCentre;
use App\Models\PigManagement;
use App\Models\SalesChannel;
use App\Models\TaxCode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class InvoiceLine extends Model
{
    use HasFactory;
    use SoftDeletes;
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'invoice_id',
        'line_number',
        'description',
        'item_code',
        'pig_id',
        'quantity',
        'unit_of_measure',
        'unit_price',
        'line_subtotal',
        'line_discount_percent',
        'line_discount_amount',
        'tax_code_id',
        'line_tax_amount',
        'line_total',
        'revenue_account_id',
        'cost_centre_id',
        'sales_channel_id',
    ];

    protected function casts(): array
    {
        return [
            'line_number'           => 'integer',
            'quantity'              => 'decimal:4',
            'unit_price'            => 'decimal:4',
            'line_subtotal'         => 'decimal:2',
            'line_discount_percent' => 'decimal:2',
            'line_discount_amount'  => 'decimal:2',
            'line_tax_amount'       => 'decimal:2',
            'line_total'            => 'decimal:2',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function pig(): BelongsTo
    {
        return $this->belongsTo(PigManagement::class, 'pig_id');
    }

    public function taxCode(): BelongsTo
    {
        return $this->belongsTo(TaxCode::class);
    }

    public function revenueAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'revenue_account_id');
    }

    public function costCentre(): BelongsTo
    {
        return $this->belongsTo(CostCentre::class);
    }

    public function salesChannel(): BelongsTo
    {
        return $this->belongsTo(SalesChannel::class);
    }
}
