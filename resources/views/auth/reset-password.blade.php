<x-guest-layout>
    <main class="relative min-h-screen overflow-hidden bg-slate-50">
        <div class="pointer-events-none absolute inset-0 opacity-60" style="background-image:linear-gradient(#d9eee6 1px,transparent 1px),linear-gradient(90deg,#d9eee6 1px,transparent 1px);background-size:48px 48px"></div>
        <div class="pointer-events-none absolute -left-32 top-24 h-80 w-80 rounded-full bg-emerald-200/50 blur-3xl"></div>
        <div class="pointer-events-none absolute -right-24 bottom-10 h-72 w-72 rounded-full bg-teal-100/70 blur-3xl"></div>

        <div class="relative mx-auto flex min-h-screen max-w-7xl items-center px-4 py-8 sm:px-6 lg:px-8">
            <div class="grid w-full overflow-hidden rounded-[2rem] border border-emerald-900/10 bg-white shadow-2xl lg:grid-cols-[.9fr_1.1fr]">
                <section class="hidden flex-col justify-between p-10 lg:flex" style="background:linear-gradient(145deg,#0f2f27 0%,#145c47 62%,#07835e 100%);color:#fff">
                    <div>
                        <a href="{{ route('home') }}" class="inline-flex items-center gap-3 rounded-2xl bg-white p-2.5 pr-4 shadow-lg">
                            <img src="{{ asset('images/chipper-farms-logo.jpg') }}" alt="Chipper Farms" class="h-11 w-auto rounded-xl">
                            <span class="font-black text-emerald-950">Chipper Farms</span>
                        </a>
                        <p class="mt-14 text-xs font-black uppercase tracking-[.22em]" style="color:#86efac">Secure account setup</p>
                        <h1 class="mt-4 max-w-md text-4xl font-black leading-tight" style="color:#fff">Choose a password that belongs only to you.</h1>
                        <p class="mt-5 max-w-md text-base leading-7" style="color:#d1fae5">Your new password replaces the temporary password in your welcome email and protects access to your organization’s farm records.</p>
                    </div>
                    <div class="space-y-4">
                        @foreach(['Use at least 8 characters', 'Avoid names and familiar words', 'Do not reuse a password from another account'] as $tip)
                            <div class="flex items-center gap-3 text-sm font-semibold" style="color:#ecfdf5"><span class="flex h-7 w-7 items-center justify-center rounded-full bg-white/10">✓</span>{{ $tip }}</div>
                        @endforeach
                    </div>
                </section>

                <section class="p-5 sm:p-8 lg:p-12 xl:p-14">
                    <div class="mx-auto max-w-xl">
                        <div class="flex items-center justify-between gap-4 lg:hidden">
                            <a href="{{ route('home') }}"><img src="{{ asset('images/chipper-farms-logo.jpg') }}" alt="Chipper Farms" class="h-12 w-auto rounded-xl border border-emerald-100 shadow-sm"></a>
                            <span class="rounded-full bg-emerald-50 px-3 py-1.5 text-xs font-black uppercase tracking-wider text-emerald-700">Secure setup</span>
                        </div>

                        <div class="mt-8 lg:mt-0">
                            <p class="text-xs font-black uppercase tracking-[.18em] text-emerald-700">Account security</p>
                            <h2 class="mt-2 text-3xl font-black tracking-tight text-slate-950 sm:text-4xl">Set your password</h2>
                            <p class="mt-3 text-sm leading-6 text-slate-600">Enter and confirm the password you want to use when signing in. This secure link can only be used for the email address shown below.</p>
                        </div>

                        <x-validation-errors class="mt-6 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-800" />

                        <form method="POST" action="{{ route('password.update') }}" class="mt-7 space-y-5" x-data="{ showPassword: false, showConfirmation: false }">
                            @csrf
                            <input type="hidden" name="token" value="{{ $request->route('token') }}">

                            <div>
                                <label for="email" class="text-sm font-bold text-slate-800">Account email</label>
                                <div class="mt-2">
                                    <input id="email" class="block w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-700 shadow-sm focus:border-emerald-600 focus:ring-emerald-600" type="email" name="email" value="{{ old('email', $request->email) }}" required readonly autocomplete="username">
                                </div>
                            </div>

                            <div>
                                <label for="password" class="text-sm font-bold text-slate-800">New password</label>
                                <div class="relative mt-2">
                                    <input id="password" class="block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 pr-12 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600" :type="showPassword ? 'text' : 'password'" name="password" required autofocus autocomplete="new-password" minlength="8">
                                    <button type="button" @click="showPassword = !showPassword" class="absolute inset-y-0 right-0 flex items-center px-4 text-sm font-bold text-slate-500 hover:text-emerald-700" :aria-label="showPassword ? 'Hide password' : 'Show password'" x-text="showPassword ? 'Hide' : 'Show'"></button>
                                </div>
                                <p class="mt-2 text-xs text-slate-500">Use 8 or more characters. A longer, unique password is safer.</p>
                            </div>

                            <div>
                                <label for="password_confirmation" class="text-sm font-bold text-slate-800">Confirm new password</label>
                                <div class="relative mt-2">
                                    <input id="password_confirmation" class="block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 pr-12 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600" :type="showConfirmation ? 'text' : 'password'" name="password_confirmation" required autocomplete="new-password" minlength="8">
                                    <button type="button" @click="showConfirmation = !showConfirmation" class="absolute inset-y-0 right-0 flex items-center px-4 text-sm font-bold text-slate-500 hover:text-emerald-700" :aria-label="showConfirmation ? 'Hide password' : 'Show password'" x-text="showConfirmation ? 'Hide' : 'Show'"></button>
                                </div>
                            </div>

                            <button type="submit" class="flex w-full items-center justify-center rounded-xl bg-emerald-700 px-5 py-3.5 text-sm font-black text-white shadow-lg shadow-emerald-900/10 transition hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:ring-offset-2">Save password and continue</button>
                        </form>

                        <div class="mt-6 flex flex-col gap-2 border-t border-slate-200 pt-5 text-center text-sm text-slate-500 sm:flex-row sm:items-center sm:justify-between sm:text-left">
                            <span>Link expired or already used?</span>
                            <a href="{{ route('password.request') }}" class="font-bold text-emerald-700 hover:text-emerald-900">Request a new password link</a>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </main>
</x-guest-layout>
