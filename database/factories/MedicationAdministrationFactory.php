<?php

namespace Database\Factories;

use App\Models\MedicationAdministration;
use App\Models\PigManagement;
use Illuminate\Database\Eloquent\Factories\Factory;

/** A legacy-style dose (no medicine catalogue, no stock). @extends Factory<MedicationAdministration> */
class MedicationAdministrationFactory extends Factory
{
    protected $model = MedicationAdministration::class;

    public function definition(): array
    {
        return [
            'pig_id' => PigManagement::factory(),
            'administration_date' => now()->subDay()->toDateString(),
            'medication_name' => 'Oxytetracycline LA',
            'dosage' => '5 ml',
            'route' => 'Injection (IM)',
            'withdrawal_period_days' => 28,
            'status' => MedicationAdministration::STATUS_RECORDED,
        ];
    }
}
