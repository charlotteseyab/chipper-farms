<?php

namespace Database\Factories;

use App\Models\Medicine;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Medicine> */
class MedicineFactory extends Factory
{
    protected $model = Medicine::class;

    public function definition(): array
    {
        return [
            'name' => 'Oxytetracycline LA '.fake()->unique()->numberBetween(1, 99999),
            'formulation' => 'Injectable solution',
            'concentration' => '200 mg/ml',
            'dose_unit' => 'ml',
            'stock_unit' => 'ml',
            'dose_units_per_stock_unit' => '1',
            'allowed_routes' => ['Injection (IM)'],
            'meat_withdrawal_days' => 28,
            'withdrawal_source_note' => 'Product label',
            'is_active' => true,
        ];
    }

    /** A vaccine sold in 50-dose vials. */
    public function vialOf50Doses(): static
    {
        return $this->state(['dose_unit' => 'dose', 'stock_unit' => 'vial', 'dose_units_per_stock_unit' => '50', 'meat_withdrawal_days' => 0]);
    }
}
