<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Group extends Model
{
    use \App\Models\Concerns\BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'name',
        'type',
        'feeding_stage',
        'structure_id',
        'description',
    ];

    /** Groups that hold real animals, excluding the per-stage feeding groups. */
    public function scopeAnimalGroups($query)
    {
        return $query->whereNull('feeding_stage');
    }

    public function pigs(): HasMany
    {
        return $this->hasMany(PigManagement::class, 'group_id');
    }

    public function structure(): BelongsTo
    {
        return $this->belongsTo(Structure::class);
    }
}
