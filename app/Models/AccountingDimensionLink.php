<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Polymorphic link between any finance-source document and any accounting
 * dimension (cost centre, sales channel, structure, room, group).
 *
 * Written by source controllers (Phase 1) when tagging a document against
 * one or more dimensions. Read by reports when slicing by dimension.
 *
 * Split-allocation: a single source row can have multiple links of the same
 * dimension_type pointing at different dimension_ids, each with an
 * allocation_percentage summing to 100.
 *
 * The actual dimension entity (CostCentre, SalesChannel, Structure, Room,
 * Group) is resolved by dimension_type + dimension_id. Since dimensions are
 * heterogeneous, we don't model this as a standard Eloquent morphTo — instead
 * expose a dimension() accessor that resolves the right model by type string.
 */
class AccountingDimensionLink extends Model
{
    use HasFactory;

    protected $table = 'accounting_dimension_links';

    public const DIMENSION_COST_CENTRE   = 'cost_centre';
    public const DIMENSION_SALES_CHANNEL = 'sales_channel';
    public const DIMENSION_STRUCTURE     = 'structure';
    public const DIMENSION_ROOM          = 'room';
    public const DIMENSION_GROUP         = 'group';

    protected $fillable = [
        'organization_id',
        'documentable_type',
        'documentable_id',
        'dimension_type',
        'dimension_id',
        'allocation_percentage',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'allocation_percentage' => 'decimal:4',
        ];
    }

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Resolve the dimension entity instance for this link.
     * Returns null if the dimension type is unknown.
     */
    public function getDimensionAttribute(): ?Model
    {
        return match ($this->dimension_type) {
            self::DIMENSION_COST_CENTRE   => CostCentre::find($this->dimension_id),
            self::DIMENSION_SALES_CHANNEL => SalesChannel::find($this->dimension_id),
            self::DIMENSION_STRUCTURE     => class_exists(\App\Models\Structure::class)
                ? \App\Models\Structure::find($this->dimension_id)
                : null,
            self::DIMENSION_ROOM          => class_exists(\App\Models\Room::class)
                ? \App\Models\Room::find($this->dimension_id)
                : null,
            self::DIMENSION_GROUP         => class_exists(\App\Models\Group::class)
                ? \App\Models\Group::find($this->dimension_id)
                : null,
            default => null,
        };
    }
}