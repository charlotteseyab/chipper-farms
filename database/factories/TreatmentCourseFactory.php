<?php

namespace Database\Factories;

use App\Models\TreatmentCourse;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TreatmentCourse> */
class TreatmentCourseFactory extends Factory
{
    protected $model = TreatmentCourse::class;

    public function definition(): array
    {
        return ['purpose' => 'treatment', 'prescriber_name' => 'Dr Mensah', 'status' => 'active'];
    }

    public function vaccination(): static
    {
        return $this->state(['purpose' => 'vaccination']);
    }
}
