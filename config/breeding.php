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
            'female' => 100, 
            'male' => 90,   
        ],

        // Optimal breeding weights (in kg) - for future weight integration
        'optimal_breeding_weight' => [
            'female' => 100, 
            'male' => 90,   
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
    | Helper functions for common date calculations
    |
    */

    'calculations' => [
        // Calculate expected farrowing date from mating date
        'farrowing_date' => function($matingDate) {
            return \Carbon\Carbon::parse($matingDate)->addDays(config('breeding.cycle.gestation_days'));
        },
        
        // Calculate pregnancy check date from mating date
        'pregnancy_check_date' => function($matingDate) {
            return \Carbon\Carbon::parse($matingDate)->addDays(config('breeding.cycle.pregnancy_check_days'));
        },
        
        // Calculate weaning date from birth date
        'weaning_date' => function($birthDate) {
            return \Carbon\Carbon::parse($birthDate)->addDays(config('breeding.cycle.birth_to_weaning_days'));
        },
        
        // Calculate maturity date from birth date
        'maturity_date' => function($birthDate) {
            return \Carbon\Carbon::parse($birthDate)->addDays(config('breeding.cycle.birth_to_maturity_days'));
        },
        
        // Calculate next available mating date after weaning
        'next_mating_date' => function($weaningDate) {
            return \Carbon\Carbon::parse($weaningDate)->addDays(config('breeding.parameters.weaning_to_mating_rest'));
        },
    ],
];
