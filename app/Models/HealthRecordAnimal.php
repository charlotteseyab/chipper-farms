<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\SetsOrganizationFromPig;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One animal in a health case's frozen membership. */
class HealthRecordAnimal extends Model
{
    use BelongsToOrganization, SetsOrganizationFromPig;

    protected $fillable = ['organization_id', 'health_record_id', 'pig_id', 'tag_label_snapshot'];

    public function healthRecord(): BelongsTo
    {
        return $this->belongsTo(HealthRecord::class);
    }

    public function pig(): BelongsTo
    {
        return $this->belongsTo(PigManagement::class, 'pig_id')->withTrashed();
    }
}
