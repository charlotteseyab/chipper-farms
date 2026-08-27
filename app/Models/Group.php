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
        'structure_id',
        'description',
    ];

    public function pigs(): HasMany
    {
        return $this->hasMany(PigManagement::class, 'group_id');
    }

    public function structure(): BelongsTo
    {
        return $this->belongsTo(Structure::class);
    }
}
