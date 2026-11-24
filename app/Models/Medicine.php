<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A medicine in the farm's catalogue. Withdrawal days come from the label,
 * entered by someone with the catalogue permission; blank means none was
 * recorded, which is shown as a caution and never treated as safe. Doses copy
 * these values when given, so editing a medicine never changes past doses.
 */
class Medicine extends Model
{
    use BelongsToOrganization, HasFactory, \App\Models\Concerns\HasAttachments;

    protected $fillable = [
        'organization_id', 'name', 'formulation', 'concentration', 'description', 'used_for', 'storage_instructions', 'precautions', 'dose_unit', 'stock_unit',
        'dose_units_per_stock_unit', 'allowed_routes', 'meat_withdrawal_days', 'withdrawal_source_note',
        'reorder_level', 'is_active', 'created_by',
    ];

    protected $casts = [
        'dose_units_per_stock_unit' => 'decimal:4', 'allowed_routes' => 'array',
        'meat_withdrawal_days' => 'integer', 'reorder_level' => 'decimal:4', 'is_active' => 'boolean',
    ];

    public function batches(): HasMany
    {
        return $this->hasMany(MedicineBatch::class);
    }

    /** The current photo of the pack or bottle, so staff can check they have the right one. */
    public function photo(): \Illuminate\Database\Eloquent\Relations\MorphOne
    {
        return $this->morphOne(\App\Models\Attachment::class, 'attachable')->where('category', 'health_medicine')->latestOfMany();
    }

    public function getDisplayNameAttribute(): string
    {
        return trim($this->name.' '.$this->concentration.($this->formulation !== '' ? ' ('.$this->formulation.')' : ''));
    }
}
