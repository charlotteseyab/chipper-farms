<x-app-layout>
    <main class="min-h-screen bg-slate-50/70 py-2">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <form action="{{ route('settings.setup.breeding.save') }}" method="POST">
                @csrf

                <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">

                    <div class="p-6 md:p-8 space-y-8">
                        <!-- Breeding Cycle Section -->
                        <section class="rounded-3xl border border-slate-200 bg-white shadow-sm overflow-hidden">
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
                                    <input name="pregnancy_check_days" type="number" value="{{ old('pregnancy_check_days', $breeding['pregnancy_check_days'] ?? config('breeding.cycle.pregnancy_check_days')) }}" min="10" max="21" class="w-full rounded-xl border-slate-300 px-3 py-2.5 shadow-sm focus:border-green-500 focus:ring-green-500">
                                    <p class="text-xs text-slate-500 mt-2">Days after mating</p>
                                </div>

                                <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                                    <label class="block text-sm font-medium text-slate-700 mb-2">Gestation Period (Days)</label>
                                    <input name="gestation_days" type="number" value="{{ old('gestation_days', $breeding['gestation_days'] ?? config('breeding.cycle.gestation_days')) }}" min="110" max="120" class="w-full rounded-xl border-slate-300 px-3 py-2.5 shadow-sm focus:border-green-500 focus:ring-green-500">
                                    <p class="text-xs text-slate-500 mt-2">Mating to farrowing</p>
                                </div>

                                <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                                    <label class="block text-sm font-medium text-slate-700 mb-2">Birth to Weaning (Days)</label>
                                    <input name="birth_to_weaning_days" type="number" value="{{ old('birth_to_weaning_days', $breeding['birth_to_weaning_days'] ?? config('breeding.cycle.birth_to_weaning_days')) }}" min="35" max="60" class="w-full rounded-xl border-slate-300 px-3 py-2.5 shadow-sm focus:border-green-500 focus:ring-green-500">
                                    <p class="text-xs text-slate-500 mt-2">From birth</p>
                                </div>

                                <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                                    <label class="block text-sm font-medium text-slate-700 mb-2">Birth to Maturity (Days)</label>
                                    <input name="birth_to_maturity_days" type="number" value="{{ old('birth_to_maturity_days', $breeding['birth_to_maturity_days'] ?? config('breeding.cycle.birth_to_maturity_days')) }}" min="150" max="240" class="w-full rounded-xl border-slate-300 px-3 py-2.5 shadow-sm focus:border-green-500 focus:ring-green-500">
                                    <p class="text-xs text-slate-500 mt-2">From birth</p>
                                </div>
                            </div>
                        </section>

                        <!-- Breeding Ages Section -->
                        <section class="rounded-3xl border border-slate-200 bg-white shadow-sm overflow-hidden">
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
                                            <input name="minimum_breeding_age_female" type="number" value="{{ old('minimum_breeding_age_female', $breeding['minimum_breeding_age_female'] ?? config('breeding.parameters.minimum_breeding_age.female')) }}" min="180" max="365" class="w-full rounded-xl border-slate-300 px-3 py-2.5 shadow-sm focus:border-purple-500 focus:ring-purple-500">
                                            <p class="text-xs text-slate-500 mt-2">~7 months minimum</p>
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium text-slate-700 mb-2">Optimal First Breeding (Days)</label>
                                            <input name="optimal_first_breeding_age_female" type="number" value="{{ old('optimal_first_breeding_age_female', $breeding['optimal_first_breeding_age_female'] ?? config('breeding.parameters.optimal_first_breeding_age.female')) }}" min="200" max="365" class="w-full rounded-xl border-slate-300 px-3 py-2.5 shadow-sm focus:border-purple-500 focus:ring-purple-500">
                                            <p class="text-xs text-slate-500 mt-2">~8 months optimal</p>
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium text-slate-700 mb-2">Minimum Breeding Weight (kg)</label>
                                            <input name="minimum_breeding_weight_female" type="number" value="{{ old('minimum_breeding_weight_female', $breeding['minimum_breeding_weight_female'] ?? config('breeding.parameters.minimum_breeding_weight.female')) }}" min="90" max="150" class="w-full rounded-xl border-slate-300 px-3 py-2.5 shadow-sm focus:border-purple-500 focus:ring-purple-500">
                                            <p class="text-xs text-slate-500 mt-2">~90 kg minimum</p>
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium text-slate-700 mb-2">Optimal Breeding Weight (kg)</label>
                                            <input name="optimal_breeding_weight_female" type="number" value="{{ old('optimal_breeding_weight_female', $breeding['optimal_breeding_weight_female'] ?? config('breeding.parameters.optimal_breeding_weight.female')) }}" min="90" max="150" class="w-full rounded-xl border-slate-300 px-3 py-2.5 shadow-sm focus:border-purple-500 focus:ring-purple-500">
                                            <p class="text-xs text-slate-500 mt-2">~90 kg optimal</p>
                                        </div>
                                    </div>
                                </div>

                                <div class="rounded-2xl border border-purple-100 bg-purple-50/50 p-5">
                                    <h4 class="font-semibold text-slate-900 text-sm mb-4">Male Pigs</h4>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-slate-700 mb-2">Minimum Breeding Age (Days)</label>
                                            <input name="minimum_breeding_age_male" type="number" value="{{ old('minimum_breeding_age_male', $breeding['minimum_breeding_age_male'] ?? config('breeding.parameters.minimum_breeding_age.male')) }}" min="180" max="365" class="w-full rounded-xl border-slate-300 px-3 py-2.5 shadow-sm focus:border-purple-500 focus:ring-purple-500">
                                            <p class="text-xs text-slate-500 mt-2">~8 months minimum</p>
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium text-slate-700 mb-2">Optimal First Breeding (Days)</label>
                                            <input name="optimal_first_breeding_age_male" type="number" value="{{ old('optimal_first_breeding_age_male', $breeding['optimal_first_breeding_age_male'] ?? config('breeding.parameters.optimal_first_breeding_age.male')) }}" min="240" max="365" class="w-full rounded-xl border-slate-300 px-3 py-2.5 shadow-sm focus:border-purple-500 focus:ring-purple-500">
                                            <p class="text-xs text-slate-500 mt-2">~10 months optimal</p>
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium text-slate-700 mb-2">Minimum Breeding Weight (kg)</label>
                                            <input name="minimum_breeding_weight_male" type="number" value="{{ old('minimum_breeding_weight_male', $breeding['minimum_breeding_weight_male'] ?? config('breeding.parameters.minimum_breeding_weight.male')) }}" min="80" max="150" class="w-full rounded-xl border-slate-300 px-3 py-2.5 shadow-sm focus:border-purple-500 focus:ring-purple-500">
                                            <p class="text-xs text-slate-500 mt-2">~80 kg minimum</p>
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium text-slate-700 mb-2">Optimal Breeding Weight (kg)</label>
                                            <input name="optimal_breeding_weight_male" type="number" value="{{ old('optimal_breeding_weight_male', $breeding['optimal_breeding_weight_male'] ?? config('breeding.parameters.optimal_breeding_weight.male')) }}" min="80" max="150" class="w-full rounded-xl border-slate-300 px-3 py-2.5 shadow-sm focus:border-purple-500 focus:ring-purple-500">
                                            <p class="text-xs text-slate-500 mt-2">~80 kg optimal</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <!-- Feed Bag Weights Section -->
                        <section class="rounded-3xl border border-slate-200 bg-white shadow-sm overflow-hidden">
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
                                    <input type="number" step="0.01" min="0.01" name="default_bag_weight_kg" value="{{ old('default_bag_weight_kg', $feedBagWeights['default_bag_weight_kg'] ?? 50) }}" class="w-full rounded-xl border-slate-300 px-3 py-2.5 shadow-sm focus:border-green-500 focus:ring-green-500">
                                    <p class="text-xs text-slate-500 mt-2">Fallback for ingredients without a specific bag weight.</p>
                                </div>

                                <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                                    <label class="block text-sm font-medium text-slate-700 mb-2">Maize Bag Weight (kg)</label>
                                    <input type="number" step="0.01" min="0.01" name="maize_bag_weight_kg" value="{{ old('maize_bag_weight_kg', $feedBagWeights['maize_bag_weight_kg'] ?? 50) }}" class="w-full rounded-xl border-slate-300 px-3 py-2.5 shadow-sm focus:border-green-500 focus:ring-green-500">
                                    <p class="text-xs text-slate-500 mt-2">Used for maize purchase estimates.</p>
                                </div>

                                <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                                    <label class="block text-sm font-medium text-slate-700 mb-2">Soybean Meal Bag Weight (kg)</label>
                                    <input type="number" step="0.01" min="0.01" name="soybean_meal_bag_weight_kg" value="{{ old('soybean_meal_bag_weight_kg', $feedBagWeights['soybean_meal_bag_weight_kg'] ?? 50) }}" class="w-full rounded-xl border-slate-300 px-3 py-2.5 shadow-sm focus:border-green-500 focus:ring-green-500">
                                    <p class="text-xs text-slate-500 mt-2">Used for soybean meal purchase estimates.</p>
                                </div>

                                <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                                    <label class="block text-sm font-medium text-slate-700 mb-2">Wheat Bran Bag Weight (kg)</label>
                                    <input type="number" step="0.01" min="0.01" name="wheat_bran_bag_weight_kg" value="{{ old('wheat_bran_bag_weight_kg', $feedBagWeights['wheat_bran_bag_weight_kg'] ?? 50) }}" class="w-full rounded-xl border-slate-300 px-3 py-2.5 shadow-sm focus:border-green-500 focus:ring-green-500">
                                    <p class="text-xs text-slate-500 mt-2">Used for wheat bran purchase estimates.</p>
                                </div>

                                <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                                    <label class="block text-sm font-medium text-slate-700 mb-2">Premix Bag Weight (kg)</label>
                                    <input type="number" step="0.01" min="0.01" name="premix_bag_weight_kg" value="{{ old('premix_bag_weight_kg', $feedBagWeights['premix_bag_weight_kg'] ?? 25) }}" class="w-full rounded-xl border-slate-300 px-3 py-2.5 shadow-sm focus:border-green-500 focus:ring-green-500">
                                    <p class="text-xs text-slate-500 mt-2">Used for premix purchase estimates.</p>
                                </div>

                                <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                                    <label class="block text-sm font-medium text-slate-700 mb-2">Concentrate Bag Weight (kg)</label>
                                    <input type="number" step="0.01" min="0.01" name="concentrate_bag_weight_kg" value="{{ old('concentrate_bag_weight_kg', $feedBagWeights['concentrate_bag_weight_kg'] ?? 50) }}" class="w-full rounded-xl border-slate-300 px-3 py-2.5 shadow-sm focus:border-green-500 focus:ring-green-500">
                                    <p class="text-xs text-slate-500 mt-2">Used for concentrate purchase estimates.</p>
                                </div>
                            </div>
                        </section>

                        <!-- Purchase Request Feed Items -->
                        <section id="purchase-feed-items"
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
                        <section class="rounded-3xl border border-slate-200 bg-white shadow-sm overflow-hidden">
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
                                    <input name="average_litter_size" type="number" value="{{ old('average_litter_size', $breeding['average_litter_size'] ?? config('breeding.parameters.average_litter_size')) }}" min="1" max="25" step="0.5" class="w-full rounded-xl border-slate-300 px-3 py-2.5 shadow-sm focus:border-orange-500 focus:ring-orange-500">
                                    <p class="text-xs text-slate-500 mt-2">Used for planning</p>
                                </div>

                                <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                                    <label class="block text-sm font-medium text-slate-700 mb-2">Weaning to Mating Rest (Days)</label>
                                    <input name="weaning_to_mating_rest" type="number" value="{{ old('weaning_to_mating_rest', $breeding['weaning_to_mating_rest'] ?? config('breeding.parameters.weaning_to_mating_rest')) }}" min="0" max="60" class="w-full rounded-xl border-slate-300 px-3 py-2.5 shadow-sm focus:border-orange-500 focus:ring-orange-500">
                                    <p class="text-xs text-slate-500 mt-2">Rest period</p>
                                </div>

                                <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                                    <label class="block text-sm font-medium text-slate-700 mb-2">Maximum Breeding Age (Days)</label>
                                    <input name="maximum_breeding_age" type="number" value="{{ old('maximum_breeding_age', $breeding['maximum_breeding_age'] ?? config('breeding.parameters.maximum_breeding_age')) }}" min="365" max="3650" class="w-full rounded-xl border-slate-300 px-3 py-2.5 shadow-sm focus:border-orange-500 focus:ring-orange-500">
                                    <p class="text-xs text-slate-500 mt-2">~6 years max</p>
                                </div>

                                <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                                    <label class="block text-sm font-medium text-slate-700 mb-2">Lactation Period (Days)</label>
                                    <input name="lactation_days" type="number" value="{{ old('lactation_days', $breeding['lactation_days'] ?? config('breeding.parameters.lactation_days')) }}" min="21" max="120" class="w-full rounded-xl border-slate-300 px-3 py-2.5 shadow-sm focus:border-orange-500 focus:ring-orange-500">
                                    <p class="text-xs text-slate-500 mt-2">Same as weaning</p>
                                </div>
                            </div>
                        </section>

                        <!-- Growth Stages -->
                        <section class="rounded-3xl border border-slate-200 bg-white shadow-sm overflow-hidden">
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
                                           value="{{ old('piglet_max_days', $stages['piglet_max_days']) }}"
                                           class="w-full rounded-xl border-slate-300 px-3 py-2.5 shadow-sm focus:border-yellow-500 focus:ring-yellow-500">
                                </div>

                                <div class="rounded-2xl border border-yellow-200 bg-yellow-50 p-5">
                                    <h4 class="font-semibold text-sm text-yellow-800 mb-3">2. Weaner Stage</h4>
                                    <p class="text-xs text-slate-500 mb-3">Start Age: {{ (int)$stages['piglet_max_days'] + 1 }} days</p>
                                    <label class="block text-sm font-medium text-slate-700 mb-2">Max Age (days)</label>
                                    <input name="weaner_max_days" type="number" min="{{ (int)$stages['piglet_max_days'] + 2 }}" max="400"
                                           value="{{ old('weaner_max_days', $stages['weaner_max_days']) }}"
                                           class="w-full rounded-xl border-slate-300 px-3 py-2.5 shadow-sm focus:border-yellow-500 focus:ring-yellow-500">
                                </div>

                                <div class="rounded-2xl border border-yellow-200 bg-yellow-50 p-5">
                                    <h4 class="font-semibold text-sm text-yellow-800 mb-3">3. Grower Stage</h4>
                                    <p class="text-xs text-slate-500 mb-3">Start Age: {{ (int)$stages['weaner_max_days'] + 1 }} days</p>
                                    <label class="block text-sm font-medium text-slate-700 mb-2">Max Age (days)</label>
                                    <input name="grower_max_days" type="number" min="{{ (int)$stages['weaner_max_days'] + 2 }}" max="600"
                                           value="{{ old('grower_max_days', $stages['grower_max_days']) }}"
                                           class="w-full rounded-xl border-slate-300 px-3 py-2.5 shadow-sm focus:border-yellow-500 focus:ring-yellow-500">
                                </div>

                                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5 flex flex-col justify-between">
                                    <div>
                                        <h4 class="font-semibold text-sm text-slate-900 mb-3">4. Finisher Stage</h4>
                                        <p class="text-xs text-slate-500 mb-3">Start Age: {{ (int)$stages['grower_max_days'] + 1 }} days</p>
                                    </div>
                                    <p class="text-xs text-slate-600 leading-5">
                                        The Finisher stage begins automatically after the Grower maximum age is reached and continues until sale.
                                    </p>
                                </div>
                            </div>
                        </section>

                        <!-- Pricing -->
                        <section class="rounded-3xl border border-slate-200 bg-white shadow-sm overflow-hidden">
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
                                    <input name="live_weight_price" type="number" value="{{ old('live_weight_price', $pricing['live_weight_price'] ?? config('sales.pricing.live_weight_price', 30)) }}" min="1" max="1000" step="0.01" class="w-full rounded-xl border-slate-300 px-3 py-2.5 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    <p class="text-xs text-slate-500 mt-2">Price per kg for live weight sales</p>
                                </div>

                                <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                                    <label class="block text-sm font-medium text-slate-700 mb-2">Carcass Weight Price (₵/kg)</label>
                                    <input name="carcass_weight_price" type="number" value="{{ old('carcass_weight_price', $pricing['carcass_weight_price'] ?? config('sales.pricing.carcass_weight_price', 50)) }}" min="1" max="1000" step="0.01" class="w-full rounded-xl border-slate-300 px-3 py-2.5 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    <p class="text-xs text-slate-500 mt-2">Price per kg for carcass sales</p>
                                </div>
                            </div>
                        </section>

                        <!-- Actions -->
                        <div class="pt-2">
                            <div class="flex flex-col sm:flex-row justify-end gap-4">
                                <button formaction="{{ route('settings.setup.breeding.reset') }}"
                                        formmethod="POST"
                                        type="submit"
                                        class="inline-flex items-center justify-center gap-2 rounded-2xl border border-slate-300 bg-white px-6 py-3 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50">
                                    @csrf
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                    </svg>
                                    Reset to Defaults
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
            </form>
        </div>
    </main>

    <script>
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