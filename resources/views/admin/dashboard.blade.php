<x-app-layout>
    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Header -->
            <!-- <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900">Admin Dashboard</h1>
                <p class="text-gray-600 mt-2">System overview and analytics</p>
            </div> -->

            <!-- Key Metrics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6 mb-8">
                @php
                    $totalUsers = \App\Models\User::where('role_id', '!=', '4')->count();                    $activeUsers = \App\Models\User::where('created_at', '>=', now()->subDays(30))->count();
                    $criticalAlerts = \App\Models\WeightAlert::where('severity', 'high')->where('acknowledged', false)->count();
                    $totalAlerts = \App\Models\WeightAlert::where('acknowledged', false)->count();
                    
                    // Enhanced pig counting including piglets
                    // Weaned pigs = active pigs only (exclude deceased/sold)
                    $weanedPigs = \App\Models\PigManagement::active()->count();
                    $weaningAge = config('breeding.cycle.birth_to_weaning_days', 45);
                    // Piglets = sum of net live piglets (live_births - piglet death counts) for active, recent litters
                    $recentFarrowings = \App\Models\Farrowing::with('pigletDeaths')
                        ->where('farrowing_date', '>=', now()->subDays($weaningAge))
                        ->where('status', 'active')
                        ->get();
                    $pigletsCount = 0;
                    foreach ($recentFarrowings as $f) {
                        $pigletsCount += max(0, (int)($f->live_births ?? 0) - (int)$f->pigletDeaths->sum('count'));
                    }
                    $totalPigs = $weanedPigs + $pigletsCount;
                    
                    $totalWeights = \App\Models\PigWeight::count();
                    $totalSales = \App\Models\PigSale::count();
                    $totalDeaths = \App\Models\PigDeath::count() + (int) \App\Models\PigletDeath::sum('count');
                    $activePigs = \App\Models\PigManagement::active()->count();
                    $deceasedPigs = \App\Models\PigManagement::deceased()->count();
                    $soldPigs = \App\Models\PigManagement::sold()->count();
                    
                    // Weighing alerts
                    $overdueForWeighing = \App\Models\PigManagement::countOverdueForWeighing();
                    $dueForWeighing = \App\Models\PigManagement::countDueForWeighing();
                @endphp

                <!-- Users -->
                <a href="#" class="block bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl shadow-lg p-6 text-white hover:shadow-xl transition-all duration-200 transform hover:scale-105">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-blue-100 text-sm font-medium">Total Users</p>
                            <p class="text-3xl font-bold">{{ $totalUsers }}</p>
                            <!-- <p class="text-blue-100 text-xs mt-1">{{ $activeUsers }} active this month</p> -->
                        </div>
                        <div class="w-12 h-12 bg-white bg-opacity-20 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"/>
                            </svg>
                        </div>
                    </div>
                </a>

                <!-- Total Pigs -->
                <a href="{{ route('all-stocks.index') }}" class="block bg-gradient-to-br from-green-500 to-green-600 rounded-xl shadow-lg p-6 text-white hover:shadow-xl transition-all duration-200 transform hover:scale-105">
    <div class="flex items-center justify-between">
        <div>
            <p class="text-green-100 text-sm font-medium">Total Pigs</p>
            <p class="text-3xl font-bold">{{ number_format($totalPigs) }}</p>
            <p class="text-green-100 text-xs mt-1">{{ \App\Models\PigManagement::active()->where('gender', 'M')->count() }} males • {{ \App\Models\PigManagement::active()->where('gender', 'F')->count() }} females • {{ number_format($pigletsCount) }} piglets</p>
        </div>
        <div class="w-12 h-12 bg-white bg-opacity-20 rounded-lg flex items-center justify-center">
            <i class="fas fa-piggy-bank text-2xl"></i>
        </div>
    </div>
