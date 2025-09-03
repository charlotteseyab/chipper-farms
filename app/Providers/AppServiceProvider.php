<?php

namespace App\Providers;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use App\Helpers\NavigationHelper;
use App\Models\PigWeight;
use App\Observers\PigWeightObserver;
use App\Models\Setting;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);

        // Share navigation helper with all views
        View::share('navigation', new NavigationHelper());

        // Register model observers
        PigWeight::observe(PigWeightObserver::class);

        // Apply runtime settings overrides for breeding config
        try {
            $overrides = [
                'breeding.cycle.pregnancy_check_days' => Setting::get('breeding.cycle.pregnancy_check_days'),
                'breeding.cycle.gestation_days' => Setting::get('breeding.cycle.gestation_days'),
                'breeding.cycle.birth_to_weaning_days' => Setting::get('breeding.cycle.birth_to_weaning_days'),
                'breeding.cycle.birth_to_maturity_days' => Setting::get('breeding.cycle.birth_to_maturity_days'),
                'breeding.parameters.minimum_breeding_age' => Setting::get('breeding.parameters.minimum_breeding_age'),
                'breeding.parameters.optimal_first_breeding_age' => Setting::get('breeding.parameters.optimal_first_breeding_age'),
                'breeding.parameters.average_litter_size' => Setting::get('breeding.parameters.average_litter_size'),
                'breeding.parameters.weaning_to_mating_rest' => Setting::get('breeding.parameters.weaning_to_mating_rest'),
                'breeding.parameters.maximum_breeding_age' => Setting::get('breeding.parameters.maximum_breeding_age'),
                'breeding.parameters.lactation_days' => Setting::get('breeding.parameters.lactation_days'),
            ];

            foreach ($overrides as $configKey => $value) {
                if ($value !== null) {
                    config([$configKey => $value]);
                }
            }
        } catch (\Throwable $e) {
            // Swallow exceptions (e.g., during install before migrations)
        }
    }
}
