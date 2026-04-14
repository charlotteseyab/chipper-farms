<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Gate;

class FortifyServiceProvider extends ServiceProvider
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
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::redirectUserForTwoFactorAuthenticationUsing(RedirectIfTwoFactorAuthenticatable::class);

        Fortify::registerView(function () {
            $user = auth()->user();
            abort_unless($user && $user->isSuperuser(), 403, 'Only a superuser can register new users.');
            return view('auth.register');
        });

        // Relax Fortify built-in throttle; we'll enforce our custom 60s lock below
        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());
            return Limit::perMinute(1000)->by($throttleKey);
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        // Custom authentication to enforce 60-second lock after 5 failed attempts
        Fortify::authenticateUsing(function (Request $request) {
            $username = Fortify::username();
            $identifier = Str::transliterate(Str::lower((string) $request->input($username)).'|'.$request->ip());
            $attemptsKey = 'login:attempts:'.$identifier;
            $lockKey = 'login:lock:'.$identifier;
            $lockUntilKey = $lockKey.':until';

            if ($request->filled('password_confirmation') && $request->input('password') !== $request->input('password_confirmation')) {
                throw ValidationException::withMessages([
                    'password_confirmation' => __('Passwords must match.'),
                ]);
            }

            if (Cache::has($lockKey)) {
                $remaining = max(1, (int) Cache::get($lockUntilKey, time()) - time());
                // Log locked attempt
                try {
                    ActivityLog::create([
                        'user_id' => optional(User::where($username, $request->input($username))->first())->id,
                        'name' => (string) $request->input($username),
                        'action' => 'login_attempt',
                        'ip_address' => $request->ip(),
                        'user_agent' => $request->userAgent(),
                        'metadata' => [
                            'result' => 'locked',
                            'retry_after_seconds' => $remaining,
                        ],
                    ]);
                } catch (\Throwable $e) {}
                throw ValidationException::withMessages([
                    $username => "Too many attempts. Please wait {$remaining} seconds before trying again.",
                ]);
            }

            $user = User::where($username, $request->input($username))->first();
            if ($user && Hash::check($request->input('password'), $user->password)) {
                // Check if user is active
                if (!$user->active_status) {
                    // Log inactive login attempt
                    try {
                        ActivityLog::create([
                            'user_id' => $user->id,
                            'name' => (string) $request->input($username),
                            'action' => 'login_attempt',
                            'ip_address' => $request->ip(),
                            'user_agent' => $request->userAgent(),
                            'metadata' => [
                                'result' => 'inactive_account',
                            ],
                        ]);
                    } catch (\Throwable $e) {}
                    throw ValidationException::withMessages([
                        $username => __('Your account has been deactivated. Please contact an administrator.'),
                    ]);
                }
                
                Cache::forget($attemptsKey);
                Cache::forget($lockKey);
                Cache::forget($lockUntilKey);
                // Do NOT log successful login attempts here; let the Login event handle "login" action logging
                return $user;
            }

            // Failed attempt: increment attempts and possibly lock
            if (!Cache::has($attemptsKey)) {
                Cache::put($attemptsKey, 0, now()->addMinutes(1));
            }
            $attempts = (int) Cache::increment($attemptsKey);

            if ($attempts >= 5) {
                $until = time() + 60;
                Cache::put($lockKey, true, now()->addSeconds(60));
                Cache::put($lockUntilKey, $until, now()->addSeconds(60));
                Cache::forget($attemptsKey);
                $remaining = 60;
                // Log locked-on-threshold attempt
                try {
                    ActivityLog::create([
                        'user_id' => optional($user)->id,
                        'name' => (string) $request->input($username),
                        'action' => 'login_attempt',
                        'ip_address' => $request->ip(),
                        'user_agent' => $request->userAgent(),
                        'metadata' => [
                            'result' => 'locked',
                            'retry_after_seconds' => $remaining,
                        ],
                    ]);
                } catch (\Throwable $e) {}
                throw ValidationException::withMessages([
                    $username => "Too many attempts. Please wait {$remaining} seconds before trying again.",
                ]);
            }

            // Log failed attempt
            try {
                ActivityLog::create([
                    'user_id' => optional($user)->id,
                    'name' => (string) $request->input($username),
                    'action' => 'login_attempt',
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'metadata' => [ 'result' => 'failed', 'attempts' => $attempts ],
                ]);
            } catch (\Throwable $e) {}
            throw ValidationException::withMessages([
                $username => __('These credentials do not match our records.'),
            ]);
        });

        // Custom login response for role-based dashboard redirect
        $this->app->singleton(LoginResponseContract::class, function () {
            return new class implements LoginResponseContract {
                public function toResponse($request)
                {
                    $user = Auth::user();
                    
                    // Use User model helper methods which handle both string and relationship roles
                    if ($user->isSuperuser()) {
                        return redirect('/superuser/dashboard');
                    } elseif ($user->isAdmin()) {
                        return redirect('/admin/dashboard');
                    } elseif ($user->isManager()) {
                        return redirect('/manager/dashboard');
                    } elseif ($user->isDataEntry()) {
                        return redirect('/dataentry/dashboard');
                    }
                    
                    return redirect('/dashboard');
                }
            };
        });
    }
}
