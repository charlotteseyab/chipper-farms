<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Group extends Model
{
    protected $fillable = [
        'name',
        'type',
        'description',
    ];

    public function pigs(): HasMany
    {
        return $this->hasMany(PigManagement::class, 'group_id');
    }

    public function groupFeedings(): HasMany
    {
        return $this->hasMany(GroupFeeding::class);
    }
}
