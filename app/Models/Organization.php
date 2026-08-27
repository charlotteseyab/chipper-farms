<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Represents a tenant (organization) within the platform.
 *
 * Chipper Farms deployments are currently single-tenant in practice, but this
 * model exists from day one so every business/finance table can carry an
 * organization_id reference. When a second tenant is onboarded no schema
 * retrofit is required.
 */
class Organization extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $attributes = [
        'base_currency' => 'GHS',
        'currency_decimals' => 2,
        'country_code' => 'GH',
        'timezone' => 'Africa/Accra',
        'is_active' => true,
    ];

    protected $fillable = [
        'slug',
        'name',
        'legal_name',
        'base_currency',
        'currency_decimals',
        'country_code',
        'timezone',
        'email',
        'phone',
        'address',
        'tax_identifier',
        'is_active',
        'deletion_reason',
        'deleted_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'currency_decimals' => 'integer',
        ];
    }

    /**
     * Users belonging to this organization.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function deletedBy()
    {
        return $this->belongsTo(User::class, 'deleted_by_user_id');
    }

    /**
     * Normalised slug helper — used when creating or looking up orgs.
     */
    public static function normalizeSlug(string $value): string
    {
        return \Illuminate\Support\Str::slug(trim($value));
    }

    /**
     * Scope: only active, non-deleted organizations.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
