<?php

namespace Database\Factories;

use App\Models\PigManagement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A live standard pig. Pass organization_id, or bind the current organisation
 * so the model stamps it.
 *
 * @extends Factory<PigManagement>
 */
class PigManagementFactory extends Factory
{
    protected $model = PigManagement::class;

    public function definition(): array
    {
        $n = fake()->unique()->numberBetween(1000, 999999);

        return [
            'ear_tag_number' => (string) $n,
            'code' => 'PIG-'.$n,
            'breed' => 'LW',
            'gender' => fake()->randomElement(['M', 'F']),
            'stock_type' => 'standard',
            'date_of_birth' => now()->subDays(120)->toDateString(),
        ];
    }

    public function breedingSow(): static
    {
        return $this->state(['stock_type' => 'breeding', 'gender' => 'F', 'date_of_birth' => now()->subYears(2)->toDateString()]);
    }
}
