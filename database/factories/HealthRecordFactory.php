<?php

namespace Database\Factories;

use App\Models\HealthRecord;
use App\Models\PigManagement;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<HealthRecord> */
class HealthRecordFactory extends Factory
{
    protected $model = HealthRecord::class;

    public function definition(): array
    {
        $date = now()->subDays(3);

        return [
            'pig_id' => PigManagement::factory(),
            'record_date' => $date->toDateString(),
            'observed_at' => $date->setTime(8, 0),
            'condition' => 'Cough',
            'severity' => 'mild',
            'symptoms' => 'Dry cough, eating normally',
            'case_status' => 'open',
            'scope' => HealthRecord::SCOPE_INDIVIDUAL,
        ];
    }
}
