<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EarTagAssignment extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id', 'ear_tag_id', 'pig_id', 'assigned_at', 'released_at',
        'release_reason', 'assigned_by', 'released_by', 'notes',
        'correction_reason', 'is_current',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'released_at' => 'datetime',
        'is_current' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::deleting(function () {
            throw new \LogicException('Tag assignment history cannot be deleted. Use an audited correction.');
        });
        static::updating(function (self $assignment) {
            if ($assignment->isDirty(['pig_id', 'ear_tag_id', 'organization_id'])) {
                throw new \LogicException('Historical assignment ownership is immutable.');
            }
        });
    }

    public function tag(): BelongsTo
    {
        return $this->belongsTo(EarTag::class, 'ear_tag_id');
    }

    public function pig(): BelongsTo
    {
        return $this->belongsTo(PigManagement::class, 'pig_id')->withTrashed();
    }
}
