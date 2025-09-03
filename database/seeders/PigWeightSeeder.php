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
        // Get all pigs
        $pigs = PigManagement::all();

        foreach ($pigs as $pig) {
            // Create weight records for each pig
            $this->createWeightRecordsForPig($pig);
        }
    }

    private function createWeightRecordsForPig($pig): void
    {
        $birthDate = Carbon::parse($pig->date_of_birth);
        $currentDate = Carbon::now();
        $ageInDays = $birthDate->diffInDays($currentDate);

        // Don't create weights for very young pigs (less than 30 days)
        if ($ageInDays < 30) {
            return;
        }

        // Determine number of weight records based on age
        $numRecords = min(5, max(2, intval($ageInDays / 30))); // 2-5 records
        $startDate = $birthDate->copy()->addDays(30); // Start from 30 days old

        for ($i = 0; $i < $numRecords; $i++) {
            $weighDate = $startDate->copy()->addDays($i * 30 + rand(-7, 7));

            // Don't create future weights
            if ($weighDate->isAfter($currentDate)) {
                continue;
            }

            // Calculate realistic weight based on age and gender
            $ageAtWeighing = $birthDate->diffInDays($weighDate);
            $baseWeight = $this->calculateRealisticWeight($ageAtWeighing, $pig->gender, $pig->breed);

            // Add some variation (±5%)
            $variation = $baseWeight * 0.05;
            $weight = $baseWeight + rand(-$variation, $variation);

            PigWeight::create([
                'pig_id' => $pig->id,
                'weight' => round($weight, 1),
                'weigh_date' => $weighDate,
                'target_week' => $this->calculateTargetWeek($ageAtWeighing),
                'purpose' => $this->getRandomPurpose(),
                'body_condition_score' => $this->getRandomBodyConditionScore(),
                'health_status' => $this->getRandomHealthStatus(),
                'notes' => $this->getRandomNotes($i, $numRecords),
            ]);
        }
    }

    private function calculateRealisticWeight($ageInDays, $gender, $breed): float
    {
        // Birth weight
        $weight = 1.5;

        // Growth phases
        if ($ageInDays <= 30) {
            // Suckling phase - rapid growth
            $dailyGrowth = 0.3;
        } elseif ($ageInDays <= 90) {
            // Weaning phase - moderate growth
            $dailyGrowth = 0.5;
        } elseif ($ageInDays <= 180) {
            // Growing phase - steady growth
            $dailyGrowth = 0.7;
        } else {
            // Finishing phase - slower growth
            $dailyGrowth = 0.4;
        }

        // Gender modifier
        $genderModifier = ($gender === 'M') ? 1.1 : 1.0;

        // Breed modifier
        $breedModifier = match($breed) {
            'LW' => 1.0,    // Large White - standard
            'DR' => 1.15,   // Duroc - heavier
            'LWDR' => 1.05, // Cross - slightly heavier
            default => 1.0
        };

        $weight += ($ageInDays * $dailyGrowth * $genderModifier * $breedModifier);

        // Cap at reasonable maximum
        return min($weight, 350);
    }

    private function calculateTargetWeek($ageInDays): int
    {
        return max(1, intval($ageInDays / 7));
    }

    private function getRandomPurpose(): string
    {
        $purposes = ['routine', 'breeding', 'health', 'sale', 'birth', 'weaning', 'maturity', 'other'];
        return $purposes[array_rand($purposes)];
    }

    private function getRandomBodyConditionScore(): ?int
    {
        // 70% chance to have a body condition score
        if (rand(1, 10) <= 7) {
            return rand(2, 5);
        }
        return null;
    }

    private function getRandomHealthStatus(): ?string
    {
        $statuses = ['excellent', 'good', 'fair', 'poor'];
        return $statuses[array_rand($statuses)];
    }

    private function getRandomNotes($recordIndex, $totalRecords): ?string
    {
        if ($recordIndex === $totalRecords - 1) {
            return 'Latest weight record';
        }

        $notes = [
            'Regular monitoring',
            'Good growth observed',
            'Healthy condition',
            'Standard weight check',
            null, // 20% chance of no notes
        ];

        return $notes[array_rand($notes)];
    }
}