</a>

                <!-- Sales -->
                <a href="{{ route('sales.index') }}" class="block bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl shadow-lg p-6 text-white hover:shadow-xl transition-all duration-300 transform hover:scale-105">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-purple-100 text-sm font-medium">Total Sales</p>
                            <p class="text-3xl font-bold">{{ number_format($totalSales) }}</p>
                            <p class="text-purple-100 text-xs mt-1">Sales recorded (No)</p>
                            @php
                                $maleSales = \App\Models\PigSale::whereHas('pig', function($q){ $q->where('gender','M'); })->count();
                                $femaleSales = \App\Models\PigSale::whereHas('pig', function($q){ $q->where('gender','F'); })->count();
                            @endphp
                            <p class="text-purple-100 text-xs mt-1">{{ number_format($maleSales) }} males • {{ number_format($femaleSales) }} females</p>
                        </div>
                        <div class="w-12 h-12 bg-white bg-opacity-20 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 10h6M9 14h6M9 18h6"/>
                        </svg>
                        </div>
                    </div>
                </a>

                <!-- Death Records -->
                <a href="{{ route('deaths.index') }}" class="block bg-gradient-to-br from-red-500 to-red-600 rounded-xl shadow-lg p-6 text-white hover:shadow-xl transition-all duration-300 transform hover:scale-105">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-red-100 text-sm font-medium">Death Records</p>
                            <p class="text-3xl font-bold">{{ number_format($totalDeaths) }}</p>
                            <p class="text-red-100 text-xs mt-1">
                                {{ number_format(\App\Models\PigDeath::count()) }} pigs • {{ number_format((int) \App\Models\PigletDeath::sum('count')) }} piglets
                            </p>
                        </div>
                        <div class="w-12 h-12 bg-white bg-opacity-20 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                            </svg>
                        </div>
                    </div>
                </a>

                <!-- Alerts -->
                <!-- <a href="{{ route('weight-alerts.index', ['acknowledged' => '0']) }}" class="block bg-gradient-to-br {{ $criticalAlerts > 5 ? 'from-orange-500 to-orange-600' : ($criticalAlerts > 0 ? 'from-yellow-500 to-yellow-600' : 'from-gray-500 to-gray-600') }} rounded-xl shadow-lg p-6 text-white hover:shadow-xl transition-all duration-300 transform hover:scale-105">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-white text-opacity-80 text-sm font-medium">Active Alerts</p>
                            <p class="text-3xl font-bold">{{ $totalAlerts }}</p>
                            <p class="text-white text-opacity-80 text-xs mt-1">{{ $criticalAlerts }} critical</p>
                        </div>
                        <div class="w-12 h-12 bg-white bg-opacity-20 rounded-lg flex items-center justify-center">
                            @if($criticalAlerts > 5)
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                                </svg>
                            @elseif($criticalAlerts > 0)
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                                </svg>
                            @else
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            @endif
                        </div>
                    </div>
                </a> -->

                <!-- Weighing Alerts -->
                <button id="weighing-alerts-card" onclick="toggleWeighingAlertsModal()" class="block w-full bg-gradient-to-br from-yellow-500 to-yellow-600 rounded-xl shadow-lg p-6 text-white hover:shadow-xl transition-all duration-300 transform hover:scale-105">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-yellow-100 text-sm font-medium">Weighing Alerts</p>
                            <p class="text-3xl font-bold">{{ $overdueForWeighing }}</p>
                            <p class="text-yellow-100 text-xs mt-1">{{ $dueForWeighing }} due for weighing</p>
                        </div>
                        <div class="w-12 h-12 bg-white bg-opacity-20 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/>
                            </svg>
                        </div>
                    </div>
                </button>
            </div>

            <!-- Additional Metrics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                @php
                    // Calculate additional metrics
                    $breedingStock = \App\Models\PigManagement::where('stock_type', 'breeding')->count();
                    $standardStock = \App\Models\PigManagement::where('stock_type', 'standard')->count();
                    $activePigs = \App\Models\PigManagement::active()->count();
                    // Calculate average of latest weight per pig (more meaningful than all-time average)
                    $avgWeight = \App\Models\PigWeight::whereIn('id', function($query) {
                        $query->select(\DB::raw('MAX(id)'))
                              ->from('pig_weights')
                              ->groupBy('pig_id');
                    })->avg('weight');
                    $totalRevenue = \App\Models\PigSale::sum('sale_price');
                    $avgSalePrice = \App\Models\PigSale::avg('sale_price');
                    $mortalityRate = $totalPigs > 0 ? ($totalDeaths / $totalPigs) * 100 : 0;
                    $salesThisMonth = \App\Models\PigSale::whereMonth('created_at', now()->month)->count();
                    
                    // Calculate farrowing rate
                    $pregNum = \App\Models\Mating::where('pregnancy_status', 'pregnant')->count();
                    $pregDenom = \App\Models\Mating::whereNotNull('female_id')->count();
                    $pregnancyRate = $pregDenom > 0 ? round($pregNum / $pregDenom * 100, 1) : 0; // Unified

                    $totalMatings = \App\Models\Mating::count();
                    $totalFarrowings = \App\Models\Farrowing::where('farrowing_date', '!=', null)->count();
                    $farrowingRate = $totalMatings > 0 ? round($totalFarrowings / $totalMatings * 100, 1) : 0;

    $totalWeaned = \App\Models\Weaning::count();
    $totalBorn = \App\Models\Farrowing::sum('live_births');
                    $weaningPercentage = $totalBorn > 0 ? round($totalWeaned / $totalBorn * 100, 1) : 0;
                    
                    $avgStillborns = \App\Models\Farrowing::avg('stillbirths');
                    $avgBirthWeight = \App\Models\Farrowing::whereNotNull('average_birth_weight')->avg('average_birth_weight');
                    
                    // Calculate total weight and farm value
                    $totalWeight = \App\Models\PigWeight::whereIn('id', function($query) {
                        $query->select(\DB::raw('MAX(id)'))
                              ->from('pig_weights')
                              ->groupBy('pig_id');
                    })->sum('weight');
                    
                    $liveWeightPrice = \App\Models\Setting::get('sales.pricing.live_weight_price', 30.00);
                    $totalFarmValue = $totalWeight * $liveWeightPrice;
                @endphp

                <!-- Breeding Stock -->
                <!-- <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-sm font-medium">Breeding Stock</p>
                            <p class="text-2xl font-bold text-gray-900">{{ number_format($breedingStock) }}</p>
                            <p class="text-gray-500 text-xs mt-1">Active breeding animals</p>
                        </div>
                        <div class="w-12 h-12 bg-pink-100 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6 text-pink-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                            </svg>
                        </div>
                    </div>
                </div> -->

                <!-- Average Weight -->
                <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-sm font-medium">Average Weight</p>
                            <p class="text-2xl font-bold text-gray-900">{{ $avgWeight ? number_format($avgWeight, 1) : '0' }} kg</p>
                            <p class="text-gray-500 text-xs mt-1">Latest weight per pig</p>
                        </div>
                        <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/>
                            </svg>
                        </div>
                    </div>
                </div>

                <!-- Total Revenue -->
                <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-sm font-medium">Total Revenue</p>
                            <p class="text-2xl font-bold text-gray-900">₵{{ number_format($totalRevenue, 2) }}</p>
                            <p class="text-gray-500 text-xs mt-1">From all sales</p>
                        </div>
                        <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"/>
                            </svg>
                        </div>
                    </div>
                </div>

                <!-- Mortality Rate -->
                <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-sm font-medium">Farrowing Rate</p>
                            <p class="text-2xl font-bold text-gray-900">{{ number_format($farrowingRate, 1) }}%</p>
                            <p class="text-gray-500 text-xs mt-1">Females that farrowed</p>
                        </div>
                        <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                            </svg>
                        </div>
                    </div>
                </div>

                <!-- Farrowing Rate -->
                <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-sm font-medium">Mortality Rate</p>
                            <p class="text-2xl font-bold text-gray-900">{{ number_format($mortalityRate, 1) }}%</p>
                            <p class="text-gray-500 text-xs mt-1">Death rate</p>
                        </div>
                        <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Weight and Value Metrics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-2 gap-6 mb-8">
                <!-- Total Weight -->
                <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-sm font-medium">Total Weight</p>
                            <p class="text-2xl font-bold text-gray-900">{{ number_format($totalWeight, 1) }} kg</p>
                            <p class="text-gray-500 text-xs mt-1">Latest weight of all pigs</p>
                        </div>
                        <div class="w-12 h-12 bg-indigo-100 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/>
                            </svg>
                        </div>
                    </div>
                </div>

                <!-- Total Farm Value -->
                <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-sm font-medium">Total Farm Value</p>
                            <p class="text-2xl font-bold text-gray-900">₵{{ number_format($totalFarmValue, 2) }}</p>
                            <p class="text-gray-500 text-xs mt-1">At ₵{{ number_format($liveWeightPrice, 2) }}/kg live weight</p>
                        </div>
                        <div class="w-12 h-12 bg-emerald-100 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"/>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Section -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                <!-- Weight Trends Chart -->
                <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-6 lg:col-span-2 order-3">
                    <div class="flex items-center justify-between mb-6">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">Monthly Weight Trends</h3>
                            <p class="text-sm text-gray-500">Average pig weight over time</p>
                        </div>
                        <div class="flex items-center space-x-2">
                            <div class="w-3 h-3 bg-gradient-to-r from-blue-400 to-blue-600 rounded-full"></div>
                            <span class="text-sm text-gray-600">Average Weight (kg)</span>
                        </div>
                    </div>
                    
                    <!-- Date Range Controls -->
                    <div class="mb-6 flex flex-wrap items-center gap-4">
                        <div class="flex items-center space-x-2">
                            <label class="text-sm font-medium text-gray-700">From:</label>
                            <input type="date" id="weightChartFromDate" class="border border-gray-300 rounded-lg px-3 py-1 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div class="flex items-center space-x-2">
                            <label class="text-sm font-medium text-gray-700">To:</label>
                            <input type="date" id="weightChartToDate" class="border border-gray-300 rounded-lg px-3 py-1 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <button id="updateWeightChart" class="bg-blue-600 text-white px-4 py-1 rounded-lg text-sm hover:bg-blue-700 transition">
                            Update Chart
                        </button>
                        <!-- <div class="flex items-center space-x-2">
                            <button class="quick-date-btn px-3 py-1 text-xs bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-all duration-200 border border-transparent hover:border-gray-300" data-days="7">7 Days</button>
                            <button class="quick-date-btn px-3 py-1 text-xs bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-all duration-200 border border-transparent hover:border-gray-300" data-days="30">30 Days</button>
                            <button class="quick-date-btn px-3 py-1 text-xs bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-all duration-200 border border-transparent hover:border-gray-300" data-days="90">90 Days</button>
                        </div> -->
                    </div>
                    
                    <div class="h-64 relative">
                        <canvas id="weightTrendsChart"></canvas>
                        <div id="chartLoading" class="absolute inset-0 bg-white bg-opacity-75 flex items-center justify-center hidden">
                            <div class="flex items-center space-x-2">
                                <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-600"></div>
                                <span class="text-gray-600">Loading chart data...</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Gender Distribution Chart (moved here) -->
                <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-6 order-1">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-lg font-semibold text-gray-900">Gender Distribution</h3>
                        <div class="flex items-center space-x-4">
                            <div class="flex items-center space-x-1">
                                <div class="w-3 h-3 bg-blue-500 rounded-full"></div>
                                <span class="text-sm text-gray-600">Male</span>
                            </div>
                            <div class="flex items-center space-x-1">
                                <div class="w-3 h-3 bg-pink-500 rounded-full"></div>
                                <span class="text-sm text-gray-600">Female</span>
                            </div>
                        </div>
                    </div>
                    <div class="h-64">
                        <canvas id="genderDistributionChart"></canvas>
                    </div>
                    <div class="mt-6 grid grid-cols-2 gap-4">
                        <div class="text-center p-4 bg-blue-50 rounded-lg border border-blue-200">
                            @php $maleCountGD = \App\Models\PigManagement::where('gender', 'M')->count(); @endphp
                            <div class="text-2xl font-bold text-blue-600">{{ $maleCountGD }}</div>
                            <div class="text-sm text-blue-700 font-medium">Males</div>
                            <div class="text-xs text-blue-600 mt-1">
                                {{ $totalPigs > 0 ? round(($maleCountGD / $totalPigs) * 100, 1) : 0 }}% of total
                            </div>
                        </div>
                        <div class="text-center p-4 bg-pink-50 rounded-lg border border-pink-200">
                            @php $femaleCountGD = \App\Models\PigManagement::where('gender', 'F')->count(); @endphp
                            <div class="text-2xl font-bold text-pink-600">{{ $femaleCountGD }}</div>
                            <div class="text-sm text-pink-700 font-medium">Females</div>
                            <div class="text-xs text-pink-600 mt-1">
                                {{ $totalPigs > 0 ? round(($femaleCountGD / $totalPigs) * 100, 1) : 0 }}% of total
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pig Distribution Chart -->
                <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-6 order-2">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-lg font-semibold text-gray-900">Pig Distribution</h3>
                        <div class="flex items-center space-x-4">
                            <div class="flex items-center space-x-1">
                                <div class="w-3 h-3 bg-green-500 rounded-full"></div>
                                <span class="text-sm text-gray-600">Active</span>
                            </div>
                            <div class="flex items-center space-x-1">
                                <div class="w-3 h-3 bg-red-500 rounded-full"></div>
                                <span class="text-sm text-gray-600">Deceased</span>
                            </div>
                            <div class="flex items-center space-x-1">
                                <div class="w-3 h-3 bg-blue-500 rounded-full"></div>
                                <span class="text-sm text-gray-600">Sold</span>
                            </div>
                        </div>
                    </div>
                    <div class="h-64">
                        <canvas id="pigDistributionChart"></canvas>
                    </div>
                    
                    <!-- Pig Distribution Stats -->
                    <div class="mt-6 grid grid-cols-3 gap-4">
                        <div class="text-center p-4 bg-green-50 rounded-lg border border-green-200">
                            <div class="text-2xl font-bold text-green-600">{{ $activePigs }}</div>
                            <div class="text-sm text-green-700 font-medium">Active Pigs</div>
                            <div class="text-xs text-green-600 mt-1">
                                {{ $totalPigs > 0 ? round(($activePigs / $totalPigs) * 100, 1) : 0 }}% of total
                            </div>
                        </div>
                        <div class="text-center p-4 bg-red-50 rounded-lg border border-red-200">
                            @php $deceasedPiglets = (int) \App\Models\PigletDeath::sum('count');
                            $deceasedTotal = $deceasedPigs + $deceasedPiglets;
                            @endphp
                            <div class="text-2xl font-bold text-red-600">{{ $deceasedTotal }}</div>
                            <div class="text-sm text-red-700 font-medium">Deceased</div>
                            <div class="text-xs text-red-600 mt-1">{{ number_format($deceasedPigs) }} pigs • {{ number_format($deceasedPiglets) }} piglets</div>
                            <div class="text-xs text-red-600 mt-1">
                                {{ $totalPigs > 0 ? round(($deceasedTotal / $totalPigs) * 100, 1) : 0 }}% of total
                            </div>
                        </div>
                        <div class="text-center p-4 bg-blue-50 rounded-lg border border-blue-200">
                            <div class="text-2xl font-bold text-blue-600">{{ $soldPigs }}</div>
                            <div class="text-sm text-blue-700 font-medium">Sold</div>
                            <div class="text-xs text-blue-600 mt-1">
                                {{ $totalPigs > 0 ? round(($soldPigs / $totalPigs) * 100, 1) : 0 }}% of total
                        </div>
                    </div>
                </div>
            </div>

                <!-- Weight Trends Chart moved below as full width is already set by col-span-2 above -->
            </div>

            <!-- Compact Dashboard Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <!-- Recent Users -->
                <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-4">
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-sm font-semibold text-gray-900">Recent Users</h3>
        <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center">
            <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"/>
            </svg>
        </div>
    </div>
    <div class="space-y-3">
        @php
        $recentUsers = \App\Models\User::with('role')
    // Filter: Exclude any user where role_id is equal to the superuser's ID
    ->where('role_id', '!=', 4) 
    ->latest()
    ->take(4)
    ->get();
            $onlineCutoff = now()->subMinutes(5); // Define 5 minutes as the "online" threshold
        @endphp
        @forelse($recentUsers as $user)
            <div class="flex items-center space-x-3">
                
                {{-- AVATAR WITH STATUS INDICATOR (New) --}}
                <div class="relative">
                    <div class="w-8 h-8 bg-gradient-to-br from-blue-500 to-blue-600 rounded-full flex items-center justify-center">
                        <span class="text-white font-medium text-xs">{{ substr($user->name, 0, 1) }}</span>
                    </div>
                    
                {{-- Status Dot Logic --}}
                @php
                    // Normalize last_seen and last_active_at to Carbon or null
                    $lastSeen = $user->last_seen;
                    if (is_string($lastSeen)) {
                        try { $lastSeen = \Illuminate\Support\Carbon::parse($lastSeen); } catch (\Throwable $e) { $lastSeen = null; }
                    }
                    $lastActiveAt = $user->last_active_at ?? null;
                    if (is_string($lastActiveAt)) {
                        try { $lastActiveAt = \Illuminate\Support\Carbon::parse($lastActiveAt); } catch (\Throwable $e) { $lastActiveAt = null; }
                    }
                    $isOnline = ($lastSeen instanceof \Illuminate\Support\Carbon) && $lastSeen->greaterThan($onlineCutoff);
                    $statusColor = $isOnline ? 'bg-green-500' : 'bg-gray-400';
                @endphp
                    
                    {{-- Status Dot --}}
                    <span class="absolute bottom-0 right-0 block h-2.5 w-2.5 rounded-full ring-2 ring-white {{ $statusColor }}"
                          title="{{ $isOnline ? 'Online' : 'Offline' }}">
                    </span>
                </div>
                
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-medium text-gray-900 truncate">{{ $user->name }}</p>
                    <p class="text-xs text-gray-500">
                        @if($isOnline)
                            Online
                        @elseif($lastActiveAt)
                            {{ $lastActiveAt->format('M j, Y g:i A') }}
                        @elseif($lastSeen)
                            {{ $lastSeen->format('M j, Y g:i A') }}
                        @else
                            Never active
                        @endif
                    </p>
                </div>
                
                {{-- Role Badge (Unchanged) --}}
                @php
                    $roleColors = [
                        'superuser' => 'bg-purple-100 text-purple-800',
                        'admin' => 'bg-red-100 text-red-800',
                        'farmmanager' => 'bg-blue-100 text-blue-800',
                        'dataentry' => 'bg-green-100 text-green-800',
                        'default' => 'bg-gray-100 text-gray-800'
                    ];
                    $userRole = $user->role ? $user->role->name : 'user';
                    $roleColor = $roleColors[$userRole] ?? $roleColors['default'];
                @endphp
                <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium {{ $roleColor }}">
                    {{ ucfirst($userRole) }}
                </span>
            </div>
        @empty
            <p class="text-gray-500 text-xs text-center py-2">No recent users</p>
        @endforelse
    </div>
