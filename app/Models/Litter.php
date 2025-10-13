<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The Litter model tracks a single birth event, connecting back to the Sow (PigManagement).
 * This allows us to track the age and status of the piglet group before individual IDs are assigned.
 */
class Litter extends Model
{
    // If your Litters table is not named 'litters', you would set:
    // protected $table = 'pig_litters';

    protected $fillable = [
        'sow_id',
        'date_of_birth',
        'number_born_live',
        'number_weaned',
        'date_weaned',
        'active', // Used by the query for active litters
    ];

    /**
     * A litter belongs to a single mother sow (PigManagement record).
     */
    public function sow()
    {
        // Assuming the foreign key in the 'litters' table is 'sow_id'
        return $this->belongsTo(PigManagement::class, 'sow_id');
    }
}
