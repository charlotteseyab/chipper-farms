<?php

namespace App\Models;

use App\Support\Features\FeatureCatalog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single feature's state for a single organisation (Phase 1, blueprint §32).
 *
 * The row is the record of an explicit decision. Absence of a row means "never
 * configured", which the service resolves to the catalogue default — not to
 * false. See the migration for why that distinction matters.
 */
class OrganizationFeatureFlag extends Model
{
    use \App\Models\Concerns\BelongsToOrganization;

    protected $table = 'organization_feature_flags';

    protected $fillable = [
        'organization_id',
        'feature_key',
        'enabled',
        'configuration',
        'applied_preset',
        'enabled_by',
        'enabled_at',
        'disabled_by',
        'disabled_at',
    ];

    protected function casts(): array
    {
        return [
            'enabled'       => 'boolean',
            'configuration' => 'array',
            'enabled_at'    => 'datetime',
            'disabled_at'   => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function enabledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enabled_by');
    }

    public function disabledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disabled_by');
    }

    /**
     * Catalogue metadata for this row, or null if the key is no longer known.
     *
     * Returning null rather than throwing is deliberate: a feature can be
     * removed from the catalogue in a later release while historical rows still
     * exist, and a settings screen listing those rows must not fatal.
     *
     * @return array{label:string,description:string,domain:string,category:string,default:bool,requires:list<string>,backs:string}|null
     */
    public function definition(): ?array
    {
        return FeatureCatalog::isKnown($this->feature_key)
            ? FeatureCatalog::definition($this->feature_key)
            : null;
    }

    public function label(): string
    {
        return $this->definition()['label'] ?? $this->feature_key;
    }

    public function scopeForOrganization($query, int $organizationId)
    {
        return $query->where('organization_id', $organizationId);
    }

    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }
}
