<?php

namespace App\Providers;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use App\Helpers\NavigationHelper;
use App\Models\PigWeight;
use App\Observers\PigWeightObserver;

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
    }
}
