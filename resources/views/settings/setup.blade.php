<x-app-layout>
    <main class="flex-1 p-6 min-h-screen">
        <div class="max-w-7xl mx-auto">
            <!-- Header -->
            <!-- <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900 mb-2">System Configuration</h1>
                <p class="text-gray-600">Configure your farm's breeding parameters and system settings</p>
            </div> -->

            <form action="{{ route('settings.setup.breeding.save') }}" method="POST">
                @csrf
                
                <!-- Main Settings Container -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <!-- Settings Tabs -->
                    <div class="border-b border-gray-200">
                        <nav class="flex space-x-8 px-6" aria-label="Tabs">
                            <button type="button" class="border-b-2 border-green-500 py-4 px-1 text-sm font-medium text-green-600">
                                Breeding Cycle
                            </button>
                            <!-- <button type="button" class="border-b-2 border-transparent py-4 px-1 text-sm font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300">
                                Farm Info
                            </button>
                            <button type="button" class="border-b-2 border-transparent py-4 px-1 text-sm font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300">
                                Advanced
                            </button> -->
                        </nav>
                    </div>
                    
                    <!-- Settings Content -->
                    <div class="p-6">
                        <!-- Breeding Cycle Section -->
                        <div class="space-y-6">
                            <div class="flex items-center mb-6">
                                <div class="bg-green-100 p-3 rounded-lg mr-4">
                                    <svg class="h-6 w-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-900">Breeding Cycle Parameters</h3>
                                    <p class="text-gray-500 text-sm">Configure the timing of your breeding operations</p>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Pregnancy Check (Days)</label>
                                    <input name="pregnancy_check_days" type="number" value="{{ old('pregnancy_check_days', $breeding['pregnancy_check_days'] ?? config('breeding.cycle.pregnancy_check_days')) }}" min="10" max="21" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors">
                                    <p class="text-xs text-gray-500 mt-1">Days after mating</p>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Gestation Period (Days)</label>
                                    <input name="gestation_days" type="number" value="{{ old('gestation_days', $breeding['gestation_days'] ?? config('breeding.cycle.gestation_days')) }}" min="110" max="120" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors">
                                    <p class="text-xs text-gray-500 mt-1">Mating to farrowing</p>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Birth to Weaning (Days)</label>
                                    <input name="birth_to_weaning_days" type="number" value="{{ old('birth_to_weaning_days', $breeding['birth_to_weaning_days'] ?? config('breeding.cycle.birth_to_weaning_days')) }}" min="35" max="60" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors">
                                    <p class="text-xs text-gray-500 mt-1">From birth</p>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Birth to Maturity (Days)</label>
                                    <input name="birth_to_maturity_days" type="number" value="{{ old('birth_to_maturity_days', $breeding['birth_to_maturity_days'] ?? config('breeding.cycle.birth_to_maturity_days')) }}" min="150" max="240" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors">
                                    <p class="text-xs text-gray-500 mt-1">From birth</p>
                                </div>
                            </div>
                        </div>

                        <hr class="my-8 border-gray-200">

                        <!-- Breeding Ages Section -->
                        <div class="space-y-6">
                            <div class="flex items-center mb-6">
                                <div class="bg-purple-100 p-3 rounded-lg mr-4">
                                    <svg class="h-6 w-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-900">Breeding Age and Weight Requirements</h3>
                                    <p class="text-gray-500 text-sm">Set minimum and optimal breeding ages and weights for your pigs</p>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="space-y-4">
                                    <h4 class="font-medium text-gray-900 text-sm">Female Pigs</h4>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Minimum Breeding Age (Days)</label>
                                        <input name="minimum_breeding_age_female" type="number" value="{{ old('minimum_breeding_age_female', $breeding['minimum_breeding_age_female'] ?? config('breeding.parameters.minimum_breeding_age.female')) }}" min="180" max="365" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors">
                                        <p class="text-xs text-gray-500 mt-1">~7 months minimum</p>
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Optimal First Breeding (Days)</label>
                                        <input name="optimal_first_breeding_age_female" type="number" value="{{ old('optimal_first_breeding_age_female', $breeding['optimal_first_breeding_age_female'] ?? config('breeding.parameters.optimal_first_breeding_age.female')) }}" min="200" max="365" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors">
                                        <p class="text-xs text-gray-500 mt-1">~8 months optimal</p>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Minimum Breeding Weight (kg)</label>
                                        <input name="minimum_breeding_weight_female" type="number" value="{{ old('minimum_breeding_weight_female', $breeding['minimum_breeding_weight_female'] ?? config('breeding.parameters.minimum_breeding_weight.female')) }}" min="90" max="150" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors">
                                        <p class="text-xs text-gray-500 mt-1">~90 kg minimum</p>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Optimal Breeding Weight (kg)</label>
                                        <input name="optimal_breeding_weight_female" type="number" value="{{ old('optimal_breeding_weight_female', $breeding['optimal_breeding_weight_female'] ?? config('breeding.parameters.optimal_breeding_weight.female')) }}" min="90" max="150" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors">
                                        <p class="text-xs text-gray-500 mt-1">~90 kg optimal</p>
                                    </div>
                                </div>
                                
                                <div class="space-y-4">
                                    <h4 class="font-medium text-gray-900 text-sm">Male Pigs</h4>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Minimum Breeding Age (Days)</label>
                                        <input name="minimum_breeding_age_male" type="number" value="{{ old('minimum_breeding_age_male', $breeding['minimum_breeding_age_male'] ?? config('breeding.parameters.minimum_breeding_age.male')) }}" min="180" max="365" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors">
                                        <p class="text-xs text-gray-500 mt-1">~8 months minimum</p>
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Optimal First Breeding (Days)</label>
                                        <input name="optimal_first_breeding_age_male" type="number" value="{{ old('optimal_first_breeding_age_male', $breeding['optimal_first_breeding_age_male'] ?? config('breeding.parameters.optimal_first_breeding_age.male')) }}" min="240" max="365" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors">
                                        <p class="text-xs text-gray-500 mt-1">~10 months optimal</p>
                                    </div>

                                    <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">Minimum Breeding Weight (kg)</label>
                                        <input name="minimum_breeding_weight_male" type="number" value="{{ old('minimum_breeding_weight_male', $breeding['minimum_breeding_weight_male'] ?? config('breeding.parameters.minimum_breeding_weight.male')) }}" min="80" max="150" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors">
                                        <p class="text-xs text-gray-500 mt-1">~80 kg minimum</p>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Optimal Breeding Weight (kg)</label>
                                        <input name="optimal_breeding_weight_male" type="number" value="{{ old('optimal_breeding_weight_male', $breeding['optimal_breeding_weight_male'] ?? config('breeding.parameters.optimal_breeding_weight.male')) }}" min="80" max="150" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors">
                                        <p class="text-xs text-gray-500 mt-1">~80 kg optimal</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    
                        <hr class="my-8 border-gray-200">

                        <!-- Additional Parameters Section -->
                        <div class="space-y-6">
                            <div class="flex items-center mb-6">
                                <div class="bg-orange-100 p-3 rounded-lg mr-4">
                                    <svg class="h-6 w-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-900">Additional Breeding Parameters</h3>
                                    <p class="text-gray-500 text-sm">Configure other important breeding settings</p>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Average Litter Size</label>
                                    <input name="average_litter_size" type="number" value="{{ old('average_litter_size', $breeding['average_litter_size'] ?? config('breeding.parameters.average_litter_size')) }}" min="1" max="25" step="0.5" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition-colors">
                                    <p class="text-xs text-gray-500 mt-1">For planning</p>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Weaning to Mating Rest (Days)</label>
                                    <input name="weaning_to_mating_rest" type="number" value="{{ old('weaning_to_mating_rest', $breeding['weaning_to_mating_rest'] ?? config('breeding.parameters.weaning_to_mating_rest')) }}" min="0" max="60" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition-colors">
                                    <p class="text-xs text-gray-500 mt-1">Rest period</p>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Maximum Breeding Age (Days)</label>
                                    <input name="maximum_breeding_age" type="number" value="{{ old('maximum_breeding_age', $breeding['maximum_breeding_age'] ?? config('breeding.parameters.maximum_breeding_age')) }}" min="365" max="3650" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition-colors">
                                    <p class="text-xs text-gray-500 mt-1">~6 years max</p>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Lactation Period (Days)</label>
                                    <input name="lactation_days" type="number" value="{{ old('lactation_days', $breeding['lactation_days'] ?? config('breeding.parameters.lactation_days')) }}" min="21" max="120" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition-colors">
                                    <p class="text-xs text-gray-500 mt-1">Same as weaning</p>
                                </div>
                            </div>
                        </div>
                    
                        <hr class="my-8 border-gray-200">

                        <!-- Pricing Configuration Section -->
                        <div class="space-y-6">
                            <div class="flex items-center mb-6">
                                <div class="bg-blue-100 p-3 rounded-lg mr-4">
                                    <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"/>
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-900">Pricing Configuration</h3>
                                    <p class="text-gray-500 text-sm">Set the price per kg for live and carcass weight sales</p>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Live Weight Price (₵/kg)</label>
                                    <input name="live_weight_price" type="number" value="{{ old('live_weight_price', $pricing['live_weight_price'] ?? config('sales.pricing.live_weight_price', 30)) }}" min="1" max="1000" step="0.01" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                                    <p class="text-xs text-gray-500 mt-1">Price per kg for live weight sales</p>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Carcass Weight Price (₵/kg)</label>
                                    <input name="carcass_weight_price" type="number" value="{{ old('carcass_weight_price', $pricing['carcass_weight_price'] ?? config('sales.pricing.carcass_weight_price', 50)) }}" min="1" max="1000" step="0.01" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                                    <p class="text-xs text-gray-500 mt-1">Price per kg for carcass weight sales</p>
                                </div>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="mt-8 flex flex-col sm:flex-row justify-end gap-4">
                            <button formaction="{{ route('settings.setup.breeding.reset') }}" formmethod="POST" type="submit" class="px-6 py-3 bg-white text-gray-700 rounded-lg hover:bg-gray-50 transition-colors shadow-sm border border-gray-300 font-medium flex items-center justify-center gap-2">
                                @csrf
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                </svg>
                                Reset to Defaults
                            </button>
                            <button type="submit" class="px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors shadow-sm font-medium flex items-center justify-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                Save Settings
                            </button>
                        </div>
                </div>
            </form>
        </div>
    </main>
    <x-copyright-minimal />
</x-app-layout>
