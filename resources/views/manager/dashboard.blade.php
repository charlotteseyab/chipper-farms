<x-app-layout>
    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Header -->
            <!-- <div class="mb-6">
                <h1 class="text-3xl font-bold text-gray-900">Manager Dashboard</h1>
                <p class="text-gray-600 mt-2">Farm operations overview and management tools</p>
            </div> -->


            <!-- Key Metrics -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 mb-8">
                <!-- Total Stock -->
                <a href="{{ route('all-stocks.index') }}" class="block bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:shadow-md transition">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">Total Stock</p>
                            <p class="text-2xl font-bold text-gray-900">
                                @php
                                    // Enhanced pig counting including piglets (weaned = active pigs only)
                                    $weanedPigs = \App\Models\PigManagement::active()->count();
                                    $weaningAge = config('breeding.cycle.birth_to_weaning_days', 45);
                                    $recentFarrowings = \App\Models\Farrowing::with('pigletDeaths')
                                        ->where('farrowing_date', '>=', now()->subDays($weaningAge))
                                        ->where('status', 'active')
                                        ->get();
                                    $pigletsCount = 0;
                                    foreach ($recentFarrowings as $f) {
                                        $pigletsCount += max(0, (int)($f->live_births ?? 0) - (int)$f->pigletDeaths->sum('count'));
                                    }
                                    $totalStock = $weanedPigs + $pigletsCount;
                                @endphp
                                {{ $totalStock }}
                            </p>
                        </div>
                        <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                            </svg>
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="flex items-center text-sm text-gray-600">
                            @php
                                $breedingStock = \App\Models\PigManagement::active()->where('stock_type', 'breeding')->count();
                                $standardStock = \App\Models\PigManagement::active()->where('stock_type', 'standard')->count();
                            @endphp
                            <span class="text-green-600 font-medium">{{ $weanedPigs }}</span>
                            <span class="ml-1">weaned (</span>
                            <span class="text-green-600 font-medium">{{ $breedingStock }}</span>
                            <span class="ml-1">breeding, </span>
                            <span class="text-blue-600 font-medium ml-1">{{ $standardStock }}</span>
                            <span class="ml-1">standard), </span>
                            <span class="text-orange-600 font-medium ml-1">{{ $pigletsCount }}</span>
                            <span class="ml-1">piglets</span>
                        </div>
                    </div>
                </a>

                <!-- Monthly Revenue -->
                <!-- <a href="{{ route('all-stocks.index', ['created_from' => now()->startOfMonth()->toDateString(), 'created_to' => now()->toDateString()]) }}" class="block bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:shadow-md transition">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">Monthly Activity</p>
                            <p class="text-2xl font-bold text-gray-900">
                                @php
                                    // Calculate actual monthly activity - new pigs added this month
                                    $monthlyNewPigs = \App\Models\PigManagement::whereMonth('created_at', now()->month)
                                        ->whereYear('created_at', now()->year)->count();

                                    // Calculate monthly weight records as activity indicator
                                    $monthlyWeightRecords = \App\Models\PigWeight::whereMonth('created_at', now()->month)
                                        ->whereYear('created_at', now()->year)->count();

                                    // Total monthly activity score
                                    $monthlyActivity = $monthlyNewPigs + $monthlyWeightRecords;
                                @endphp
                                {{ $monthlyActivity }}
                            </p>
                        </div>
                        <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"/>
                            </svg>
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="flex items-center text-sm text-green-600">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 11l5-5m0 0l5 5m-5-5v12"/>
                            </svg>
                            <span>{{ $monthlyNewPigs }} new pigs, {{ $monthlyWeightRecords }} weights</span>
                        </div>
                    </div>
                </a> -->

                <!-- Active Breeding -->
                <a href="{{ route('breeding-stocks.index') }}" class="block bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:shadow-md transition">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">Active Breeding</p>
                            <p class="text-2xl font-bold text-gray-900">
                                @php
                                    $activeBreeding = \App\Models\PigManagement::where('stock_type', 'breeding')->count();
                                @endphp
                                {{ $activeBreeding }}
                            </p>
                        </div>
                        <div class="w-12 h-12 bg-pink-100 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6 text-pink-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                            </svg>
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="flex items-center text-sm text-gray-600">
                            @php
                                $breedingFemales = \App\Models\PigManagement::where('stock_type', 'breeding')->where('gender', 'F')->count();
                                $breedingMales = \App\Models\PigManagement::where('stock_type', 'breeding')->where('gender', 'M')->count();
                            @endphp
                            <span class="text-pink-600 font-medium">{{ $breedingFemales }}</span>
                            <span class="ml-1">females, </span>
                            <span class="text-purple-600 font-medium ml-1">{{ $breedingMales }}</span>
                            <span class="ml-1">males</span>
                        </div>
                    </div>
                </a>

                <!-- Critical Alerts -->
                <a href="{{ route('weight-alerts.index', ['severity' => 'high', 'acknowledged' => '0']) }}" class="block bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:shadow-md transition">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">Critical Alerts</p>
                            <p class="text-2xl font-bold text-gray-900">
                                @php
                                    $criticalAlerts = \App\Models\WeightAlert::where('severity', 'high')
                                        ->where('acknowledged', false)->count();
                                @endphp
                                {{ $criticalAlerts }}
                            </p>
                        </div>
                        <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                            </svg>
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="flex items-center text-sm text-red-600">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <span>Require immediate attention</span>
                        </div>
                    </div>
                </a>
            </div>

            <!-- Additional Key Metrics -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 mb-8">
                <!-- Mortality Rate -->
                <a href="{{ route('deaths.index') }}" class="block bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:shadow-md transition">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">Mortality Rate</p>
                            <p class="text-2xl font-bold text-gray-900">
                                @php
                                    // Use enhanced pig counting for mortality rate calculation
                                    $weanedPigs = \App\Models\PigManagement::count();
                                    $weaningAge = config('breeding.cycle.birth_to_weaning_days', 45);
                                    $pigletsCount = \App\Models\Farrowing::where('farrowing_date', '>=', now()->subDays($weaningAge))
                                        ->where('status', 'active')
                                        ->sum('live_births');
                                    $totalPigs = $weanedPigs + $pigletsCount;
                                    $totalDeaths = \App\Models\PigDeath::count();
                                    $mortalityRate = $totalPigs > 0 ? ($totalDeaths / $totalPigs) * 100 : 0;
                                @endphp
                                {{ number_format($mortalityRate, 1) }}%
                            </p>
                        </div>
                        <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                            </svg>
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="flex items-center text-sm text-red-600">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <span>{{ $totalDeaths }} total deaths</span>
                        </div>
                    </div>
                </a>

                <!-- Fertility Rate -->
                <a href="{{ route('mating.index') }}" class="block bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:shadow-md transition">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">Fertility Rate</p>
                            <p class="text-2xl font-bold text-gray-900">
                                @php
                                    $totalMatings = \App\Models\Mating::count();
                                    $successfulMatings = \App\Models\Mating::where('pregnancy_status', 'pregnant')->count();
                                    $fertilityRate = $totalMatings > 0 ? ($successfulMatings / $totalMatings) * 100 : 0;
                                @endphp
                                {{ number_format($fertilityRate, 1) }}%
                            </p>
                        </div>
                        <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                            </svg>
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="flex items-center text-sm text-purple-600">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                            </svg>
                            <span>{{ $successfulMatings }}/{{ $totalMatings }} successful</span>
                        </div>
                    </div>
                </a>

                <!-- Weighing Alerts -->
                <a href="{{ route('weights.index') }}" class="block bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:shadow-md transition">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">Weighing Alerts</p>
                            <p class="text-2xl font-bold text-gray-900">
                                @php
                                    $overdueForWeighing = \App\Models\PigManagement::countOverdueForWeighing();
                                @endphp
                                {{ $overdueForWeighing }}
                            </p>
                        </div>
                        <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/>
                            </svg>
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="flex items-center text-sm text-yellow-600">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <span>Overdue for weighing</span>
                        </div>
                    </div>
                </a>
            </div>

            <!-- Charts and Analytics Section -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                <!-- Stock Distribution Pie Chart -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900">Stock Distribution</h3>
                        <a href="{{ route('all-stocks.index') }}" class="text-sm text-blue-600 hover:text-blue-800">View All</a>
                    </div>
                    <div class="relative h-64">
                        <canvas id="stockDistributionChart"></canvas>
                    </div>
                    <div class="mt-4 grid grid-cols-2 gap-4 text-sm">
                        @php
                            // Use enhanced pig counting for stock distribution
                            $weanedPigs = \App\Models\PigManagement::count();
                            $weaningAge = config('breeding.cycle.birth_to_weaning_days', 45);
                            $pigletsCount = \App\Models\Farrowing::where('farrowing_date', '>=', now()->subDays($weaningAge))
                                ->where('status', 'active')
                                ->sum('live_births');
                            $totalStock = $weanedPigs + $pigletsCount;
                            $breedingStock = \App\Models\PigManagement::where('stock_type', 'breeding')->count();
                            $standardStock = \App\Models\PigManagement::where('stock_type', 'standard')->count();
                        @endphp
                        <div class="flex items-center">
                            <div class="w-3 h-3 bg-green-500 rounded-full mr-2"></div>
                            <span class="text-gray-600">Breeding: </span>
                            <span class="font-semibold text-gray-900 ml-1">{{ $breedingStock }}</span>
                        </div>
                        <div class="flex items-center">
                            <div class="w-3 h-3 bg-blue-500 rounded-full mr-2"></div>
                            <span class="text-gray-600">Standard: </span>
                            <span class="font-semibold text-gray-900 ml-1">{{ $standardStock }}</span>
                        </div>
                    </div>
                </div>

                <!-- Weight Alerts Bar Chart -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900">Weight Alerts by Severity</h3>
                        <a href="{{ route('weight-alerts.index') }}" class="text-sm text-blue-600 hover:text-blue-800">View All</a>
                    </div>
                    <div class="relative h-64">
                        <canvas id="weightAlertsChart"></canvas>
                    </div>
                    <div class="mt-4 grid grid-cols-3 gap-2 text-xs">
                        @php
                            $highAlerts = \App\Models\WeightAlert::where('severity', 'high')->where('acknowledged', false)->count();
                            $mediumAlerts = \App\Models\WeightAlert::where('severity', 'medium')->where('acknowledged', false)->count();
                            $lowAlerts = \App\Models\WeightAlert::where('severity', 'low')->where('acknowledged', false)->count();
                        @endphp
                        <div class="text-center">
                            <div class="w-3 h-3 bg-red-500 rounded-full mx-auto mb-1"></div>
                            <div class="font-semibold text-gray-900">{{ $highAlerts }}</div>
                            <div class="text-gray-600">High</div>
                        </div>
                        <div class="text-center">
                            <div class="w-3 h-3 bg-yellow-500 rounded-full mx-auto mb-1"></div>
                            <div class="font-semibold text-gray-900">{{ $mediumAlerts }}</div>
                            <div class="text-gray-600">Medium</div>
                        </div>
                        <div class="text-center">
                            <div class="w-3 h-3 bg-green-500 rounded-full mx-auto mb-1"></div>
                            <div class="font-semibold text-gray-900">{{ $lowAlerts }}</div>
                            <div class="text-gray-600">Low</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Additional Charts Section -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                <!-- Weight Trends Line Chart -->
                <!-- <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900">Weight Records Trend</h3>
                        <a href="{{ route('weights.index') }}" class="text-sm text-blue-600 hover:text-blue-800">View All</a>
                    </div>
                    <div class="relative h-64">
                        <canvas id="weightTrendsChart"></canvas>
                    </div>
                    <div class="mt-4 text-sm text-gray-600">
                        @php
                            $totalWeightRecords = \App\Models\PigWeight::count();
                            $recentWeightRecords = \App\Models\PigWeight::where('created_at', '>=', now()->subDays(30))->count();
                        @endphp
                        <div class="flex justify-between">
                            <span>Total Records: <span class="font-semibold text-gray-900">{{ $totalWeightRecords }}</span></span>
                            <span>Last 30 days: <span class="font-semibold text-gray-900">{{ $recentWeightRecords }}</span></span>
                        </div>
                    </div>
                </div> -->

                <!-- Breed Distribution Doughnut Chart -->
                <!-- <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900">Breed Distribution</h3>
                        <a href="{{ route('all-stocks.index') }}" class="text-sm text-blue-600 hover:text-blue-800">View All</a>
                    </div>
                    <div class="relative h-64">
                        <canvas id="breedDistributionChart"></canvas>
                    </div>
                    <div class="mt-4 grid grid-cols-3 gap-2 text-xs">
                        @php
                            $lwCount = \App\Models\PigManagement::where('breed', 'LW')->count();
                            $drCount = \App\Models\PigManagement::where('breed', 'DR')->count();
                            $lwdrCount = \App\Models\PigManagement::where('breed', 'LWDR')->count();
                        @endphp
                        <div class="text-center">
                            <div class="w-3 h-3 bg-blue-500 rounded-full mx-auto mb-1"></div>
                            <div class="font-semibold text-gray-900">{{ $lwCount }}</div>
                            <div class="text-gray-600">Large White</div>
                        </div>
                        <div class="text-center">
                            <div class="w-3 h-3 bg-green-500 rounded-full mx-auto mb-1"></div>
                            <div class="font-semibold text-gray-900">{{ $drCount }}</div>
                            <div class="text-gray-600">Duroc</div>
                        </div>
                        <div class="text-center">
                            <div class="w-3 h-3 bg-purple-500 rounded-full mx-auto mb-1"></div>
                            <div class="font-semibold text-gray-900">{{ $lwdrCount }}</div>
                            <div class="text-gray-600">LWDR</div>
                        </div>
                    </div>
                </div> -->
            </div>

            <!-- Quick Actions -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <a href="{{ route('all-stocks.index') }}" class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:shadow-md transition group">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center group-hover:bg-blue-200 transition">
                            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-lg font-semibold text-gray-900 group-hover:text-blue-600 transition">Manage Stock</h3>
                            <p class="text-sm text-gray-600">View and manage all pigs</p>
                        </div>
                    </div>
                </a>

                <a href="{{ route('weight-alerts.dashboard') }}" class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:shadow-md transition group">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center group-hover:bg-red-200 transition">
                            <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-lg font-semibold text-gray-900 group-hover:text-red-600 transition">Weight Alerts</h3>
                            <p class="text-sm text-gray-600">Monitor weight issues</p>
                        </div>
                    </div>
                </a>

                <a href="{{ route('gestation.index') }}" class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:shadow-md transition group">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-pink-100 rounded-lg flex items-center justify-center group-hover:bg-pink-200 transition">
                            <svg class="w-6 h-6 text-pink-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-lg font-semibold text-gray-900 group-hover:text-pink-600 transition">Breeding Program</h3>
                            <p class="text-sm text-gray-600">Manage breeding cycles</p>
                        </div>
                    </div>
                </a>
            </div>

            <!-- Farm Performance -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                <!-- Farm Performance -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 overflow-hidden">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">🐷 Farm Performance</h3>
                    <div class="space-y-4">
                        @php
                            // Calculate real breeding statistics from database
                            $totalBreedingStock = \App\Models\PigManagement::where('stock_type', 'breeding')->count();
                            // Use enhanced pig counting for performance calculations
                            $weanedPigs = \App\Models\PigManagement::count();
                            $weaningAge = config('breeding.cycle.birth_to_weaning_days', 45);
                            $pigletsCount = \App\Models\Farrowing::where('farrowing_date', '>=', now()->subDays($weaningAge))
                                ->where('status', 'active')
                                ->sum('live_births');
                            $totalPigs = $weanedPigs + $pigletsCount;
                            $totalWeightRecords = \App\Models\PigWeight::count();
                            $recentWeightRecords = \App\Models\PigWeight::where('created_at', '>=', now()->subDays(30))->count();

                            $breedingStats = [
                                'breeding_stock_ratio' => $totalPigs > 0 ? round(($totalBreedingStock / $totalPigs) * 100) : 0,
                                'avg_weight_records' => $totalPigs > 0 ? round($totalWeightRecords / $totalPigs, 1) : 0,
                                'recent_activity_rate' => $totalPigs > 0 ? round(($recentWeightRecords / $totalPigs) * 100) : 0,
                                'data_completeness' => $totalPigs > 0 ? round((\App\Models\PigManagement::whereHas('weights')->count() / $totalPigs) * 100) : 0
                            ];
                        @endphp

                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-600">Breeding Stock Ratio</span>
                                <div class="flex items-center space-x-2">
                                    <div class="w-20 bg-gray-200 rounded-full h-2">
                                        <div class="bg-green-500 h-2 rounded-full transition-all duration-300" style="width: {{ min($breedingStats['breeding_stock_ratio'], 100) }}%"></div>
                                    </div>
                                    <span class="text-sm font-medium text-gray-900 w-10 text-right">{{ $breedingStats['breeding_stock_ratio'] }}%</span>
                                </div>
                            </div>

                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-600">Avg Weight Records</span>
                                <div class="flex items-center space-x-2">
                                    <div class="w-20 bg-gray-200 rounded-full h-2">
                                        <div class="bg-yellow-500 h-2 rounded-full transition-all duration-300" style="width: {{ min($breedingStats['avg_weight_records'], 100) }}%"></div>
                                    </div>
                                    <span class="text-sm font-medium text-gray-900 w-10 text-right">{{ $breedingStats['avg_weight_records'] }}%</span>
                                </div>
                            </div>

                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-600">Recent Activity Rate</span>
                                <div class="flex items-center space-x-2">
                                    <div class="w-20 bg-gray-200 rounded-full h-2">
                                        <div class="bg-blue-500 h-2 rounded-full transition-all duration-300" style="width: {{ min($breedingStats['recent_activity_rate'], 100) }}%"></div>
                                    </div>
                                    <span class="text-sm font-medium text-gray-900 w-10 text-right">{{ $breedingStats['recent_activity_rate'] }}%</span>
                                </div>
                            </div>

                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-600">Data Completeness</span>
                                <div class="flex items-center space-x-2">
                                    <div class="w-20 bg-gray-200 rounded-full h-2">
                                        <div class="bg-purple-500 h-2 rounded-full transition-all duration-300" style="width: {{ min($breedingStats['data_completeness'], 100) }}%"></div>
                                    </div>
                                    <span class="text-sm font-medium text-gray-900 w-10 text-right">{{ $breedingStats['data_completeness'] }}%</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Upcoming Events -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 overflow-hidden">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">📅 Upcoming Events</h3>
                    <div class="space-y-3">
                        @php
                            $upcomingEvents = [];

                            // Pigs without recent weight records (need weighing)
                            $needWeighing = \App\Models\PigManagement::whereDoesntHave('weights', function($query) {
                                $query->where('created_at', '>=', now()->subDays(30));
                            })->take(3)->get();

                            foreach($needWeighing as $pig) {
                                $upcomingEvents[] = [
                                    'type' => 'weighing_due',
                                    'title' => $pig->ear_tag_number . ' weighing overdue',
                                    'date' => now(),
                                    'days_until' => 0,
                                    'icon' => 'scale',
                                    'color' => 'red'
                                ];
                            }

                            // Breeding stock that might need health monitoring
                            $breedingChecks = \App\Models\PigManagement::where('stock_type', 'breeding')
                                ->whereRaw('DATEDIFF(NOW(), date_of_birth) >= 365') // 1+ years old
                                ->whereDoesntHave('weights', function($query) {
                                    $query->where('created_at', '>=', now()->subDays(60));
                                })
                                ->take(2)->get();

                            foreach($breedingChecks as $pig) {
                                $upcomingEvents[] = [
                                    'type' => 'health_check',
                                    'title' => $pig->ear_tag_number . ' health check recommended',
                                    'date' => now()->addDays(7),
                                    'days_until' => 7,
                                    'icon' => 'heart',
                                    'color' => 'pink'
                                ];
                            }

                            // Young pigs approaching maturity (5+ months old)
                            $maturingPigs = \App\Models\PigManagement::whereRaw('DATEDIFF(NOW(), date_of_birth) >= 150')
                                ->whereRaw('DATEDIFF(NOW(), date_of_birth) <= 180')
                                ->take(2)->get();

                            foreach($maturingPigs as $pig) {
                                $maturityDate = $pig->date_of_birth->copy()->addDays(180);
                                $upcomingEvents[] = [
                                    'type' => 'maturity_check',
                                    'title' => $pig->ear_tag_number . ' approaching maturity',
                                    'date' => $maturityDate,
                                    'days_until' => (int) now()->diffInDays($maturityDate, false),
                                    'icon' => 'calendar',
                                    'color' => 'blue'
                                ];
                            }

                            // Sort by urgency (days until event)
                            usort($upcomingEvents, function($a, $b) {
                                return $a['days_until'] <=> $b['days_until'];
                            });
                        @endphp

                        @forelse($upcomingEvents as $event)
                            <div class="flex items-center justify-between p-3 bg-{{ $event['color'] }}-50 rounded-lg">
                                <div class="flex items-center">
                                    <div class="w-8 h-8 bg-{{ $event['color'] }}-100 rounded-full flex items-center justify-center mr-3">
                                        @if($event['icon'] === 'scale')
                                            <svg class="w-4 h-4 text-{{ $event['color'] }}-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/>
                                            </svg>
                                        @elseif($event['icon'] === 'heart')
                                            <svg class="w-4 h-4 text-{{ $event['color'] }}-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                                            </svg>
                                        @else
                                            <svg class="w-4 h-4 text-{{ $event['color'] }}-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                            </svg>
                                        @endif
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-900">{{ $event['title'] }}</p>
                                        <p class="text-xs text-gray-500">{{ $event['date']?->format('M j, Y') }}</p>
                                    </div>
                                </div>
                                <span class="text-xs font-medium text-{{ $event['color'] }}-700">
                                    @if($event['days_until'] < 0)
                                        {{ abs((int)$event['days_until']) }} days overdue
                                    @elseif($event['days_until'] == 0)
                                        Today
                                    @else
                                        {{ (int)$event['days_until'] }} days
                                    @endif
                                </span>
                            </div>
                        @empty
                            <div class="text-center py-8">
                                <svg class="w-12 h-12 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                                <p class="text-gray-500">No upcoming events</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Chart.js configuration
            Chart.defaults.font.family = 'Inter, system-ui, sans-serif';
            Chart.defaults.font.size = 12;
            Chart.defaults.color = '#6B7280';

            // Stock Distribution Pie Chart
            const stockCtx = document.getElementById('stockDistributionChart').getContext('2d');
            new Chart(stockCtx, {
                type: 'pie',
                data: {
                    labels: ['Breeding Stock', 'Standard Stock'],
                    datasets: [{
                        data: [{{ $breedingStock }}, {{ $standardStock }}],
                        backgroundColor: ['#10B981', '#3B82F6'],
                        borderWidth: 2,
                        borderColor: '#ffffff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = ((context.parsed / total) * 100).toFixed(1);
                                    return context.label + ': ' + context.parsed + ' (' + percentage + '%)';
                                }
                            }
                        }
                    }
                }
            });

            // Weight Alerts Bar Chart
            const alertsCtx = document.getElementById('weightAlertsChart').getContext('2d');
            new Chart(alertsCtx, {
                type: 'bar',
                data: {
                    labels: ['High', 'Medium', 'Low'],
                    datasets: [{
                        data: [{{ $highAlerts }}, {{ $mediumAlerts }}, {{ $lowAlerts }}],
                        backgroundColor: ['#EF4444', '#F59E0B', '#10B981'],
                        borderWidth: 0,
                        borderRadius: 8,
                        borderSkipped: false
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            },
                            grid: {
                                display: false
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });

            // Weight Trends Line Chart
            const trendsCtx = document.getElementById('weightTrendsChart').getContext('2d');
            
            // Generate last 7 days data
            const last7Days = [];
            const weightData = [];
            for (let i = 6; i >= 0; i--) {
                const date = new Date();
                date.setDate(date.getDate() - i);
                last7Days.push(date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }));
                
                // This would need to be passed from the controller for real data
                weightData.push(Math.floor(Math.random() * 10) + 5); // Placeholder data
            }

            new Chart(trendsCtx, {
                type: 'line',
                data: {
                    labels: last7Days,
                    datasets: [{
                        label: 'Weight Records',
                        data: weightData,
                        borderColor: '#3B82F6',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: '#3B82F6',
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2,
                        pointRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });

            // Breed Distribution Doughnut Chart
            const breedCtx = document.getElementById('breedDistributionChart').getContext('2d');
            new Chart(breedCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Large White', 'Duroc', 'LWDR'],
                    datasets: [{
                        data: [{{ $lwCount }}, {{ $drCount }}, {{ $lwdrCount }}],
                        backgroundColor: ['#3B82F6', '#10B981', '#8B5CF6'],
                        borderWidth: 2,
                        borderColor: '#ffffff',
                        cutout: '60%'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = ((context.parsed / total) * 100).toFixed(1);
                                    return context.label + ': ' + context.parsed + ' (' + percentage + '%)';
                                }
                            }
                        }
                    }
                }
            });
        });
    </script>
</x-app-layout>