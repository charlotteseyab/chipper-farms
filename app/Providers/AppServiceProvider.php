<?php

namespace App\Providers;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use App\Helpers\NavigationHelper;
use App\Models\PigWeight;
use App\Observers\PigWeightObserver;
use App\Models\FeedInventory;
use App\Observers\FeedInventoryObserver;
use App\Models\Setting;
use Illuminate\Support\Facades\Event;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use App\Models\ActivityLog;

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
        FeedInventory::observe(FeedInventoryObserver::class);

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

        // Login/Logout activity logging
        Event::listen(Login::class, function (Login $event) {
            try {
                ActivityLog::create([
                    'user_id' => $event->user->id,
                    'action' => 'login',
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'metadata' => [ 'guard' => $event->guard ],
                ]);
            } catch (\Throwable $e) {}
        });

        Event::listen(Logout::class, function (Logout $event) {
            try {
                ActivityLog::create([
                    'user_id' => optional($event->user)->id,
                    'action' => 'logout',
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'metadata' => [ 'guard' => $event->guard ],
                ]);
            } catch (\Throwable $e) {}

            // Record last activity time on logout so UI can show last active timestamp
            try {
                if ($event->user) {
                    $event->user->forceFill([
                        'last_seen' => null,           // not currently online
                        'last_active_at' => now(),     // last active moment
                    ])->save();
                }
            } catch (\Throwable $e) {}
        });

        // Schedule daily notification generation if scheduler is running (documented in README)
        try {
            if ($this->app->runningInConsole()) {
                \Illuminate\Support\Facades\Artisan::command('schedule:work');
            }
        } catch (\Throwable $e) {
            // ignore
        }
    }
}
