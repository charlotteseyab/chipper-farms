<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class EarTag extends Model
{
    use BelongsToOrganization;

    protected $attributes = ['status' => self::AVAILABLE];

    public const AVAILABLE = 'available';
    public const IN_USE = 'in_use';
    public const PENDING_RECOVERY = 'pending_recovery';
    public const LOST = 'lost';
    public const DAMAGED = 'damaged';
    public const RESERVED = 'reserved';
    public const RETIRED = 'retired';

    public const STATUSES = [
        self::AVAILABLE, self::IN_USE, self::PENDING_RECOVERY, self::LOST,
        self::DAMAGED, self::RESERVED, self::RETIRED,
    ];

    protected $fillable = [
        'organization_id', 'ear_tag_color_id', 'tag_number',
        'normalized_tag_number', 'status', 'condition_notes', 'reserved_for',
        'created_by', 'updated_by',
    ];

    public function color(): BelongsTo
    {
        return $this->belongsTo(EarTagColor::class, 'ear_tag_color_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(EarTagAssignment::class);
    }

    public function currentAssignment(): HasOne
    {
        return $this->hasOne(EarTagAssignment::class)->where('is_current', 1);
    }

    public static function normalizeNumber(string $number): string
    {
        $number = trim($number);

        return ctype_digit($number) ? (ltrim($number, '0') ?: '0') : mb_strtoupper($number);
    }
}
