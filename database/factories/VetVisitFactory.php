<?php

namespace Database\Factories;

use App\Models\VetVisit;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<VetVisit> */
class VetVisitFactory extends Factory
{
    protected $model = VetVisit::class;

    public function definition(): array
    {
        return [
            'visited_at' => now()->subDay()->setTime(10, 0),
            'veterinarian_name' => 'Dr '.fake()->lastName(),
            'clinic' => 'District Veterinary Office',
            'reason' => 'Routine herd check',
            'status' => VetVisit::STATUS_DRAFT,
        ];
    }
}
