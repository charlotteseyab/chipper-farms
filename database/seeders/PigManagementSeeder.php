<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\PigManagement;
use Carbon\Carbon;

class PigManagementSeeder extends Seeder
{
    private $earTagCounter = 1;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create breeding stock (older pigs)
        $this->createBreedingStock();
        
        // Create standard stock (younger pigs)
        $this->createStandardStock();
    }

    private function createBreedingStock(): void
    {
        $breeds = ['LW', 'DR', 'LWDR'];
        
        // Create breeding boars (males)
        for ($i = 1; $i <= 8; $i++) {
            $breed = $breeds[array_rand($breeds)];
            $gender = 'M';
            $birthDate = Carbon::now()->subDays(rand(365, 730)); // 1-2 years old
            
            PigManagement::updateOrCreate(
                ['code' => 'BRD' . str_pad($i, 4, '0', STR_PAD_LEFT)],
                [
                    'ear_tag_number' => $this->generateUniqueEarTag($breed, $gender),
                    'breed' => $breed,
                    'gender' => $gender,
                    'stock_type' => 'breeding',
                    'date_of_birth' => $birthDate,
                    'age_in_days' => $birthDate->diffInDays(Carbon::now()),
                ]
            );
        }

        // Create breeding sows (females)
        for ($i = 1; $i <= 12; $i++) {
            $breed = $breeds[array_rand($breeds)];
            $gender = 'F';
            $birthDate = Carbon::now()->subDays(rand(365, 730)); // 1-2 years old
            
            PigManagement::updateOrCreate(
                ['code' => 'BRD' . str_pad($i + 8, 4, '0', STR_PAD_LEFT)],
                [
                    'ear_tag_number' => $this->generateUniqueEarTag($breed, $gender),
                    'breed' => $breed,
                    'gender' => $gender,
                    'stock_type' => 'breeding',
                    'date_of_birth' => $birthDate,
                    'age_in_days' => $birthDate->diffInDays(Carbon::now()),
                ]
            );
        }
    }

    private function createStandardStock(): void
    {
        $breeds = ['LW', 'DR', 'LWDR'];
        $genders = ['M', 'F'];
        
        // Create younger pigs for fattening
        for ($i = 1; $i <= 25; $i++) {
            $breed = $breeds[array_rand($breeds)];
            $gender = $genders[array_rand($genders)];
            $birthDate = Carbon::now()->subDays(rand(60, 180)); // 2-6 months old
            
            PigManagement::updateOrCreate(
                ['code' => 'STD' . str_pad($i, 4, '0', STR_PAD_LEFT)],
                [
                    'ear_tag_number' => $this->generateUniqueEarTag($breed, $gender),
                    'breed' => $breed,
                    'gender' => $gender,
                    'stock_type' => 'standard',
                    'date_of_birth' => $birthDate,
                    'age_in_days' => $birthDate->diffInDays(Carbon::now()),
                ]
            );
        }

        // Create some older standard pigs
        for ($i = 26; $i <= 35; $i++) {
            $breed = $breeds[array_rand($breeds)];
            $gender = $genders[array_rand($genders)];
            $birthDate = Carbon::now()->subDays(rand(180, 300)); // 6-10 months old
            
            PigManagement::updateOrCreate(
                ['code' => 'STD' . str_pad($i, 4, '0', STR_PAD_LEFT)],
                [
                    'ear_tag_number' => $this->generateUniqueEarTag($breed, $gender),
                    'breed' => $breed,
                    'gender' => $gender,
                    'stock_type' => 'standard',
                    'date_of_birth' => $birthDate,
                    'age_in_days' => $birthDate->diffInDays(Carbon::now()),
                ]
            );
        }
    }

    private function generateUniqueEarTag($breed, $gender): string
    {
        do {
            $earTag = $breed . $gender . str_pad($this->earTagCounter, 3, '0', STR_PAD_LEFT);
            $this->earTagCounter++;
        } while (PigManagement::where('ear_tag_number', $earTag)->exists());
        
        return $earTag;
    }
}
