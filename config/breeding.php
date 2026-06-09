<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Pig Breeding Cycle Configuration
    |--------------------------------------------------------------------------
    |
    | These values define the standard breeding cycle parameters for pigs.
    | All values are in days and can be adjusted based on farm-specific
    | practices or breed variations.
    |
    */

    'cycle' => [
        // Number of days to check for pregnancy after mating
        'pregnancy_check_days' => 14,
        
        // Number of days from birth to weaning
        'birth_to_weaning_days' => 45,
        
        // Number of days from birth to maturity (breeding age)
        'birth_to_maturity_days' => 180,
        
        // Number of days from mating to farrowing (gestation period)
        'gestation_days' => 114,
    ],

    /*
    |--------------------------------------------------------------------------
    | Additional Breeding Parameters
    |--------------------------------------------------------------------------
    |
    | Additional parameters that can be useful for breeding management
    |
    */

    'parameters' => [
        // Average litter size for planning purposes
        'average_litter_size' => 10,

        // Minimum breeding ages by gender (in days)
        'minimum_breeding_age' => [
            'female' => 210, // ~7 months for gilts
            'male' => 240,   // ~8 months for boars
        ],

        // Optimal first breeding ages (in days)
        'optimal_first_breeding_age' => [
            'female' => 240, // ~8 months for gilts
            'male' => 300,   // ~10 months for boars
        ],

        // Maximum breeding age (in days)
        'maximum_breeding_age' => 2190, // ~6 years for both

        // Minimum weights for breeding (in kg) - for future weight integration
        'minimum_breeding_weight' => [
            'female' => 90, 
            'male' => 80,   
        ],

        // Optimal breeding weights (in kg) - for future weight integration
        'optimal_breeding_weight' => [
            'female' => 90, 
            'male' => 80,   
        ],

        // Rest period between weaning and next mating (days)
        'weaning_to_mating_rest' => 7,

        // Lactation period (same as weaning but for clarity)
        'lactation_days' => 45,
    ],

    /*
    |--------------------------------------------------------------------------
    | Breeding Alerts & Notifications
    |--------------------------------------------------------------------------
    |
    | Days before events to show alerts/reminders
    |
    */

    'alerts' => [
        // Days before pregnancy check to show reminder
        'pregnancy_check_reminder' => 2,
        
        // Days before expected farrowing to show alert
        'farrowing_alert' => 7,
        
        // Days before weaning to show alert
        'weaning_alert' => 5,
        
        // Days before pig reaches maturity to show alert
        'maturity_alert' => 14,
    ],

    /*
    |--------------------------------------------------------------------------
    | Date Calculation Helpers
    |--------------------------------------------------------------------------
    |
    | Date calculations are implemented in App\Helpers\BreedingCalculator.
    |
    */

    'calculator' => App\Helpers\BreedingCalculator::class,
];
