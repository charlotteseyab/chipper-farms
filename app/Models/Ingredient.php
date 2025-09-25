<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ingredient extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'description', 'protein_percentage', 'energy_kcal_per_kg', 'fat_percentage', 'fiber_percentage', 'unit', 'cost_per_unit'
    ];
}
