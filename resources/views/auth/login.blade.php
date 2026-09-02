<x-guest-layout>
    <div class="flex min-h-screen items-center justify-center bg-slate-100 px-4 py-10 sm:px-6">
        <div class="w-full max-w-lg rounded-3xl border border-slate-200 bg-white p-6 shadow-xl shadow-slate-900/10 sm:p-9">
            <div class="mb-7 flex justify-center">
                <a href="/">
                    <img src="/images/chipper-farms-logo.jpg" alt="Chipper Farms Logo" class="h-20 w-auto object-contain sm:h-24" />
                </a>
            </div>
            <div class="mb-7 text-center">
                <h1 class="text-2xl font-extrabold text-slate-950">Welcome back</h1>
                <p class="mt-2 text-sm text-slate-500">Sign in to continue to your farm workspace.</p>
            </div>
            <x-validation-errors class="mb-4" />
            @if ($errors->has('email'))
                @php
                    $message = $errors->first('email');
                    $retryAfter = null;
                    if (preg_match('/(available in|seconds:?)\s*(\d+)/i', $message, $m)) {
                        $retryAfter = (int) ($m[2] ?? 0);
                    }
                @endphp
                @if(!empty($retryAfter) && $retryAfter > 0)
                    <div x-data="{ s: {{ $retryAfter }} }" x-init="const t=setInterval(()=>{ if(s>0){ s-- } },1000)" class="mb-4 p-3 rounded-lg bg-yellow-50 border border-yellow-200 text-yellow-800 text-sm">
                        Too many attempts. Please wait <span x-text="s"></span> seconds before trying again.
                    </div>
                @endif
            @endif

            @session('status')
                <div class="mb-4 font-medium text-sm text-green-600">
                    {{ $value }}
                </div>
            @endsession

            <form method="POST" action="{{ route('login') }}" class="space-y-5">
                @csrf

                <div>
                    <x-label for="email" value="{{ __('Email') }}" />
                    <input id="email" class="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition placeholder:text-slate-400 focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20" type="email" name="email" value="{{ old('email') }}" placeholder="you@example.com" required autofocus autocomplete="username">
                </div>

                <div x-data="{ show: false }">
                    <x-label for="password" value="{{ __('Password') }}" />
                    <div class="relative mt-2">
                        <input id="password" class="block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 pr-12 text-sm text-slate-900 shadow-sm outline-none transition focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20" :type="show ? 'text' : 'password'" name="password" required autocomplete="current-password" />
                        <button type="button" @click="show = !show" tabindex="-1" aria-label="Show or hide password" class="absolute inset-y-0 right-0 flex items-center px-4 text-slate-500 transition hover:text-emerald-700">
                            <svg x-show="!show" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                            <svg x-show="show" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a9.956 9.956 0 012.293-3.95m3.32-2.69A9.956 9.956 0 0112 5c4.478 0 8.268 2.943 9.542 7a9.956 9.956 0 01-4.043 5.306M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3l18 18" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- <div class="mt-4" x-data="{ showConfirm: false }">
                    <x-label for="password_confirmation" value="{{ __('Confirm Password') }}" />
                    <div class="relative">
                        <input id="password_confirmation" class="block mt-1 w-full pr-10" :type="showConfirm ? 'text' : 'password'" name="password_confirmation" required autocomplete="off" />
                        <button type="button" @click="showConfirm = !showConfirm" tabindex="-1" class="absolute inset-y-0 right-0 px-3 flex items-center text-gray-500">
                            <svg x-show="!showConfirm" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                            <svg x-show="showConfirm" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a9.956 9.956 0 012.293-3.95m3.32-2.69A9.956 9.956 0 0112 5c4.478 0 8.268 2.943 9.542 7a9.956 9.956 0 01-4.043 5.306M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3l18 18" />
                            </svg>
                        </button>
                    </div>
                </div> -->

                <div class="block">
                    <label for="remember_me" class="flex items-center">
                        <x-checkbox id="remember_me" name="remember" />
                        <span class="ms-2 text-sm text-gray-600">{{ __('Remember me') }}</span>
                    </label>
                </div>

                <div class="flex flex-col-reverse gap-3 pt-1 sm:flex-row sm:items-center sm:justify-between">
                    @if (Route::has('password.request'))
                        <a class="rounded-md text-sm font-semibold text-emerald-700 hover:text-emerald-900 focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:ring-offset-2" href="{{ route('password.request') }}">
                            {{ __('Forgot your password?') }}
                        </a>
                    @endif

                    <button type="submit" class="inline-flex min-h-12 items-center justify-center rounded-xl bg-emerald-700 px-6 py-3 text-sm font-bold uppercase tracking-wider text-white shadow-lg shadow-emerald-900/15 transition hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:ring-offset-2 sm:min-w-32">{{ __('Log in') }}</button>
                </div>
            </form>
        </div>
    </div>
</x-guest-layout>
