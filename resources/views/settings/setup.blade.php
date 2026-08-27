<x-app-layout>
    @if($platformView)
        <div class="mx-auto mb-5 max-w-7xl rounded-2xl border border-blue-200 bg-blue-50 px-5 py-4 text-sm font-semibold text-blue-900">
            This is a read-only platform view. Operational setup belongs to each farm and must be completed by that farm’s administrator.
        </div>
        <style>.setup-page form { pointer-events:none; opacity:.68; } .setup-page input,.setup-page button { cursor:not-allowed !important; }</style>
    @endif
    <style>
        .breed-manager { max-width: 1080px; margin-inline: auto; }
        .breed-manager__row { display: grid; grid-template-columns: 110px minmax(280px, 1fr) 120px; gap: 18px; align-items: center; }
        .breed-manager__input { display: block; width: 100% !important; max-width: none !important; min-width: 0; }
        .breed-manager__add { display: grid; grid-template-columns: minmax(260px, 1fr) 180px; gap: 16px; align-items: end; }
        .setup-hero { background: #064e3b; color: #fff; }
        .setup-hero__eyebrow { color: #a7f3d0; }
        .setup-jump { scrollbar-width: thin; margin-bottom: 20px !important; }
        .setup-jump button { white-space: nowrap; }
        .setup-tab[aria-selected="true"] { background: #047857 !important; color: #fff !important; box-shadow: 0 5px 14px rgba(4,120,87,.2); }
        .setup-tab[aria-selected="false"] { background: transparent !important; color: #334155 !important; }
        .setup-tab[aria-selected="false"]:hover { background: #f1f5f9 !important; }
        [data-setup-panel][hidden] { display: none !important; }
        .setup-settings-shell { border: 0 !important; background: transparent !important; box-shadow: none !important; overflow: visible !important; }
        .setup-settings-shell > div { padding: 0 !important; }
        .setup-page form[action*="setup/breeding"] section {
            border-width: 1px !important;
            border-color: #cbd5e1 !important;
            box-shadow: 0 8px 24px rgba(15, 23, 42, .045) !important;
        }
        .setup-page form[action*="setup/breeding"] section > div:first-child {
            background: #f8fafc !important;
            border-bottom-color: #e2e8f0 !important;
        }
        .setup-page input[type="text"], .setup-page input[type="number"] {
            min-height: 46px;
            background: #fff;
            color: #0f172a;
            font-weight: 600;
        }
        .setup-page label { color: #334155; }
        .setup-save-bar { position: sticky; bottom: 12px; z-index: 20; border: 1px solid #cbd5e1; background: rgba(255,255,255,.96); box-shadow: 0 14px 35px rgba(15,23,42,.16); backdrop-filter: blur(12px); }
        @media (max-width: 700px) {
            .breed-manager__head { display: none !important; }
            .breed-manager__row { grid-template-columns: 1fr auto; gap: 10px; }
            .breed-manager__row .breed-manager__name { grid-column: 1 / -1; grid-row: 2; }
            .breed-manager__add { grid-template-columns: 1fr; }
        }
    </style>
    <main class="setup-page min-h-screen bg-slate-50/70 py-5 sm:py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <section class="setup-hero mb-5 overflow-hidden rounded-3xl px-5 py-7 shadow-md sm:px-8 sm:py-9">
                <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                    <div class="max-w-3xl"><p class="setup-hero__eyebrow text-xs font-extrabold uppercase tracking-[0.2em]">Farm configuration</p><h1 class="mt-2 text-3xl font-black tracking-tight text-white sm:text-4xl">Farm setup</h1><p class="mt-3 text-base font-medium leading-7 text-white">Configure the farm rules used for breeding, growth stages, feed planning, purchasing and storefront pricing.</p></div>
                    <div class="rounded-2xl border border-white/20 bg-white/10 px-4 py-3 text-sm font-semibold text-white"><span class="block text-xs uppercase tracking-wide text-emerald-200">Important</span>Changes affect calculations and future records across the system.</div>
                </div>
            </section>

            <nav id="setup-tabs" class="setup-jump mb-7 flex gap-2 overflow-x-auto rounded-2xl border border-slate-200 bg-white p-2 shadow-sm" aria-label="Setup sections" role="tablist">
                <button type="button" class="setup-tab rounded-xl px-4 py-2.5 text-sm font-bold" data-setup-tab="animal-breeds" role="tab">Breeds</button>
                <button type="button" class="setup-tab rounded-xl px-4 py-2.5 text-sm font-bold" data-setup-tab="breeding-cycle" role="tab">Breeding cycle</button>
                <button type="button" class="setup-tab rounded-xl px-4 py-2.5 text-sm font-bold" data-setup-tab="breeding-requirements" role="tab">Breeding requirements</button>
                <button type="button" class="setup-tab rounded-xl px-4 py-2.5 text-sm font-bold" data-setup-tab="feed-units" role="tab">Feed units</button>
                <button type="button" class="setup-tab rounded-xl px-4 py-2.5 text-sm font-bold" data-setup-tab="purchase-feed-items" role="tab">Feed catalogue</button>
                <button type="button" class="setup-tab rounded-xl px-4 py-2.5 text-sm font-bold" data-setup-tab="additional-parameters" role="tab">Planning</button>
                <button type="button" class="setup-tab rounded-xl px-4 py-2.5 text-sm font-bold" data-setup-tab="growth-stages" role="tab">Growth stages</button>
                <button type="button" class="setup-tab rounded-xl px-4 py-2.5 text-sm font-bold" data-setup-tab="sales-pricing" role="tab">Sales pricing</button>
            </nav>

            <form action="{{ route('settings.setup.breeds.save') }}" method="POST">
                @csrf

                <section id="animal-breeds" data-setup-panel role="tabpanel" class="breed-manager mb-8 scroll-mt-24 overflow-hidden rounded-3xl border border-emerald-200 bg-white shadow-sm">
                    <div class="flex flex-col gap-3 border-b border-emerald-100 bg-gradient-to-r from-emerald-50 to-white px-5 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-7">
                        <div>
                            <div class="mb-1 flex items-center gap-2">
                                <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-700 text-sm font-bold text-white">{{ count($animalBreeds) }}</span>
                                <h2 class="text-xl font-bold text-slate-900">Animal Breeds</h2>
                            </div>
                            <p class="text-sm text-slate-600">Control which breeds are available on this farm. Codes remain short internally; customers see full names.</p>
                        </div>
                        <span class="w-fit rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-800">Farm-specific catalogue</span>
                    </div>

                    <div class="p-5 sm:p-7">
                        @error('breeds') <p class="mb-4 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700">{{ $message }}</p> @enderror

                        <div class="overflow-hidden rounded-2xl border border-slate-200">
                            <div class="breed-manager__head breed-manager__row bg-slate-50 px-5 py-2.5 text-xs font-semibold uppercase tracking-wide text-slate-500">
                                <span>Code</span><span>Full name</span><span class="text-right">Action</span>
                            </div>
                            <div class="divide-y divide-slate-200">
                                @foreach($animalBreeds as $code => $name)
                                    <div class="breed-manager__row px-5 py-4">
                                        <div><span class="inline-flex rounded-lg bg-slate-100 px-3 py-2 font-mono text-sm font-bold text-slate-700">{{ $code }}</span></div>
                                        <div class="breed-manager__name">
                                            <label class="mb-1 block text-xs font-medium text-slate-500 md:hidden">Full name</label>
                                            <input name="breed_names[{{ $code }}]" value="{{ old('breed_names.'.$code, $name) }}" required maxlength="100" class="breed-manager__input rounded-xl border-slate-300 px-3 py-2 shadow-sm focus:border-emerald-500 focus:ring-emerald-500" aria-label="Full name for {{ $code }}">
                                        </div>
                                        <label class="inline-flex items-center justify-end gap-2 text-sm font-medium text-red-700">
                                            <input type="checkbox" name="remove_breeds[]" value="{{ $code }}" class="rounded border-slate-300 text-red-600 focus:ring-red-500"> Remove
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="mt-5 rounded-2xl border border-dashed border-emerald-300 bg-emerald-50/60 p-4">
                            <h3 class="mb-3 text-sm font-bold text-emerald-950">Add another breed</h3>
                            <div class="breed-manager__add">
                                <label class="text-sm font-medium text-slate-700">Full breed name
                                    <input name="new_breed_name" value="{{ old('new_breed_name') }}" maxlength="100" placeholder="e.g. Hampshire" class="breed-manager__input mt-1 rounded-xl border-slate-300 focus:border-emerald-500 focus:ring-emerald-500">
                                    <span class="mt-1 block text-xs font-normal text-slate-500">The internal code is generated automatically.</span>
                                </label>
                                <button type="submit" class="min-h-11 rounded-xl bg-emerald-700 px-5 py-2.5 font-semibold text-white shadow-sm hover:bg-emerald-800">Add breed</button>
                            </div>
                            @error('new_breed_code') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                            @error('new_breed_name') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </section>
            </form>

            <form action="{{ route('settings.setup.breeding.save') }}" method="POST">
                @csrf

                <div class="setup-settings-shell overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">

                    <div class="p-6 md:p-8 space-y-8">
                        <!-- Breeding Cycle Section -->
                        <section id="breeding-cycle" data-setup-panel role="tabpanel" class="scroll-mt-24 rounded-3xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                            <div class="px-6 py-5 border-b border-slate-100 bg-slate-50/80">
                                <div class="flex items-start gap-4">
                                    <div class="shrink-0 rounded-2xl bg-green-100 p-3">
                                        <svg class="h-6 w-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <h3 class="text-lg font-semibold text-slate-900">Breeding Cycle Parameters</h3>
                                        <p class="text-sm text-slate-500 mt-1">Configure the timing of breeding, gestation, weaning, and maturity stages.</p>
                                    </div>
                                </div>
                            </div>

                            <div class="p-6 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-5">
                                <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                                    <label class="block text-sm font-medium text-slate-700 mb-2">Pregnancy Check (Days)</label>
                                            <input name="pregnancy_check_days" type="number" value="{{ old('pregnancy_check_days', $breeding['pregnancy_check_days']) }}" min="10" max="21" placeholder="Set days" class="w-full rounded-xl border-slate-300 px-3 py-2.5 shadow-sm focus:border-green-500 focus:ring-green-500">
                                    <p class="text-xs text-slate-500 mt-2">Days after mating</p>
                                </div>

                                <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                                    <label class="block text-sm font-medium text-slate-700 mb-2">Gestation Period (Days)</label>
                                    <input name="gestation_days" type="number" value="{{ old('gestation_days', $breeding['gestation_days']) }}" min="110" max="120" placeholder="Set days" class="w-full rounded-xl border-slate-300 px-3 py-2.5 shadow-sm focus:border-green-500 focus:ring-green-500">
                                    <p class="text-xs text-slate-500 mt-2">Mating to farrowing</p>
                                </div>

                                <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                                    <label class="block text-sm font-medium text-slate-700 mb-2">Birth to Weaning (Days)</label>
                                    <input name="birth_to_weaning_days" type="number" value="{{ old('birth_to_weaning_days', $breeding['birth_to_weaning_days']) }}" min="35" max="60" placeholder="Set days" class="w-full rounded-xl border-slate-300 px-3 py-2.5 shadow-sm focus:border-green-500 focus:ring-green-500">
                                    <p class="text-xs text-slate-500 mt-2">From birth</p>
                                </div>

                                <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                                    <label class="block text-sm font-medium text-slate-700 mb-2">Birth to Maturity (Days)</label>
                                    <input name="birth_to_maturity_days" type="number" value="{{ old('birth_to_maturity_days', $breeding['birth_to_maturity_days']) }}" min="150" max="240" placeholder="Set days" class="w-full rounded-xl border-slate-300 px-3 py-2.5 shadow-sm focus:border-green-500 focus:ring-green-500">
                                    <p class="text-xs text-slate-500 mt-2">From birth</p>
                                </div>
                            </div>
                        </section>

                        <!-- Breeding Ages Section -->
                        <section id="breeding-requirements" data-setup-panel role="tabpanel" class="scroll-mt-24 rounded-3xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                            <div class="px-6 py-5 border-b border-slate-100 bg-slate-50/80">
                                <div class="flex items-start gap-4">
                                    <div class="shrink-0 rounded-2xl bg-purple-100 p-3">
                                        <svg class="h-6 w-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <h3 class="text-lg font-semibold text-slate-900">Breeding Age and Weight Requirements</h3>
                                        <p class="text-sm text-slate-500 mt-1">Set minimum and optimal ages and weights for breeding males and females.</p>
                                    </div>
                                </div>
                            </div>

                            <div class="p-6 grid grid-cols-1 xl:grid-cols-2 gap-6">
                                <div class="rounded-2xl border border-purple-100 bg-purple-50/50 p-5">
                                    <h4 class="font-semibold text-slate-900 text-sm mb-4">Female Pigs</h4>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-slate-700 mb-2">Minimum Breeding Age (Days)</label>
                                            <input name="minimum_breeding_age_female" type="number" value="{{ old('minimum_breeding_age_female', $breeding['minimum_breeding_age_female']) }}" min="180" max="365" placeholder="Set days" class="w-full rounded-xl border-slate-300 px-3 py-2.5 shadow-sm focus:border-purple-500 focus:ring-purple-500">
                                            <p class="text-xs text-slate-500 mt-2">~7 months minimum</p>
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium text-slate-700 mb-2">Optimal First Breeding (Days)</label>
                                            <input name="optimal_first_breeding_age_female" type="number" value="{{ old('optimal_first_breeding_age_female', $breeding['optimal_first_breeding_age_female']) }}" min="200" max="365" placeholder="Set days" class="w-full rounded-xl border-slate-300 px-3 py-2.5 shadow-sm focus:border-purple-500 focus:ring-purple-500">
                                            <p class="text-xs text-slate-500 mt-2">~8 months optimal</p>
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium text-slate-700 mb-2">Minimum Breeding Weight (kg)</label>
                                            <input name="minimum_breeding_weight_female" type="number" value="{{ old('minimum_breeding_weight_female', $breeding['minimum_breeding_weight_female']) }}" min="20" max="500" step="0.01" placeholder="Set kg" class="w-full rounded-xl border-slate-300 px-3 py-2.5 shadow-sm focus:border-purple-500 focus:ring-purple-500">
                                            <p class="text-xs text-slate-500 mt-2">~90 kg minimum</p>
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium text-slate-700 mb-2">Optimal Breeding Weight (kg)</label>
                                            <input name="optimal_breeding_weight_female" type="number" value="{{ old('optimal_breeding_weight_female', $breeding['optimal_breeding_weight_female']) }}" min="20" max="500" step="0.01" placeholder="Set kg" class="w-full rounded-xl border-slate-300 px-3 py-2.5 shadow-sm focus:border-purple-500 focus:ring-purple-500">
                                            <p class="text-xs text-slate-500 mt-2">~90 kg optimal</p>
                                        </div>
                                    </div>
                                </div>

                                <div class="rounded-2xl border border-purple-100 bg-purple-50/50 p-5">
                                    <h4 class="font-semibold text-slate-900 text-sm mb-4">Male Pigs</h4>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-slate-700 mb-2">Minimum Breeding Age (Days)</label>
                                            <input name="minimum_breeding_age_male" type="number" value="{{ old('minimum_breeding_age_male', $breeding['minimum_breeding_age_male']) }}" min="180" max="365" placeholder="Set days" class="w-full rounded-xl border-slate-300 px-3 py-2.5 shadow-sm focus:border-purple-500 focus:ring-purple-500">
                                            <p class="text-xs text-slate-500 mt-2">~8 months minimum</p>
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium text-slate-700 mb-2">Optimal First Breeding (Days)</label>
                                            <input name="optimal_first_breeding_age_male" type="number" value="{{ old('optimal_first_breeding_age_male', $breeding['optimal_first_breeding_age_male']) }}" min="240" max="365" placeholder="Set days" class="w-full rounded-xl border-slate-300 px-3 py-2.5 shadow-sm focus:border-purple-500 focus:ring-purple-500">
                                            <p class="text-xs text-slate-500 mt-2">~10 months optimal</p>
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium text-slate-700 mb-2">Minimum Breeding Weight (kg)</label>
                                            <input name="minimum_breeding_weight_male" type="number" value="{{ old('minimum_breeding_weight_male', $breeding['minimum_breeding_weight_male']) }}" min="20" max="500" step="0.01" placeholder="Set kg" class="w-full rounded-xl border-slate-300 px-3 py-2.5 shadow-sm focus:border-purple-500 focus:ring-purple-500">
                                            <p class="text-xs text-slate-500 mt-2">~80 kg minimum</p>
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium text-slate-700 mb-2">Optimal Breeding Weight (kg)</label>
                                            <input name="optimal_breeding_weight_male" type="number" value="{{ old('optimal_breeding_weight_male', $breeding['optimal_breeding_weight_male']) }}" min="20" max="500" step="0.01" placeholder="Set kg" class="w-full rounded-xl border-slate-300 px-3 py-2.5 shadow-sm focus:border-purple-500 focus:ring-purple-500">
                                            <p class="text-xs text-slate-500 mt-2">~80 kg optimal</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <!-- Feed Bag Weights Section -->
                        <section id="feed-units" data-setup-panel role="tabpanel" class="scroll-mt-24 rounded-3xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                            <div class="px-6 py-5 border-b border-slate-100 bg-slate-50/80">
                                <div class="flex items-start gap-4">
                                    <div class="shrink-0 rounded-2xl bg-emerald-100 p-3">
                                        <svg class="h-6 w-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V7a2 2 0 00-2-2h-3V3H9v2H6a2 2 0 00-2 2v6m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0H4"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <h3 class="text-lg font-semibold text-slate-900">Feed Purchase Units / Bag Weights</h3>
                                        <p class="text-sm text-slate-500 mt-1">
                                            Set the standard bag weights used to convert projected shortages from kilograms into bags on the Planning & Reports page.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="p-6 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
                                <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                                    <label class="block text-sm font-medium text-slate-700 mb-2">Default Bag Weight (kg)</label>
                                    <input type="number" step="0.01" min="0.01" name="default_bag_weight_kg" value="{{ old('default_bag_weight_kg', $feedBagWeights['default_bag_weight_kg']) }}" placeholder="Set kg per bag" class="w-full rounded-xl border-slate-300 px-3 py-2.5 shadow-sm focus:border-green-500 focus:ring-green-500">
                                    <p class="text-xs text-slate-500 mt-2">Fallback for ingredients without a specific bag weight.</p>
                                </div>

                                <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                                    <label class="block text-sm font-medium text-slate-700 mb-2">Maize Bag Weight (kg)</label>
                                    <input type="number" step="0.01" min="0.01" name="maize_bag_weight_kg" value="{{ old('maize_bag_weight_kg', $feedBagWeights['maize_bag_weight_kg']) }}" placeholder="Set kg per bag" class="w-full rounded-xl border-slate-300 px-3 py-2.5 shadow-sm focus:border-green-500 focus:ring-green-500">
                                    <p class="text-xs text-slate-500 mt-2">Used for maize purchase estimates.</p>
                                </div>

                                <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                                    <label class="block text-sm font-medium text-slate-700 mb-2">Soybean Meal Bag Weight (kg)</label>
                                    <input type="number" step="0.01" min="0.01" name="soybean_meal_bag_weight_kg" value="{{ old('soybean_meal_bag_weight_kg', $feedBagWeights['soybean_meal_bag_weight_kg']) }}" placeholder="Set kg per bag" class="w-full rounded-xl border-slate-300 px-3 py-2.5 shadow-sm focus:border-green-500 focus:ring-green-500">
                                    <p class="text-xs text-slate-500 mt-2">Used for soybean meal purchase estimates.</p>
                                </div>

                                <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                                    <label class="block text-sm font-medium text-slate-700 mb-2">Wheat Bran Bag Weight (kg)</label>
                                    <input type="number" step="0.01" min="0.01" name="wheat_bran_bag_weight_kg" value="{{ old('wheat_bran_bag_weight_kg', $feedBagWeights['wheat_bran_bag_weight_kg']) }}" placeholder="Set kg per bag" class="w-full rounded-xl border-slate-300 px-3 py-2.5 shadow-sm focus:border-green-500 focus:ring-green-500">
                                    <p class="text-xs text-slate-500 mt-2">Used for wheat bran purchase estimates.</p>
                                </div>

                                <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                                    <label class="block text-sm font-medium text-slate-700 mb-2">Premix Bag Weight (kg)</label>
                                    <input type="number" step="0.01" min="0.01" name="premix_bag_weight_kg" value="{{ old('premix_bag_weight_kg', $feedBagWeights['premix_bag_weight_kg']) }}" placeholder="Set kg per bag" class="w-full rounded-xl border-slate-300 px-3 py-2.5 shadow-sm focus:border-green-500 focus:ring-green-500">
                                    <p class="text-xs text-slate-500 mt-2">Used for premix purchase estimates.</p>
                                </div>

                                <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                                    <label class="block text-sm font-medium text-slate-700 mb-2">Concentrate Bag Weight (kg)</label>
                                    <input type="number" step="0.01" min="0.01" name="concentrate_bag_weight_kg" value="{{ old('concentrate_bag_weight_kg', $feedBagWeights['concentrate_bag_weight_kg']) }}" placeholder="Set kg per bag" class="w-full rounded-xl border-slate-300 px-3 py-2.5 shadow-sm focus:border-green-500 focus:ring-green-500">
                                    <p class="text-xs text-slate-500 mt-2">Used for concentrate purchase estimates.</p>
                                </div>
                            </div>
                        </section>

                        <!-- Purchase Request Feed Items -->
                        <section id="purchase-feed-items" data-setup-panel role="tabpanel"
                                 class="overflow-hidden rounded-3xl border border-emerald-100 bg-white shadow-sm"
                                 x-data="feedItemsSettings(@js(old('feed_items', $purchaseRequestFeedItems ?? [])))">
                            <div class="border-b border-emerald-100 bg-gradient-to-r from-emerald-50 via-white to-lime-50 px-5 py-5 md:px-6">
                                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                                    <div class="flex items-start gap-4">
                                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-700 shadow-sm">
                                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7h18M3 12h18M3 17h12" />
                                            </svg>
                                        </div>
                                        <div>
                                            <div class="flex flex-wrap items-center gap-2">
                                                <h3 class="text-base font-bold text-slate-900 md:text-lg">Purchase Request Feed Items</h3>
                                                <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700">
                                                    <span x-text="cleanItems().length"></span> dropdown option<span x-show="cleanItems().length !== 1">s</span>
                                                </span>
                                            </div>
                                            <p class="mt-1 max-w-2xl text-sm leading-6 text-slate-500">
                                                Manage the feed names staff will see in the <span class="font-semibold text-emerald-700">Feed</span> tab on the purchase request form.
                                            </p>
                                        </div>
                                    </div>

                                    <button type="submit"
                                            name="feed_items_only"
                                            value="1"
                                            class="inline-flex items-center justify-center gap-2 rounded-2xl bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                        </svg>
                                        Save Feed Items
                                    </button>
                                </div>
                            </div>

                            <div class="p-5 md:p-6">
                                <div class="grid grid-cols-1 gap-5 xl:grid-cols-12">
                                    <div class="xl:col-span-4">
                                        <div class="rounded-3xl border border-emerald-200 bg-emerald-50/70 p-4 shadow-sm md:p-5">
                                            <div class="flex items-start gap-3">
                                                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-2xl bg-white text-emerald-700 shadow-sm">
                                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                                    </svg>
                                                </div>
                                                <div>
                                                    <h4 class="text-sm font-bold text-slate-900">Add feed item</h4>
                                                    <p class="mt-1 text-xs leading-5 text-slate-500">Type the name once. It will be added to the saved dropdown when you save.</p>
                                                </div>
                                            </div>

                                            <div class="mt-4 space-y-3">
                                                <label class="sr-only" for="new_feed_item">Feed item name</label>
                                                <input id="new_feed_item"
                                                       name="new_feed_item"
                                                       type="text"
                                                       x-model="newItem"
                                                       @keydown.enter.prevent="addItem()"
                                                       placeholder="Example: Grower Feed"
                                                       class="w-full rounded-2xl border-emerald-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 shadow-sm placeholder:text-slate-400 focus:border-emerald-500 focus:ring-emerald-500">

                                                <p x-show="message" x-transition class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-medium text-amber-700" x-text="message"></p>

                                                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 xl:grid-cols-1">
                                                    <button type="button"
                                                            @click="addItem()"
                                                            :disabled="!newItem.trim()"
                                                            class="inline-flex items-center justify-center gap-2 rounded-2xl border border-emerald-200 bg-white px-4 py-2.5 text-sm font-semibold text-emerald-700 shadow-sm transition hover:bg-emerald-50 disabled:cursor-not-allowed disabled:border-slate-200 disabled:bg-slate-100 disabled:text-slate-400">
                                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                                        </svg>
                                                        Add to list
                                                    </button>

                                                    <button type="submit"
                                                            name="feed_items_only"
                                                            value="1"
                                                            class="inline-flex items-center justify-center gap-2 rounded-2xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700">
                                                        Save list
                                                    </button>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="mt-4 rounded-3xl border border-slate-200 bg-slate-50 p-4">
                                            <div class="mb-2 flex items-center justify-between">
                                                <h4 class="text-sm font-bold text-slate-900">Dropdown preview</h4>
                                                <span class="rounded-full bg-white px-2.5 py-1 text-[11px] font-bold text-slate-500 shadow-sm">Live preview</span>
                                            </div>
                                            <select class="w-full rounded-2xl border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-700 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                                                <option>Select feed item</option>
                                                <template x-for="item in cleanItems()" :key="item">
                                                    <option x-text="item"></option>
                                                </template>
                                            </select>
                                            <p class="mt-3 text-xs leading-5 text-slate-500">After saving, this same list appears on the purchase request Feed tab.</p>
                                        </div>
                                    </div>

                                    <div class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm md:p-5 xl:col-span-8">
                                        <div class="mb-4 flex flex-col gap-3 border-b border-slate-100 pb-4 sm:flex-row sm:items-center sm:justify-between">
                                            <div>
                                                <h4 class="text-sm font-bold text-slate-900">Saved feed names</h4>
                                                <p class="mt-1 text-xs leading-5 text-slate-500">These names should be short because they become selectable purchase descriptions.</p>
                                            </div>
                                            <span class="inline-flex w-fit items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">
                                                <span x-text="cleanItems().length"></span>&nbsp;item<span x-show="cleanItems().length !== 1">s</span>
                                            </span>
                                        </div>

                                        <template x-if="items.length === 0">
                                            <div class="rounded-3xl border border-dashed border-slate-300 bg-slate-50/70 px-4 py-8 text-center">
                                                <div class="mx-auto mb-3 flex h-11 w-11 items-center justify-center rounded-2xl bg-white text-slate-400 shadow-sm">
                                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                                    </svg>
                                                </div>
                                                <p class="text-sm font-semibold text-slate-700">No feed items added yet.</p>
                                                <p class="mt-1 text-xs text-slate-500">Use the add box, then save the list.</p>
                                            </div>
                                        </template>

                                        <div class="grid max-h-[430px] grid-cols-1 gap-2 overflow-y-auto pr-1 md:grid-cols-2">
                                            <template x-for="(item, index) in items" :key="index">
                                                <div class="group flex items-center gap-2 rounded-2xl border border-slate-200 bg-slate-50/80 px-3 py-2 transition hover:border-emerald-200 hover:bg-emerald-50/60">
                                                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-white text-[11px] font-bold text-emerald-700 shadow-sm" x-text="index + 1"></span>

                                                    <input type="text"
                                                           :name="`feed_items[${index}]`"
                                                           x-model="items[index]"
                                                           @blur="items[index] = normalize(items[index])"
                                                           placeholder="Feed item name"
                                                           class="min-w-0 flex-1 rounded-xl border-0 bg-transparent px-1 py-1.5 text-sm font-semibold text-slate-700 placeholder:text-slate-400 focus:bg-white focus:ring-2 focus:ring-emerald-100">

                                                    <button type="button"
                                                            @click="removeItem(index)"
                                                            title="Remove feed item"
                                                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-slate-400 transition hover:bg-red-50 hover:text-red-600">
                                                        <span class="sr-only">Remove</span>
                                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                        </svg>
                                                    </button>
                                                </div>
                                            </template>
                                        </div>

                                        <div class="mt-4 rounded-2xl border border-emerald-100 bg-emerald-50/70 px-4 py-3 text-xs leading-5 text-emerald-800">
                                            Save this section after adding or editing feed names. The purchase request form reads from this saved list.
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <!-- Additional Parameters Section -->
                        <section id="additional-parameters" data-setup-panel role="tabpanel" class="scroll-mt-24 rounded-3xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                            <div class="px-6 py-5 border-b border-slate-100 bg-slate-50/80">
                                <div class="flex items-start gap-4">
                                    <div class="shrink-0 rounded-2xl bg-orange-100 p-3">
                                        <svg class="h-6 w-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <h3 class="text-lg font-semibold text-slate-900">Additional Breeding Parameters</h3>
                                        <p class="text-sm text-slate-500 mt-1">Configure planning and breeding settings used across the system.</p>
                                    </div>
                                </div>
                            </div>

                            <div class="p-6 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-5">
                                <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                                    <label class="block text-sm font-medium text-slate-700 mb-2">Average Litter Size</label>
                                    <input name="average_litter_size" type="number" value="{{ old('average_litter_size', $breeding['average_litter_size']) }}" min="1" max="25" step="0.5" placeholder="Set average" class="w-full rounded-xl border-slate-300 px-3 py-2.5 shadow-sm focus:border-orange-500 focus:ring-orange-500">
                                    <p class="text-xs text-slate-500 mt-2">Used for planning</p>
                                </div>

                                <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                                    <label class="block text-sm font-medium text-slate-700 mb-2">Weaning to Mating Rest (Days)</label>
                                    <input name="weaning_to_mating_rest" type="number" value="{{ old('weaning_to_mating_rest', $breeding['weaning_to_mating_rest']) }}" min="0" max="60" placeholder="Set days" class="w-full rounded-xl border-slate-300 px-3 py-2.5 shadow-sm focus:border-orange-500 focus:ring-orange-500">
                                    <p class="text-xs text-slate-500 mt-2">Rest period</p>
                                </div>

                                <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                                    <label class="block text-sm font-medium text-slate-700 mb-2">Maximum Breeding Age (Days)</label>
                                    <input name="maximum_breeding_age" type="number" value="{{ old('maximum_breeding_age', $breeding['maximum_breeding_age']) }}" min="365" max="3650" placeholder="Set days" class="w-full rounded-xl border-slate-300 px-3 py-2.5 shadow-sm focus:border-orange-500 focus:ring-orange-500">
                                    <p class="text-xs text-slate-500 mt-2">~6 years max</p>
                                </div>

                                <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                                    <label class="block text-sm font-medium text-slate-700 mb-2">Lactation Period (Days)</label>
                                    <input name="lactation_days" type="number" value="{{ old('lactation_days', $breeding['lactation_days']) }}" min="21" max="120" placeholder="Set days" class="w-full rounded-xl border-slate-300 px-3 py-2.5 shadow-sm focus:border-orange-500 focus:ring-orange-500">
                                    <p class="text-xs text-slate-500 mt-2">Same as weaning</p>
                                </div>
                            </div>
                        </section>

                        <!-- Growth Stages -->
                        <section id="growth-stages" data-setup-panel role="tabpanel" class="scroll-mt-24 rounded-3xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                            <div class="px-6 py-5 border-b border-slate-100 bg-slate-50/80">
                                <div class="flex items-start gap-4">
                                    <div class="shrink-0 rounded-2xl bg-yellow-100 p-3">
                                        <svg class="h-6 w-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m2-4h.01"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <h3 class="text-lg font-semibold text-slate-900">Growth Stages & Age Requirements</h3>
                                        <p class="text-sm text-slate-500 mt-1">
                                            Define the maximum age in days for each stage to support automatic reporting and feed calculations.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="p-6 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-5">
                                <div class="rounded-2xl border border-yellow-200 bg-yellow-50 p-5">
                                    <h4 class="font-semibold text-sm text-yellow-800 mb-3">1. Piglet Stage</h4>
                                    <p class="text-xs text-slate-500 mb-3">Age: 0 days to...</p>
                                    <label class="block text-sm font-medium text-slate-700 mb-2">Max Age (days)</label>
                                    <input name="piglet_max_days" type="number" min="1" max="300"
                                           value="{{ old('piglet_max_days', $stages['piglet_max_days']) }}" placeholder="Set maximum age"
                                           class="w-full rounded-xl border-slate-300 px-3 py-2.5 shadow-sm focus:border-yellow-500 focus:ring-yellow-500">
                                </div>

                                <div class="rounded-2xl border border-yellow-200 bg-yellow-50 p-5">
                                    <h4 class="font-semibold text-sm text-yellow-800 mb-3">2. Weaner Stage</h4>
                                    <p class="text-xs text-slate-500 mb-3">Start Age: {{ $stages['piglet_max_days'] !== null ? ((int) $stages['piglet_max_days'] + 1).' days' : 'set Piglet stage first' }}</p>
                                    <label class="block text-sm font-medium text-slate-700 mb-2">Max Age (days)</label>
                                    <input name="weaner_max_days" type="number" min="{{ (int)$stages['piglet_max_days'] + 2 }}" max="400"
                                           value="{{ old('weaner_max_days', $stages['weaner_max_days']) }}" placeholder="Set maximum age"
                                           class="w-full rounded-xl border-slate-300 px-3 py-2.5 shadow-sm focus:border-yellow-500 focus:ring-yellow-500">
                                </div>

                                <div class="rounded-2xl border border-yellow-200 bg-yellow-50 p-5">
                                    <h4 class="font-semibold text-sm text-yellow-800 mb-3">3. Grower Stage</h4>
                                    <p class="text-xs text-slate-500 mb-3">Start Age: {{ $stages['weaner_max_days'] !== null ? ((int) $stages['weaner_max_days'] + 1).' days' : 'set Weaner stage first' }}</p>
                                    <label class="block text-sm font-medium text-slate-700 mb-2">Max Age (days)</label>
                                    <input name="grower_max_days" type="number" min="{{ (int)$stages['weaner_max_days'] + 2 }}" max="600"
                                           value="{{ old('grower_max_days', $stages['grower_max_days']) }}" placeholder="Set maximum age"
                                           class="w-full rounded-xl border-slate-300 px-3 py-2.5 shadow-sm focus:border-yellow-500 focus:ring-yellow-500">
                                </div>

                                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5 flex flex-col justify-between">
                                    <div>
                                        <h4 class="font-semibold text-sm text-slate-900 mb-3">4. Finisher Stage</h4>
                                        <p class="text-xs text-slate-500 mb-3">Start Age: {{ $stages['grower_max_days'] !== null ? ((int) $stages['grower_max_days'] + 1).' days' : 'set Grower stage first' }}</p>
                                    </div>
                                    <p class="text-xs text-slate-600 leading-5">
                                        The Finisher stage begins automatically after the Grower maximum age is reached and continues until sale.
                                    </p>
                                </div>
                            </div>
                        </section>

                        <!-- Pricing -->
                        <section id="sales-pricing" data-setup-panel role="tabpanel" class="scroll-mt-24 rounded-3xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                            <div class="px-6 py-5 border-b border-slate-100 bg-slate-50/80">
                                <div class="flex items-start gap-4">
                                    <div class="shrink-0 rounded-2xl bg-blue-100 p-3">
                                        <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <h3 class="text-lg font-semibold text-slate-900">Pricing Configuration</h3>
                                        <p class="text-sm text-slate-500 mt-1">Set the sales price per kilogram for live and carcass weight.</p>
                                    </div>
                                </div>
                            </div>

                            <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-5">
                                <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                                    <label class="block text-sm font-medium text-slate-700 mb-2">Live Weight Price (₵/kg)</label>
                                    <input name="live_weight_price" type="number" value="{{ old('live_weight_price', $pricing['live_weight_price']) }}" min="1" max="1000" step="0.01" placeholder="Set price per kg" class="w-full rounded-xl border-slate-300 px-3 py-2.5 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    <p class="text-xs text-slate-500 mt-2">Price per kg for live weight sales</p>
                                </div>

                                <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                                    <label class="block text-sm font-medium text-slate-700 mb-2">Carcass Weight Price (₵/kg)</label>
                                    <input name="carcass_weight_price" type="number" value="{{ old('carcass_weight_price', $pricing['carcass_weight_price']) }}" min="1" max="1000" step="0.01" placeholder="Set price per kg" class="w-full rounded-xl border-slate-300 px-3 py-2.5 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    <p class="text-xs text-slate-500 mt-2">Price per kg for carcass sales</p>
                                </div>
                            </div>
                        </section>

                        <!-- Actions -->
                        <div class="pt-2">
                            <div id="setup-save-bar" class="setup-save-bar flex flex-col gap-3 rounded-2xl p-3 sm:flex-row sm:items-center sm:justify-between sm:p-4">
                                <p class="px-2 text-sm font-semibold text-slate-600">Save changes made to breeding, feed, growth and pricing settings.</p>
                                <div class="flex flex-col gap-3 sm:flex-row">
                                <button formaction="{{ route('settings.setup.breeding.reset') }}"
                                        formmethod="POST"
                                        type="submit"
                                        class="inline-flex items-center justify-center gap-2 rounded-2xl border border-slate-300 bg-white px-6 py-3 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50">
                                    @csrf
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                    </svg>
                                    Clear Farm Setup
                                </button>

                                <button type="submit"
                                        class="inline-flex items-center justify-center gap-2 rounded-2xl bg-green-600 px-6 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-green-700">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    Save Settings
                                </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const tabs = [...document.querySelectorAll('[data-setup-tab]')];
            const panels = [...document.querySelectorAll('[data-setup-panel]')];
            const saveBar = document.getElementById('setup-save-bar');
            const validIds = tabs.map(tab => tab.dataset.setupTab);

            if (!tabs.length || !panels.length) return;

            const activate = (id, updateUrl = true) => {
                if (!validIds.includes(id)) id = validIds[0];

                tabs.forEach(tab => {
                    const active = tab.dataset.setupTab === id;
                    tab.setAttribute('aria-selected', active ? 'true' : 'false');
                    tab.setAttribute('tabindex', active ? '0' : '-1');
                    tab.setAttribute('aria-controls', tab.dataset.setupTab);
                });

                panels.forEach(panel => {
                    const active = panel.id === id;
                    panel.hidden = !active;
                    panel.setAttribute('aria-hidden', active ? 'false' : 'true');
                });

                if (saveBar) saveBar.hidden = id === 'animal-breeds';
                if (updateUrl) history.replaceState(null, '', `#${id}`);
            };

            tabs.forEach((tab, index) => {
                tab.addEventListener('click', () => activate(tab.dataset.setupTab));
                tab.addEventListener('keydown', event => {
                    if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
                    event.preventDefault();
                    let next = index;
                    if (event.key === 'ArrowRight') next = (index + 1) % tabs.length;
                    if (event.key === 'ArrowLeft') next = (index - 1 + tabs.length) % tabs.length;
                    if (event.key === 'Home') next = 0;
                    if (event.key === 'End') next = tabs.length - 1;
                    tabs[next].focus();
                    activate(tabs[next].dataset.setupTab);
                });
            });

            window.addEventListener('hashchange', () => activate(location.hash.slice(1), false));
            activate(location.hash.slice(1) || validIds[0], false);
        });

        function feedItemsSettings(initialItems) {
            return {
                newItem: '',
                message: '',
                items: Array.isArray(initialItems)
                    ? initialItems.map(item => String(item || '').trim()).filter(item => item !== '')
                    : [],

                normalize(value) {
                    return String(value || '').trim().replace(/\s+/g, ' ');
                },

                cleanItems() {
                    return this.items
                        .map(item => this.normalize(item))
                        .filter(item => item !== '');
                },

                addItem() {
                    const value = this.normalize(this.newItem);
                    this.message = '';

                    if (!value) {
                        return;
                    }

                    const alreadyExists = this.cleanItems().some(item => item.toLowerCase() === value.toLowerCase());
                    if (alreadyExists) {
                        this.message = 'This feed item is already in the list.';
                        return;
                    }

                    this.items.push(value);
                    this.newItem = '';
                },

                removeItem(index) {
                    this.items.splice(index, 1);
                }
            }
        }
    </script>

</x-app-layout>
