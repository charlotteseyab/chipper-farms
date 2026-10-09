<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EarTagColor extends Model
{
    use BelongsToOrganization;

    protected $attributes = ['is_active' => true];

    protected $fillable = [
        'organization_id', 'name', 'normalized_name', 'short_code',
        'hex_color', 'is_active', 'created_by', 'updated_by',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function tags(): HasMany
    {
        return $this->hasMany(EarTag::class);
    }

    public static function normalizeName(string $name): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/', ' ', $name)));
    }
}
