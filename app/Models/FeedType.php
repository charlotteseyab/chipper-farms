<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class FeedType extends Model
{
    protected $fillable = [
        'name',
        'description',
        'protein_percent',
        'energy_mj_per_kg',
        'fiber_percent',
        'cost_per_unit',
        'unit',
    ];
}
