<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\PigManagement;
use App\Models\PigWeight;
use Carbon\Carbon;

class PigWeightSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get all pigs that are breeding age or close to it
        $pigs = PigManagement::where('date_of_birth', '<=', now()->subDays(150))->get();

        foreach ($pigs as $pig) {
            // Create 2-4 weight records for each pig over the past few months
            $numRecords = rand(2, 4);
            $startDate = now()->subDays(120);

            for ($i = 0; $i < $numRecords; $i++) {
                $weighDate = $startDate->copy()->addDays($i * 30 + rand(-5, 5));

                // Calculate realistic weight based on age and gender
                $ageAtWeighing = $pig->date_of_birth->diffInDays($weighDate);
                $baseWeight = $this->calculateRealisticWeight($ageAtWeighing, $pig->gender, $pig->breed);

                // Add some variation
                $weight = $baseWeight + rand(-10, 10);

                PigWeight::create([
                    'pig_id' => $pig->id,
                    'weight' => $weight,
                    'weigh_date' => $weighDate,
                    'weight_unit' => 'kg',
                    'purpose' => $this->getRandomPurpose(),
                    'weighed_by' => 'System Seeder',
                    'is_estimated' => false,
                    'body_condition_score' => rand(25, 45) / 10, // 2.5 to 4.5
                    'notes' => $i === $numRecords - 1 ? 'Latest weight record' : null
                ]);
            }
        }
    }

    private function calculateRealisticWeight($ageInDays, $gender, $breed): float
    {
        // Base weight calculation (simplified)
        $baseWeight = 2.5; // Birth weight in kg

        // Growth rate varies by gender and breed
        $dailyGrowthRate = match($gender) {
            'M' => 0.7, // Males grow faster
            'F' => 0.6, // Females grow slower
            default => 0.65
        };

        // Breed modifier
        $breedModifier = match($breed) {
            'Yorkshire' => 1.1,
            'Landrace' => 1.05,
            'Duroc' => 1.15,
            'Hampshire' => 1.0,
            default => 1.0
        };

        $weight = $baseWeight + ($ageInDays * $dailyGrowthRate * $breedModifier);

        // Cap at reasonable maximum
        return min($weight, 300);
    }

    private function getRandomPurpose(): string
    {
        $purposes = ['routine', 'health_check', 'breeding_check', 'sale_prep'];
        return $purposes[array_rand($purposes)];
    }
}
