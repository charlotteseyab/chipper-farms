<x-app-layout>
    <main class="min-h-screen bg-slate-50/70 py-2">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- <div class="mb-6">
                <div class="rounded-3xl bg-gradient-to-r from-[#1C6C51] via-[#238b67] to-[#2aa578] p-6 md:p-8 text-white shadow-lg">
                    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                        <div>
                            <p class="text-sm font-medium text-white/80 mb-2">System Configuration</p>
                            <h1 class="text-2xl md:text-3xl font-bold tracking-tight">General Settings</h1>
                            <p class="mt-2 text-sm md:text-base text-white/85 max-w-3xl">
                                Configure your farm identity, localization, branding, and basic application preferences.
                            </p>
                        </div>

                        <div class="rounded-2xl bg-white/10 backdrop-blur-sm px-5 py-4 border border-white/15">
                            <p class="text-xs uppercase tracking-[0.18em] text-white/70">Settings Area</p>
                            <p class="mt-1 text-lg font-semibold">Farm Identity</p>
                        </div>
                    </div>
                </div>
            </div> -->

            <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 bg-slate-50/70">
                    <nav class="flex flex-wrap gap-x-6 gap-y-2 px-6 py-4" aria-label="Tabs">
                        @if(auth()->user()->isSuperuser() || auth()->user()->isAdmin())
                            <a href="{{ route('settings.general') }}"
                               class="inline-flex items-center rounded-full px-4 py-2 text-sm font-semibold transition {{ request()->routeIs('settings.general*') ? 'bg-green-600 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">
                                General Settings
                            </a>
                        @endif
                        
                        @if(auth()->user()->isSuperuser())
                            <a href="{{ route('settings.users') }}"
                               class="inline-flex items-center rounded-full px-4 py-2 text-sm font-semibold transition {{ request()->routeIs('settings.users*') ? 'bg-green-600 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">
                                Account Settings
                            </a>
                        @endif

                        <a href="{{ route('settings.setup') }}"
                           class="inline-flex items-center rounded-full px-4 py-2 text-sm font-semibold transition {{ request()->routeIs('settings.setup*') ? 'bg-green-600 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">
                            Breeding Cycle
                        </a>

                        @if(auth()->user()->isSuperuser() || auth()->user()->isAdmin())
                            <a href="{{ route('settings.email') }}"
                               class="inline-flex items-center rounded-full px-4 py-2 text-sm font-semibold transition {{ request()->routeIs('settings.email*') ? 'bg-green-600 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">
                                Email Settings
                            </a>
                        @endif
                    </nav>
                </div>

                <div class="p-6 md:p-8">
                    <form action="{{ route('settings.general.update') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        @method('PUT')

                        <div class="grid grid-cols-1 xl:grid-cols-3 gap-8">
                            <section class="xl:col-span-2 rounded-3xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                                <div class="px-6 py-5 border-b border-slate-100 bg-slate-50/80">
                                    <div class="flex items-start gap-4">
                                        <div class="shrink-0 rounded-2xl bg-green-100 p-3">
                                            <svg class="h-6 w-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37a1.724 1.724 0 002.572-1.065z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            </svg>
                                        </div>
                                        <div>
                                            <h3 class="text-lg font-semibold text-slate-900">General Configuration</h3>
                                            <p class="text-sm text-slate-500 mt-1">Set the application name, preferred currency, and localization formats.</p>
                                        </div>
                                    </div>
                                </div>

                                <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-5">
                                    <div class="md:col-span-2 rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                                        <label class="block text-sm font-medium text-slate-700 mb-2">Application Name</label>
                                        <input type="text"
                                               name="app_name"
                                               value="{{ $settings['app_name'] ?? 'Chipper Farms' }}"
                                               class="w-full rounded-xl border-slate-300 px-4 py-2.5 shadow-sm focus:border-green-500 focus:ring-green-500 transition-all">
                                        <p class="text-xs text-slate-500 mt-2">This appears across the application and reports.</p>
                                    </div>

                                    <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                                        <label class="block text-sm font-medium text-slate-700 mb-2">Currency</label>
                                        <select name="currency"
                                                class="w-full rounded-xl border-slate-300 px-4 py-2.5 shadow-sm focus:border-green-500 focus:ring-green-500 transition-all">
                                            <option value="GHS" {{ ($settings['currency'] ?? '') == 'GHS' ? 'selected' : '' }}>Ghana Cedi (₵)</option>
                                            <option value="USD" {{ ($settings['currency'] ?? '') == 'USD' ? 'selected' : '' }}>US Dollar ($)</option>
                                            <option value="EUR" {{ ($settings['currency'] ?? '') == 'EUR' ? 'selected' : '' }}>Euro (€)</option>
                                        </select>
                                        <p class="text-xs text-slate-500 mt-2">Used for pricing and reports.</p>
                                    </div>

                                    <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                                        <label class="block text-sm font-medium text-slate-700 mb-2">Date Format</label>
                                        <select name="date_format"
                                                class="w-full rounded-xl border-slate-300 px-4 py-2.5 shadow-sm focus:border-green-500 focus:ring-green-500 transition-all">
                                            <option value="d/m/Y" {{ ($settings['date_format'] ?? '') == 'd/m/Y' ? 'selected' : '' }}>DD/MM/YYYY (31/12/2024)</option>
                                            <option value="m/d/Y" {{ ($settings['date_format'] ?? '') == 'm/d/Y' ? 'selected' : '' }}>MM/DD/YYYY (12/31/2024)</option>
                                            <option value="Y-m-d" {{ ($settings['date_format'] ?? '') == 'Y-m-d' ? 'selected' : '' }}>YYYY-MM-DD (2024-12-31)</option>
                                        </select>
                                        <p class="text-xs text-slate-500 mt-2">Controls how dates appear system-wide.</p>
                                    </div>

                                    <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                                        <label class="block text-sm font-medium text-slate-700 mb-2">Time Format</label>
                                        <select name="time_format"
                                                class="w-full rounded-xl border-slate-300 px-4 py-2.5 shadow-sm focus:border-green-500 focus:ring-green-500 transition-all">
                                            <option value="H:i" {{ ($settings['time_format'] ?? '') == 'H:i' ? 'selected' : '' }}>24-hour (14:30)</option>
                                            <option value="h:i A" {{ ($settings['time_format'] ?? '') == 'h:i A' ? 'selected' : '' }}>12-hour (02:30 PM)</option>
                                        </select>
                                        <p class="text-xs text-slate-500 mt-2">Controls how times appear in logs and reports.</p>
                                    </div>
                                </div>
                            </section>

                            <section class="rounded-3xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                                <div class="px-6 py-5 border-b border-slate-100 bg-slate-50/80">
                                    <div class="flex items-start gap-4">
                                        <div class="shrink-0 rounded-2xl bg-blue-100 p-3">
                                            <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-10h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                            </svg>
                                        </div>
                                        <div>
                                            <h3 class="text-lg font-semibold text-slate-900">Farm Logo</h3>
                                            <p class="text-sm text-slate-500 mt-1">Upload the image used for branding across the system.</p>
                                        </div>
                                    </div>
                                </div>

                                <div class="p-6">
                                    @php
                                        $logoPath = $settings['logo'] ?? null;
                                        $hasLogo = $logoPath && Storage::disk('public')->exists($logoPath);
                                    @endphp

                                    <div class="relative group">
                                        <div class="w-56 h-56 mx-auto rounded-3xl border-2 border-dashed border-slate-300 bg-slate-50 flex flex-col items-center justify-center overflow-hidden transition-all group-hover:border-green-500 cursor-pointer"
                                             onclick="document.getElementById('logo-input').click()">

                                            <img src="{{ $hasLogo ? Storage::url($logoPath) : '#' }}"
                                                 id="logo-preview"
                                                 class="{{ $hasLogo ? '' : 'hidden' }} w-full h-full object-contain p-4">

                                            <div id="logo-placeholder" class="{{ $hasLogo ? 'hidden' : 'text-center p-6' }}">
                                                <svg class="mx-auto h-10 w-10 text-slate-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                                    <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-2-2V6a2 2 0 012-2h14a4 4 0 014 4v0m-6 20l4 4m4-24h8m-4-4v8m-12 4h.02"
                                                          stroke-width="2"
                                                          stroke-linecap="round"
                                                          stroke-linejoin="round" />
                                                </svg>
                                                <p class="mt-3 text-xs font-bold tracking-[0.18em] uppercase text-slate-500">Upload Logo</p>
                                                <p class="mt-1 text-xs text-slate-400">PNG, JPG, JPEG, or SVG</p>
                                            </div>
                                        </div>

                                        <input type="file"
                                               name="logo"
                                               id="logo-input"
                                               class="hidden"
                                               accept="image/*"
                                               onchange="previewLogo(this)">
                                    </div>

                                    <button type="button"
                                            onclick="document.getElementById('logo-input').click()"
                                            class="mt-4 w-full inline-flex items-center justify-center rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 hover:text-green-600">
                                        {{ $hasLogo ? 'Change Image' : 'Select Image' }}
                                    </button>

                                    <p class="mt-3 text-xs text-slate-500 text-center">
                                        Recommended: square logo with transparent or light background.
                                    </p>
                                </div>
                            </section>
                        </div>

                        <div class="mt-8 pt-6 border-t border-slate-200 flex justify-end">
                            <button type="submit"
                                    class="inline-flex items-center justify-center gap-2 rounded-2xl bg-green-600 px-6 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-green-700">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                Save General Settings
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <script>
        function previewLogo(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.getElementById('logo-preview');
                    const placeholder = document.getElementById('logo-placeholder');

                    preview.src = e.target.result;
                    preview.classList.remove('hidden');
                    if (placeholder) placeholder.classList.add('hidden');
                }
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>

    <x-copyright-minimal />
</x-app-layout>