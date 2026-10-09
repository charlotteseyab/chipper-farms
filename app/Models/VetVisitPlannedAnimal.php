<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** An animal the farm planned for the vet to see. Never counted as examined. */
class VetVisitPlannedAnimal extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['organization_id', 'vet_visit_id', 'pig_id'];

    public function visit(): BelongsTo
    {
        return $this->belongsTo(VetVisit::class, 'vet_visit_id');
    }

    public function pig(): BelongsTo
    {
        return $this->belongsTo(PigManagement::class, 'pig_id')->withTrashed();
    }
}
