<x-guest-layout>
    <div class="flex justify-center items-center min-h-screen bg-gray-50">
        <div class="w-full max-w-2xl bg-white rounded-lg shadow-2xl p-8">
            <div class="flex justify-center mb-6">
                <a href="/">
                    <img src="/images/chipper-farms-logo.jpg" alt="Chipper Farms Logo" class="h-24 w-auto" />
                </a>
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

            <form method="POST" action="{{ route('login') }}">
                @csrf

                <div>
                    <x-label for="email" value="{{ __('Email') }}" />
                    <x-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
                </div>

                <div class="mt-4" x-data="{ show: false }">
                    <x-label for="password" value="{{ __('Password') }}" />
                    <div class="relative">
                        <input id="password" class="block mt-1 w-full pr-10" :type="show ? 'text' : 'password'" name="password" required autocomplete="current-password" />
                        <button type="button" @click="show = !show" tabindex="-1" class="absolute inset-y-0 right-0 px-3 flex items-center text-gray-500">
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

                <div class="block mt-4">
                    <label for="remember_me" class="flex items-center">
                        <x-checkbox id="remember_me" name="remember" />
                        <span class="ms-2 text-sm text-gray-600">{{ __('Remember me') }}</span>
                    </label>
                </div>

                <div class="flex items-center justify-end mt-4">
                    @if (Route::has('password.request'))
                        <a class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" href="{{ route('password.request') }}">
                            {{ __('Forgot your password?') }}
                        </a>
                    @endif

                    <x-button class="ms-4">
                        {{ __('Log in') }}
                    </x-button>
                </div>
            </form>
        </div>
    </div>
</x-guest-layout>