</div>

                <!-- Weekly Activity -->
                <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-4">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-sm font-semibold text-gray-900">Weekly Activity</h3>
                        <div class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center">
                            <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                            </svg>
                        </div>
                    </div>
                    <div class="space-y-3">
                        @php
                            $pigsThisWeek = \App\Models\PigManagement::where('created_at', '>=', now()->subWeek())->count();
                            $weightsThisWeek = \App\Models\PigWeight::where('created_at', '>=', now()->subWeek())->count();
                            $salesThisWeek = \App\Models\PigSale::where('created_at', '>=', now()->subWeek())->count();
                            $deathsThisWeek = \App\Models\PigDeath::where('created_at', '>=', now()->subWeek())->count();
                        @endphp
                        <div class="flex items-center justify-between">
                            <span class="text-xs text-gray-600">New Pigs</span>
                            <span class="text-sm font-bold text-green-600">+{{ $pigsThisWeek }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-xs text-gray-600">Weight Records</span>
                            <span class="text-sm font-bold text-blue-600">+{{ $weightsThisWeek }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-xs text-gray-600">Sales</span>
                            <span class="text-sm font-bold text-purple-600">+{{ $salesThisWeek }}</span>
                        </div>
                            <div class="flex items-center justify-between">
                            <span class="text-xs text-gray-600">Deaths</span>
                            <span class="text-sm font-bold text-red-600">+{{ $deathsThisWeek }}</span>
                        </div>
                                </div>
                            </div>

                <!-- Quick Actions -->
                <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-4">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-sm font-semibold text-gray-900">Quick Actions</h3>
                        <div class="w-8 h-8 bg-purple-100 rounded-lg flex items-center justify-center">
                            <svg class="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                        </div>
                    </div>
                    <div class="space-y-2">
                        <a href="{{ route('all-stocks.index') }}" class="flex items-center p-2 bg-blue-50 rounded-lg hover:bg-blue-100 transition text-xs">
                            <svg class="w-3 h-3 text-blue-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/>
                            </svg>
                            Manage Pigs
                        </a>
                        <a href="{{ route('weight-alerts.index', ['acknowledged' => '0']) }}" class="flex items-center p-2 bg-red-50 rounded-lg hover:bg-red-100 transition text-xs">
                            <svg class="w-3 h-3 text-red-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                            </svg>
                            View Alerts
                        </a>
                        <a href="{{ route('deaths.index') }}" class="flex items-center p-2 bg-gray-50 rounded-lg hover:bg-gray-100 transition text-xs">
                            <svg class="w-3 h-3 text-gray-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                            </svg>
                            Death Records
                        </a>
                        <a href="{{ route('sales.index') }}" class="flex items-center p-2 bg-green-50 rounded-lg hover:bg-green-100 transition text-xs">
                            <svg class="w-3 h-3 text-green-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"/>
                            </svg>
                            Sales Records
                        </a>
                    </div>
                </div>

                <!-- Weighing Alerts Detail -->
                <button onclick="toggleWeighingAlertsModal()" class="w-full bg-white rounded-xl shadow-lg border border-gray-200 p-4 hover:shadow-xl transition-all duration-300 text-left">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-sm font-semibold text-gray-900">Weighing Schedule Alerts</h3>
                        <div class="w-8 h-8 bg-yellow-100 rounded-lg flex items-center justify-center">
                            <svg class="w-4 h-4 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/>
                            </svg>
                        </div>
                    </div>
                    <div class="space-y-2">
                        @php
                            $overduePigs = \App\Models\PigManagement::getOverdueForWeighing()->take(5);
                        @endphp
                        @forelse($overduePigs as $pig)
                            @php
                                $weighingStatus = $pig->getWeighingStatus();
                                $daysSince = $pig->getDaysSinceLastWeighing();
                            @endphp
                            <div class="flex items-center justify-between p-2 bg-{{ $weighingStatus['color'] }}-50 rounded-lg">
                                <div class="flex items-center">
                                    <span class="text-lg mr-2">{{ $weighingStatus['icon'] }}</span>
                                    <div>
                                        <p class="text-xs font-medium text-gray-900">{{ $pig->ear_tag_number }}</p>
                                        <p class="text-xs text-gray-600">{{ $pig->breed }} • {{ $pig->gender }}</p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="text-xs font-medium text-{{ $weighingStatus['color'] }}-700">{{ $weighingStatus['message'] }}</p>
                                    <p class="text-xs text-gray-500">{{ (int)$daysSince }} days</p>
                                </div>
                            </div>
                        @empty
                            <div class="text-center py-4">
                                <svg class="w-8 h-8 text-gray-400 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <p class="text-xs text-gray-500">All pigs are up to date with weighing</p>
                            </div>
                        @endforelse
                        @if($overduePigs->count() > 0)
                            <div class="text-center text-xs text-yellow-600 mt-2">
                                Click to view detailed alerts →
                            </div>
                        @endif
                    </div>
                </button>
            </div>
            <!-- Weight & Value Trends (Monthly/Annual) -->
            <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-6 mb-8 mt-8">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Weight & Value Trends</h3>
                        <p class="text-sm text-gray-500">Total weight and total farm value over time</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button id="wvMonthlyBtn" class="px-3 py-1 rounded-lg text-sm bg-blue-600 text-white">Monthly</button>
                        <button id="wvAnnualBtn" class="px-3 py-1 rounded-lg text-sm bg-gray-100 text-gray-700">Annual</button>
                    </div>
                </div>
                <div class="h-80">
                    <canvas id="weightValueTrends"></canvas>
                </div>
            </div>
        </div>
        <x-copyright-minimal />
    </div>

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Chart data from PHP
        @php
            // Weight trends data (last 30 days) - average of latest weight per pig for each day
            $weightTrendsData = \App\Models\PigWeight::selectRaw('DATE(created_at) as date, AVG(weight) as avg_weight')
                ->whereIn('id', function($query) {
                    $query->select(\DB::raw('MAX(id)'))
                          ->from('pig_weights')
                          ->where('created_at', '>=', now()->subDays(30))
                          ->groupBy('pig_id');
                })
                ->where('created_at', '>=', now()->subDays(30))
                ->groupBy('date')
                ->orderBy('date')
                ->get();
            
            // Generate labels for last 30 days
            $weightLabels = [];
            $weightValues = [];
            
            if ($weightTrendsData->count() > 0) {
                $weightLabels = $weightTrendsData->pluck('date')->map(function($date) {
                    return \Carbon\Carbon::parse($date)->format('M j');
                })->toArray();
                $weightValues = $weightTrendsData->pluck('avg_weight')->toArray();
            } else {
                // Fallback: generate sample data for last 30 days to match default date range
                for ($i = 29; $i >= 0; $i--) {
                    $weightLabels[] = now()->subDays($i)->format('M j');
                    $weightValues[] = rand(20, 80); // Sample weights
                }
            }

            // Pig distribution data (variables already defined above)
            
            // Ensure we have some data for the chart
            if ($activePigs == 0 && $deceasedPigs == 0 && $soldPigs == 0) {
                $activePigs = 1; // Fallback to show something
            }

            // User activity data (last 7 days)
            $userActivityData = [];
            $userActivityLabels = [];
            for ($i = 6; $i >= 0; $i--) {
                $date = now()->subDays($i)->format('Y-m-d');
                $count = \App\Models\User::whereDate('created_at', $date)->count();
                $userActivityData[] = $count;
                $userActivityLabels[] = now()->subDays($i)->format('M j');
            }

            // Weight & Value trends (monthly last 12 months, annual last 5 years)
            $chartLiveWeightPrice = \App\Models\Setting::get('sales.pricing.live_weight_price', 30.00);
            $wvMonthlyWeight = [];
            $wvMonthlyValue = [];
            $wvMonthlyLabels = [];
            for ($i = 11; $i >= 0; $i--) {
                $monthDate = now()->subMonths($i);
                $monthEnd = $monthDate->copy()->endOfMonth();
                $monthWeight = \App\Models\PigWeight::whereIn('id', function($q) use ($monthEnd) {
                    $q->select(\DB::raw('MAX(id)'))
                      ->from('pig_weights')
                      ->where('created_at', '<=', $monthEnd)
                      ->groupBy('pig_id');
                })->sum('weight');
                $wvMonthlyWeight[] = round($monthWeight, 1);
                $wvMonthlyValue[] = round($monthWeight * $chartLiveWeightPrice, 2);
                $wvMonthlyLabels[] = $monthDate->format('M Y');
            }

            $wvAnnualWeight = [];
            $wvAnnualValue = [];
            $wvAnnualLabels = [];
            for ($i = 4; $i >= 0; $i--) {
                $year = now()->subYears($i)->year;
                $yearEnd = \Carbon\Carbon::create($year, 12, 31)->endOfYear();
                $yearWeight = \App\Models\PigWeight::whereIn('id', function($q) use ($yearEnd) {
                    $q->select(\DB::raw('MAX(id)'))
                      ->from('pig_weights')
                      ->where('created_at', '<=', $yearEnd)
                      ->groupBy('pig_id');
                })->sum('weight');
                $wvAnnualWeight[] = round($yearWeight, 1);
                $wvAnnualValue[] = round($yearWeight * $chartLiveWeightPrice, 2);
                $wvAnnualLabels[] = (string) $year;
            }
        @endphp

        // Weight Trends Chart - Modern Bar Chart with Gradient
        const weightTrendsCtx = document.getElementById('weightTrendsChart').getContext('2d');
        
        // Create gradient for the bars
        const barGradient = weightTrendsCtx.createLinearGradient(0, 0, 0, 300);
        barGradient.addColorStop(0, 'rgba(59, 130, 246, 0.8)');
        barGradient.addColorStop(0.5, 'rgba(59, 130, 246, 0.6)');
        barGradient.addColorStop(1, 'rgba(59, 130, 246, 0.4)');
        
        let weightChart = new Chart(weightTrendsCtx, {
            type: 'bar',
            data: {
                labels: @json($weightLabels),
                datasets: [{
                    label: 'Average Weight (kg)',
                    data: @json($weightValues),
                    backgroundColor: barGradient,
                    borderColor: 'rgb(59, 130, 246)',
                    borderWidth: 2,
                    borderRadius: 8,
                    borderSkipped: false,
                    hoverBackgroundColor: 'rgba(59, 130, 246, 0.9)',
                    hoverBorderColor: 'rgb(59, 130, 246)',
                    hoverBorderWidth: 3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    intersect: false,
                    mode: 'index'
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        borderColor: 'rgb(59, 130, 246)',
                        borderWidth: 1,
                        cornerRadius: 8,
                        displayColors: false,
                        callbacks: {
                            title: function(context) {
                                return 'Weight Trend - ' + context[0].label;
                            },
                            label: function(context) {
                                return 'Average Weight: ' + context.parsed.y.toFixed(1) + ' kg';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)',
                            drawBorder: false
                        },
                        ticks: {
                            color: '#6B7280',
                            font: {
                                size: 12
                            },
                            callback: function(value) {
                                return value + ' kg';
                            }
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            color: '#6B7280',
                            font: {
                                size: 11
                            },
                            maxRotation: 45,
                            minRotation: 0
                        }
                    }
                },
                animation: {
                    duration: 2000,
                    easing: 'easeInOutQuart'
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        borderColor: 'rgb(59, 130, 246)',
                        borderWidth: 1,
                        cornerRadius: 8,
                        displayColors: false,
                        callbacks: {
                            title: function(context) {
                                return 'Weight Trend - ' + context[0].label;
                            },
                            label: function(context) {
                                return 'Average Weight: ' + context.parsed.y.toFixed(1) + ' kg';
                            }
                        }
                    }
                }
            }
        });

        // Initialize date inputs with default values (last 30 days)
        const fromDateInput = document.getElementById('weightChartFromDate');
        const toDateInput = document.getElementById('weightChartToDate');
        const updateButton = document.getElementById('updateWeightChart');
        
        // Set default dates (last 30 days)
        const today = new Date();
        const thirtyDaysAgo = new Date(today);
        thirtyDaysAgo.setDate(today.getDate() - 30);
        
        fromDateInput.value = thirtyDaysAgo.toISOString().split('T')[0];
        toDateInput.value = today.toISOString().split('T')[0];
        
        // Highlight the default 30-day button
        highlightQuickDateButton(30);

        // Function to highlight the appropriate quick date button
        function highlightQuickDateButton(days) {
            document.querySelectorAll('.quick-date-btn').forEach(btn => {
                btn.classList.remove('bg-blue-100', 'text-blue-700', 'border-blue-300');
                btn.classList.add('bg-gray-100', 'text-gray-700');
                
                if (parseInt(btn.dataset.days) === days) {
                    btn.classList.remove('bg-gray-100', 'text-gray-700');
                    btn.classList.add('bg-blue-100', 'text-blue-700', 'border-blue-300');
                }
            });
        }

        // Function to detect which quick date button should be highlighted based on current date range
        function detectAndHighlightQuickDate() {
            const fromDate = new Date(fromDateInput.value);
            const toDate = new Date(toDateInput.value);
            const today = new Date();
            
            // Check if the date range matches any of the quick date options
            const daysDiff = Math.ceil((toDate - fromDate) / (1000 * 60 * 60 * 24));
            
            // Check if it's exactly 7, 30, or 90 days and ends today
            if (daysDiff === 7 && toDate.toDateString() === today.toDateString()) {
                highlightQuickDateButton(7);
            } else if (daysDiff === 30 && toDate.toDateString() === today.toDateString()) {
                highlightQuickDateButton(30);
            } else if (daysDiff === 90 && toDate.toDateString() === today.toDateString()) {
                highlightQuickDateButton(90);
            } else {
                // Clear all highlights if it doesn't match any quick date
                document.querySelectorAll('.quick-date-btn').forEach(btn => {
                    btn.classList.remove('bg-blue-100', 'text-blue-700', 'border-blue-300');
                    btn.classList.add('bg-gray-100', 'text-gray-700');
                });
            }
        }

        // Function to update chart with new date range
        async function updateWeightChart() {
            const fromDate = fromDateInput.value;
            const toDate = toDateInput.value;
            
            if (!fromDate || !toDate) {
                alert('Please select both from and to dates');
                return;
            }
            
            if (new Date(fromDate) > new Date(toDate)) {
                alert('From date cannot be after to date');
                return;
            }
            
            // Show loading state
            updateButton.textContent = 'Loading...';
            updateButton.disabled = true;
            document.getElementById('chartLoading').classList.remove('hidden');
            
            try {
                // Fetch new data from server
                console.log('Fetching data for date range:', fromDate, 'to', toDate);
                const response = await fetch(`/admin/weight-trends-data?from=${fromDate}&to=${toDate}`);
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const data = await response.json();
                console.log('Received data:', data);
                
                // Update chart
                weightChart.data.labels = data.labels;
                weightChart.data.datasets[0].data = data.values;
                weightChart.update('active');
                
                console.log('Chart updated successfully');
                
            } catch (error) {
                console.error('Error fetching chart data:', error);
                alert('Error loading chart data: ' + error.message);
            } finally {
                updateButton.textContent = 'Update Chart';
                updateButton.disabled = false;
                document.getElementById('chartLoading').classList.add('hidden');
            }
        }

        // Event listeners
        updateButton.addEventListener('click', updateWeightChart);
        
        // Auto-update chart when date inputs change
        fromDateInput.addEventListener('change', function() {
            detectAndHighlightQuickDate();
            updateWeightChart();
        });
        toDateInput.addEventListener('change', function() {
            detectAndHighlightQuickDate();
            updateWeightChart();
        });
        
        // Quick date buttons with enhanced functionality
        document.querySelectorAll('.quick-date-btn').forEach(button => {
            button.addEventListener('click', function() {
                // Prevent multiple rapid clicks
                if (this.disabled) return;
                
                const days = parseInt(this.dataset.days);
                const toDate = new Date();
                const fromDate = new Date();
                fromDate.setDate(toDate.getDate() - days);
                
                // Update date inputs
                fromDateInput.value = fromDate.toISOString().split('T')[0];
                toDateInput.value = toDate.toISOString().split('T')[0];
                
                // Highlight the selected button
                highlightQuickDateButton(days);
                
                // Update chart immediately
                updateWeightChart();
            });
        });

        // Pig Distribution Chart
        const pigDistributionCtx = document.getElementById('pigDistributionChart').getContext('2d');
        new Chart(pigDistributionCtx, {
            type: 'doughnut',
            data: {
                labels: ['Active', 'Deceased', 'Sold'],
                datasets: [{
                    data: [{{ $activePigs }}, {{ $deceasedPigs }}, {{ $soldPigs }}],
                    backgroundColor: [
                        'rgb(34, 197, 94)',
                        'rgb(239, 68, 68)',
                        'rgb(59, 130, 246)'
                    ],
                    borderWidth: 0,
                    cutout: '60%'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });

        // Gender Distribution Chart
        (function initGender(){
            const canvas = document.getElementById('genderDistributionChart');
            if (!canvas) return;
            const ctx = canvas.getContext('2d');
            const male = {{ \App\Models\PigManagement::where('gender','M')->count() }};
            const female = {{ \App\Models\PigManagement::where('gender','F')->count() }};
            new Chart(ctx, {
                type: 'doughnut',
            data: {
                    labels: ['Male','Female'],
                datasets: [{
                        data: [male, female],
                        backgroundColor: ['rgb(59, 130, 246)','rgb(236, 72, 153)'],
                        borderWidth: 0,
                        cutout: '60%'
                    }]
                },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
            });
        })();

        // Weight & Value Trends Chart (ensure DOM ready)
        document.addEventListener('DOMContentLoaded', function initWV(){
            const el = document.getElementById('weightValueTrends');
            if (!el) { return; }
            const ctx = el.getContext('2d');
            const wGrad = ctx.createLinearGradient(0,0,0,300);
            wGrad.addColorStop(0,'rgba(59,130,246,0.8)');
            wGrad.addColorStop(1,'rgba(59,130,246,0.15)');
            const vGrad = ctx.createLinearGradient(0,0,0,300);
            vGrad.addColorStop(0,'rgba(16,185,129,0.8)');
            vGrad.addColorStop(1,'rgba(16,185,129,0.15)');

            // Debug: ensure Chart and data present
            try {
            console.log('WV Chart.js present?', typeof Chart !== 'undefined');
            console.log('WV monthly labels', @json($wvMonthlyLabels ?? []));
            console.log('WV monthly weight', @json($wvMonthlyWeight ?? []));
            console.log('WV monthly value', @json($wvMonthlyValue ?? []));
            } catch (e) {}

            let chart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: @json($wvMonthlyLabels ?? []),
                    datasets: [
                        { label: 'Total Weight (kg)', data: @json($wvMonthlyWeight ?? []), borderColor: 'rgb(59,130,246)', backgroundColor: wGrad, borderWidth: 2, fill: true, tension: 0.35, yAxisID: 'y' },
                        { label: 'Farm Value (₵)', data: @json($wvMonthlyValue ?? []), borderColor: 'rgb(16,185,129)', backgroundColor: vGrad, borderWidth: 2, fill: true, tension: 0.35, yAxisID: 'y1' }
                    ]
                },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                    interaction: { intersect: false, mode: 'index' },
                    plugins: { legend: { display: false } },
                scales: {
                        y: { beginAtZero: true, ticks: { callback: v => v + ' kg' } },
                        y1: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false }, ticks: { callback: v => '₵' + Number(v).toLocaleString('en-US') } }
                }
            }
        });

            const mBtn = document.getElementById('wvMonthlyBtn');
            const aBtn = document.getElementById('wvAnnualBtn');
            if (mBtn) mBtn.addEventListener('click', () => {
                mBtn.classList.remove('bg-gray-100','text-gray-700');
                mBtn.classList.add('bg-blue-600','text-white');
                aBtn.classList.remove('bg-blue-600','text-white');
                aBtn.classList.add('bg-gray-100','text-gray-700');
                chart.data.labels = @json($wvMonthlyLabels ?? []);
                chart.data.datasets[0].data = @json($wvMonthlyWeight ?? []);
                chart.data.datasets[1].data = @json($wvMonthlyValue ?? []);
                chart.update('active');
            });
            if (aBtn) aBtn.addEventListener('click', () => {
                aBtn.classList.remove('bg-gray-100','text-gray-700');
                aBtn.classList.add('bg-blue-600','text-white');
                mBtn.classList.remove('bg-blue-600','text-white');
                mBtn.classList.add('bg-gray-100','text-gray-700');
                chart.data.labels = @json($wvAnnualLabels ?? []);
                chart.data.datasets[0].data = @json($wvAnnualWeight ?? []);
                chart.data.datasets[1].data = @json($wvAnnualValue ?? []);
                chart.update('active');
            });
        });

    </script>

    <!-- Weighing Alerts Modal -->
    <div id="weighingAlertsModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-hidden">
            <div class="flex items-center justify-between p-6 border-b border-gray-200">
                <div>
                    <h2 class="text-xl font-bold text-gray-900">Weighing Alerts Details</h2>
                    <p class="text-sm text-gray-600 mt-1">Pigs overdue for weighing with detailed information</p>
                </div>
                <button onclick="toggleWeighingAlertsModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            
            <div class="p-6 overflow-y-auto max-h-[calc(90vh-120px)]">
                @php
                    $allOverduePigs = \App\Models\PigManagement::getOverdueForWeighing();
                @endphp
                
                @if($allOverduePigs->count() > 0)
                    <div class="space-y-4">
                        @foreach($allOverduePigs as $pig)
                            @php
                                $weighingStatus = $pig->getWeighingStatus();
                                $daysSince = $pig->getDaysSinceLastWeighing();
                                $lastWeighing = $pig->getLastWeighingDate();
                                $lastWeight = $pig->weights()->latest('weigh_date')->first();
                                $overdueDays = $daysSince - 30; // 30 is the weighing interval
                            @endphp
                            <div class="bg-{{ $weighingStatus['color'] }}-50 border border-{{ $weighingStatus['color'] }}-200 rounded-lg p-4">
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <div class="flex items-center mb-2">
                                            <span class="text-2xl mr-3">{{ $weighingStatus['icon'] }}</span>
                                            <div>
                                                <h3 class="text-lg font-semibold text-gray-900">{{ $pig->ear_tag_number }}</h3>
                                                <p class="text-sm text-gray-600">{{ $pig->breed }} • {{ $pig->gender }} • {{ $pig->stock_type }}</p>
                                            </div>
                                        </div>
                                        
                                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-3">
                                            <div class="bg-white rounded-lg p-3 border border-gray-200">
                                                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Last Weighing</p>
                                                <p class="text-sm font-semibold text-gray-900">
                                                    @if($lastWeighing)
                                                        {{ $lastWeighing->format('M j, Y') }}
                                                    @else
                                                        Never weighed
                                                    @endif
                                                </p>
                                            </div>
                                            
                                            <div class="bg-white rounded-lg p-3 border border-gray-200">
                                                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Last Weight</p>
                                                <p class="text-sm font-semibold text-gray-900">
                                                    @if($lastWeight)
                                                        {{ number_format($lastWeight->weight, 1) }} {{ $lastWeight->weight_unit }}
                                                    @else
                                                        No weight recorded
                                                    @endif
                                                </p>
                                            </div>
                                            
                                            <div class="bg-white rounded-lg p-3 border border-gray-200">
                                                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Overdue By</p>
                                                <p class="text-sm font-semibold text-{{ $weighingStatus['color'] }}-700">
                                                    {{ max(0, (int)$overdueDays) }} days
                                                </p>
                                            </div>
                                        </div>
                                        
                                        <div class="mt-3 flex items-center justify-between">
                                            <div class="flex items-center">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-{{ $weighingStatus['color'] }}-100 text-{{ $weighingStatus['color'] }}-800">
                                                    {{ $weighingStatus['message'] }}
                                                </span>
                                                <span class="ml-2 text-xs text-gray-500">
                                                    {{ (int)$daysSince }} days since last weighing
                                                </span>
                                            </div>
                                            
                                            <a href="{{ route('weights.create', ['pig_id' => $pig->id]) }}" 
                                               class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md text-white bg-{{ $weighingStatus['color'] }}-600 hover:bg-{{ $weighingStatus['color'] }}-700 transition-colors">
                                                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/>
                                                </svg>
                                                Record Weight
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-12">
                        <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">All caught up!</h3>
                        <p class="text-gray-500">All pigs are up to date with their weighing schedule.</p>
                    </div>
                @endif
            </div>
            
            <div class="flex items-center justify-between p-6 border-t border-gray-200 bg-gray-50">
                <div class="text-sm text-gray-600">
                    <span class="font-medium">{{ $allOverduePigs->count() }}</span> pigs need weighing attention
                </div>
                <div class="flex space-x-3">
                    <a href="{{ route('weights.index') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                        View All Weights
                    </a>
                    <button onclick="toggleWeighingAlertsModal()" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-gray-600 hover:bg-gray-700 transition-colors">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleWeighingAlertsModal() {
            const modal = document.getElementById('weighingAlertsModal');
            if (!modal) return;
            modal.classList.toggle('hidden');
        }

        // Close modal when clicking outside
        document.getElementById('weighingAlertsModal').addEventListener('click', function(e) {
            if (e.target === this) {
                toggleWeighingAlertsModal();
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const modal = document.getElementById('weighingAlertsModal');
                if (modal && !modal.classList.contains('hidden')) {
                    toggleWeighingAlertsModal();
                }
            }
        });

        // Open weighing alerts modal automatically when coming from notification link
        document.addEventListener('DOMContentLoaded', function () {
            if (window.location.hash === '#weighing-alerts-card') {
                // Scroll the card into view
                const card = document.getElementById('weighing-alerts-card');
                if (card) {
                    card.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
                // Open the modal
                toggleWeighingAlertsModal();
            }
        });
    </script>
</x-app-layout>