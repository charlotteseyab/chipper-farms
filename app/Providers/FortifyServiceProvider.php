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

            // Per account and IP (the 5-attempt lockout below is the real
            // guard there), and per IP: one address trying many accounts was
            // otherwise unlimited, and each attempt runs a deliberately slow
            // password hash, so it could also exhaust the server's CPU. 60 a
            // minute leaves room for a farm office sharing one address.
            return [
                Limit::perMinute(1000)->by($throttleKey),
                Limit::perMinute(60)->by('ip:'.$request->ip()),
            ];
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
                // Not audited: the lock itself was recorded when it was set,
                // and a row per request here would let a bot that keeps
                // hammering a locked login grow the audit table without limit.
                throw ValidationException::withMessages([
                    $username => "Too many attempts. Please wait {$remaining} seconds before trying again.",
                ]);
            }

            $user = User::where($username, $request->input($username))->first();
            if ($user && Hash::check($request->input('password'), $user->password)) {
                // Check if user is active
                if (!$user->active_status) {
                    $this->logLoginAttempt($request, $user, ['result' => 'inactive_account']);
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
                $this->logLoginAttempt($request, $user, [
                    'result' => 'locked',
                    'retry_after_seconds' => $remaining,
                ]);
                throw ValidationException::withMessages([
                    $username => "Too many attempts. Please wait {$remaining} seconds before trying again.",
                ]);
            }

            $this->logLoginAttempt($request, $user, ['result' => 'failed', 'attempts' => $attempts]);
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

                    if (! $user->isSuperuser()
                        && ! $user->isCustomer()
                        && $user->onboarding_required
                        && $user->onboarding_completed_at === null) {
                        return redirect()->route('onboarding.show');
                    }
                    
                    // The role's usual dashboard when the user can open it, else
                    // the first one they can: a customised role must not land
                    // on a page that answers 403.
                    return redirect()->route(\App\Services\Access\LandingPage::routeName($user));
                }
            };
        });
    }

    /**
     * Audit a login attempt that did not sign in.
     *
     * The typed username is stored only when it belongs to a real account.
     * Anything else is often a password typed into the wrong field, so it is
     * kept only as a short hash: repeated attempts with the same text can still
     * be matched up without the text itself sitting in the audit trail.
     */
    protected function logLoginAttempt(Request $request, ?User $user, array $metadata): void
    {
        $typed = (string) $request->input(Fortify::username());

        if (! $user) {
            $metadata['unknown_identifier_hash'] = substr(hash('sha256', Str::lower($typed)), 0, 12);
        }

        try {
            ActivityLog::create([
                'user_id' => $user?->id,
                'organization_id' => $user?->organization_id,
                'name' => $user ? $typed : null,
                'action' => 'login_attempt',
                'ip_address' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
                'metadata' => $metadata,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
