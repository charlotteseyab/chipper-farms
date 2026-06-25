<?php

namespace App\Providers;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use App\Helpers\NavigationHelper;
use App\Models\PigWeight;
use App\Observers\PigWeightObserver;
use App\Models\Setting;
use App\Support\CurrentOrganization;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use App\Models\ActivityLog;

// Phase 0.3 — policies + policy targets
use App\Models\Farrowing;
use App\Models\Mating;
use App\Models\Organization;
use App\Models\PigDeath;
use App\Models\PigManagement;
use App\Models\PigSale;
use App\Models\PurchaseRequest;
use App\Models\User;
use App\Models\Weaning;
use App\Policies\FarrowingPolicy;
use App\Policies\MatingPolicy;
use App\Policies\OrganizationPolicy;
use App\Policies\PigDeathPolicy;
use App\Policies\PigManagementPolicy;
use App\Policies\PigSalePolicy;
use App\Policies\PurchaseRequestPolicy;
use App\Policies\WeaningPolicy;

class AppServiceProvider extends ServiceProvider
{
    /**
     * The model => policy map for the application.
     *
     * Registered explicitly in boot() via Gate::policy() rather than relying
     * on Laravel's auto-discovery conventions. Explicit registration means
     * future model or namespace renames cannot silently detach a policy.
     */
    protected array $policies = [
        Organization::class => OrganizationPolicy::class,
        PigManagement::class => PigManagementPolicy::class,
        PigSale::class => PigSalePolicy::class,
        PigDeath::class => PigDeathPolicy::class,
        Farrowing::class => FarrowingPolicy::class,
        Mating::class => MatingPolicy::class,
        Weaning::class => WeaningPolicy::class,
        PurchaseRequest::class => PurchaseRequestPolicy::class,
        \App\Models\Storefront\StorefrontListing::class => \App\Policies\StorefrontListingPolicy::class,
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        // The current organization resolver is a per-request singleton.
        // EnsureOrganizationContext middleware binds the organization into it;
        // everywhere else in the app reads through this one service.
        $this->app->singleton(CurrentOrganization::class, function () {
            return new CurrentOrganization();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        try {
            Schema::defaultStringLength(191);
        } catch (\Throwable $e) {
            Log::warning('Skipping default string length configuration.', [
                'error' => $e->getMessage(),
            ]);
        }

        // Share navigation helper with all views
        View::share('navigation', new NavigationHelper());

        // Register model observers
        PigWeight::observe(PigWeightObserver::class);

        // --- Authorization (Phase 0.3) ---------------------------------------
        // Superuser bypass: a superuser is allowed to perform any gate check.
        // Returning true short-circuits policy evaluation; returning null
        // defers to the policy. We intentionally return null (not false) for
        // non-superusers so the policy decides.
        //
        // This implements the "superuser bypasses all policies" option chosen
        // for Phase 0.3. If you ever need to EXCLUDE a specific action from
        // the superuser bypass (e.g. a deliberately-locked admin-only toggle),
        // handle that INSIDE the policy with an explicit Auth::user() check
        // rather than removing this bypass.
        Gate::before(function (?User $user, string $ability) {
            if (! $user) {
                return null;
            }

            if ($user->isSuperuser()) {
                return true;
            }

            if (method_exists($user, 'hasPermission') && $user->hasPermission($ability)) {
                return true;
            }

            return null;
        });

        // Register policies explicitly.
        foreach ($this->policies as $model => $policy) {
            Gate::policy($model, $policy);
        }
        // ---------------------------------------------------------------------

        // Apply runtime settings overrides for breeding config only when settings storage is reachable
        try {
            if (! Schema::hasTable('system_configs')) {
                $overrides = [];
            } else {
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
            }

            foreach ($overrides as $configKey => $value) {
                if ($value !== null) {
                    config([$configKey => $value]);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Skipping runtime settings overrides during boot.', [
                'error' => $e->getMessage(),
            ]);
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
            } catch (\Throwable $e) {
                report($e);
            }
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
            } catch (\Throwable $e) {
                report($e);
            }

            // Record last activity time on logout so UI can show last active timestamp
            try {
                if ($event->user) {
                    $event->user->forceFill([
                        'last_seen' => null,           // not currently online
                        'last_active_at' => now(),     // last active moment
                    ])->save();
                }
            } catch (\Throwable $e) {
                report($e);
            }
        });
    }
}
