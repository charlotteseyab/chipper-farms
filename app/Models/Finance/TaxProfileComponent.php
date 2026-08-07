<?php

namespace App\Models\Finance;

use App\Models\TaxCode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One levy inside a tax profile (Phase 2).
 *
 * Carries its own rate rather than reading it from the linked tax_code. See the
 * migration for why: a profile's meaning must not change when someone corrects
 * a tax code, or historical invoices stop being explainable.
 */
class TaxProfileComponent extends Model
{
    public const RATE_PERCENT = 'percent';
    public const RATE_FIXED   = 'fixed';

    /** Computed on the profile's taxable base. */
    public const BASE_NET = 'net';

    /** Computed on the base plus every component before it. */
    public const BASE_COMPOUND = 'compound_on_previous';

    protected $table = 'tax_profile_components';

    protected $fillable = [
        'tax_profile_id',
        'tax_code_id',
        'code',
        'label',
        'rate_type',
        'rate',
        'base_mode',
        'calculation_order',
        'is_recoverable',
        'gl_account_hint',
    ];

    protected function casts(): array
    {
        return [
            // decimal:4 keeps the DB value as a string, which is what the
            // calculator wants — casting to float here would defeat the
            // decimal arithmetic downstream.
            'rate'              => 'decimal:4',
            'calculation_order' => 'integer',
            'is_recoverable'    => 'boolean',
        ];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(TaxProfile::class, 'tax_profile_id');
    }

    public function taxCode(): BelongsTo
    {
        return $this->belongsTo(TaxCode::class);
    }

    public function isPercent(): bool
    {
        return $this->rate_type === self::RATE_PERCENT;
    }

    public function isCompound(): bool
    {
        return $this->base_mode === self::BASE_COMPOUND;
    }
}
