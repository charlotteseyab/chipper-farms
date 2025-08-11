<?php

namespace App\Helpers;

use Carbon\Carbon;

class BreedingCalculator
{
    /**
     * Calculate expected farrowing date from mating date
     */
    public static function calculateFarrowingDate($matingDate): Carbon
    {
        return Carbon::parse($matingDate)->addDays(config('breeding.cycle.gestation_days'));
    }

    /**
     * Calculate pregnancy check date from mating date
     */
    public static function calculatePregnancyCheckDate($matingDate): Carbon
    {
        return Carbon::parse($matingDate)->addDays(config('breeding.cycle.pregnancy_check_days'));
    }

    /**
     * Calculate weaning date from birth date
     */
    public static function calculateWeaningDate($birthDate): Carbon
    {
        return Carbon::parse($birthDate)->addDays(config('breeding.cycle.birth_to_weaning_days'));
    }

    /**
     * Calculate maturity date from birth date
     */
    public static function calculateMaturityDate($birthDate): Carbon
    {
        return Carbon::parse($birthDate)->addDays(config('breeding.cycle.birth_to_maturity_days'));
    }

    /**
     * Calculate next available mating date after weaning
     */
    public static function calculateNextMatingDate($weaningDate): Carbon
    {
        return Carbon::parse($weaningDate)->addDays(config('breeding.parameters.weaning_to_mating_rest'));
    }

    /**
     * Check if a pig is old enough for breeding
     */
    public static function isBreedingAge($birthDate): bool
    {
        $age = Carbon::parse($birthDate)->diffInDays(now());
        return $age >= config('breeding.parameters.minimum_breeding_age');
    }

    /**
     * Check if a pig is too old for breeding
     */
    public static function isTooOldForBreeding($birthDate): bool
    {
        $age = Carbon::parse($birthDate)->diffInDays(now());
        return $age > config('breeding.parameters.maximum_breeding_age');
    }

    /**
     * Get breeding status for a pig based on age
     */
    public static function getBreedingStatus($birthDate): array
    {
        $age = Carbon::parse($birthDate)->diffInDays(now());
        $minAge = config('breeding.parameters.minimum_breeding_age');
        $maxAge = config('breeding.parameters.maximum_breeding_age');

        if ($age < $minAge) {
            $daysToMaturity = $minAge - $age;
            return [
                'status' => 'immature',
                'message' => "Too young for breeding. Ready in {$daysToMaturity} days.",
                'ready_date' => self::calculateMaturityDate($birthDate),
                'can_breed' => false
            ];
        }

        if ($age > $maxAge) {
            return [
                'status' => 'retired',
                'message' => 'Too old for breeding.',
                'ready_date' => null,
                'can_breed' => false
            ];
        }

        return [
            'status' => 'ready',
            'message' => 'Ready for breeding.',
            'ready_date' => Carbon::parse($birthDate)->addDays($minAge),
            'can_breed' => true
        ];
    }

    /**
     * Calculate breeding cycle timeline for a mating
     */
    public static function getBreedingTimeline($matingDate): array
    {
        $mating = Carbon::parse($matingDate);
        
        return [
            'mating_date' => $mating,
            'pregnancy_check_date' => self::calculatePregnancyCheckDate($mating),
            'expected_farrowing_date' => self::calculateFarrowingDate($mating),
            'expected_weaning_date' => self::calculateWeaningDate(self::calculateFarrowingDate($mating)),
            'next_mating_available' => self::calculateNextMatingDate(
                self::calculateWeaningDate(self::calculateFarrowingDate($mating))
            ),
            'cycle_length_days' => self::calculateNextMatingDate(
                self::calculateWeaningDate(self::calculateFarrowingDate($mating))
            )->diffInDays($mating)
        ];
    }

    /**
     * Get upcoming events for a mating
     */
    public static function getUpcomingEvents($matingDate, $status = 'planned'): array
    {
        $timeline = self::getBreedingTimeline($matingDate);
        $events = [];
        $now = now();

        // Pregnancy check event
        if ($status === 'planned' || $status === 'completed') {
            $pregnancyCheck = $timeline['pregnancy_check_date'];
            if ($pregnancyCheck->isFuture()) {
                $events[] = [
                    'type' => 'pregnancy_check',
                    'date' => $pregnancyCheck,
                    'days_until' => $pregnancyCheck->diffInDays($now),
                    'title' => 'Pregnancy Check Due',
                    'description' => 'Check if mating was successful',
                    'priority' => 'high'
                ];
            }
        }

        // Farrowing event
        if ($status === 'completed') {
            $farrowing = $timeline['expected_farrowing_date'];
            if ($farrowing->isFuture()) {
                $daysUntil = $farrowing->diffInDays($now);
                $priority = $daysUntil <= config('breeding.alerts.farrowing_alert') ? 'urgent' : 'medium';
                
                $events[] = [
                    'type' => 'farrowing',
                    'date' => $farrowing,
                    'days_until' => $daysUntil,
                    'title' => 'Expected Farrowing',
                    'description' => 'Prepare farrowing pen and monitor sow',
                    'priority' => $priority
                ];
            }

            // Weaning event
            $weaning = $timeline['expected_weaning_date'];
            if ($weaning->isFuture()) {
                $daysUntil = $weaning->diffInDays($now);
                $priority = $daysUntil <= config('breeding.alerts.weaning_alert') ? 'high' : 'low';
                
                $events[] = [
                    'type' => 'weaning',
                    'date' => $weaning,
                    'days_until' => $daysUntil,
                    'title' => 'Weaning Due',
                    'description' => 'Separate piglets from mother',
                    'priority' => $priority
                ];
            }

            // Next mating available
            $nextMating = $timeline['next_mating_available'];
            if ($nextMating->isFuture()) {
                $events[] = [
                    'type' => 'next_mating',
                    'date' => $nextMating,
                    'days_until' => $nextMating->diffInDays($now),
                    'title' => 'Next Mating Available',
                    'description' => 'Sow ready for next breeding cycle',
                    'priority' => 'low'
                ];
            }
        }

        // Sort events by date
        usort($events, function($a, $b) {
            return $a['date']->timestamp - $b['date']->timestamp;
        });

        return $events;
    }

    /**
     * Get breeding cycle configuration
     */
    public static function getBreedingConfig(): array
    {
        return [
            'pregnancy_check_days' => config('breeding.cycle.pregnancy_check_days'),
            'birth_to_weaning_days' => config('breeding.cycle.birth_to_weaning_days'),
            'birth_to_maturity_days' => config('breeding.cycle.birth_to_maturity_days'),
            'gestation_days' => config('breeding.cycle.gestation_days'),
            'minimum_breeding_age' => config('breeding.parameters.minimum_breeding_age'),
            'maximum_breeding_age' => config('breeding.parameters.maximum_breeding_age'),
            'weaning_to_mating_rest' => config('breeding.parameters.weaning_to_mating_rest'),
        ];
    }
}
