<?php

namespace Database\Factories;

use App\Models\Medicine;
use App\Models\MedicineBatch;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MedicineBatch> */
class MedicineBatchFactory extends Factory
{
    protected $model = MedicineBatch::class;

    public function definition(): array
    {
        return [
            'medicine_id' => Medicine::factory(),
            'batch_number' => 'B'.fake()->unique()->numberBetween(10000, 99999),
            'expiry_date' => now()->addYear()->toDateString(),
            'received_at' => now()->toDateString(),
            'quantity_received' => '100',
            'unit_cost' => '2.50',
            'is_active' => true,
        ];
    }

    public function expired(): static
    {
        return $this->state(['expiry_date' => now()->subDay()->toDateString()]);
    }
}
