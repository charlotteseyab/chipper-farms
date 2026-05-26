<?php

namespace App\Models\Finance;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\PurchaseRequestItem;
use App\Models\RawMaterial;
use App\Models\TaxCode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class VendorInvoiceLine extends Model
{
    use HasFactory;
    use SoftDeletes;
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'vendor_invoice_id',
        'line_number',
        'description',
        'raw_material_id',
        'purchase_request_item_id',
        'quantity',
        'unit',
        'unit_cost',
        'line_subtotal',
        'tax_code_id',
        'tax_rate',
        'tax_amount',
        'withholding_tax_code_id',
        'withholding_rate',
        'withholding_amount',
        'line_total',
        'account_id',
    ];

    protected function casts(): array
    {
        return [
            'line_number'         => 'integer',
            'quantity'            => 'decimal:4',
            'unit_cost'           => 'decimal:4',
            'line_subtotal'       => 'decimal:2',
            'tax_rate'            => 'decimal:4',
            'tax_amount'          => 'decimal:2',
            'withholding_rate'    => 'decimal:4',
            'withholding_amount'  => 'decimal:2',
            'line_total'          => 'decimal:2',
        ];
    }

    public function vendorInvoice(): BelongsTo
    {
        return $this->belongsTo(VendorInvoice::class);
    }

    public function rawMaterial(): BelongsTo
    {
        return $this->belongsTo(RawMaterial::class);
    }

    public function purchaseRequestItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequestItem::class);
    }

    public function taxCode(): BelongsTo
    {
        return $this->belongsTo(TaxCode::class);
    }

    public function withholdingTaxCode(): BelongsTo
    {
        return $this->belongsTo(TaxCode::class, 'withholding_tax_code_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
