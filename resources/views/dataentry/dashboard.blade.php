<x-app-layout>
    <div class="min-h-screen">
        <div class="max-w-7xl mx-auto px-2 sm:px-6 lg:px-8 py-2 mb-12">

            <!-- Header -->
            <div class="mb-6">
                <div class="bg-gradient-to-r from-emerald-600 to-emerald-700 rounded-2xl shadow-lg overflow-hidden">
                    <div class="px-6 sm:px-8 py-6 flex items-center justify-between">
                        <div>
                            <h1 class="text-2xl sm:text-3xl font-bold text-white">Data Entry Dashboard</h1>
                            <p class="text-emerald-100 mt-1 text-sm">Quick overview and shortcuts for daily operations</p>
                        </div>
                        <div class="hidden sm:flex items-center gap-3">
                            <a href="{{ route('weights.create') }}" class="inline-flex items-center px-3 py-2 rounded-lg bg-white/15 hover:bg-white/25 text-white text-xs font-semibold transition">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                                New Weight
                            </a>
                            <a href="{{ route('all-stocks.create') }}" class="inline-flex items-center px-3 py-2 rounded-lg bg-white/15 hover:bg-white/25 text-white text-xs font-semibold transition">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                                New Pig
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Key Metrics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6 mb-8">
                @php
                    // Enhanced pig counting including piglets
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
                    $totalPigs = $weanedPigs + $pigletsCount;
                    $todayWeights = \App\Models\PigWeight::whereDate('created_at', today())->count();
                    $pendingWeighing = \App\Models\PigManagement::active()->whereDoesntHave('weights', function($query) {
                        $query->where('weigh_date', '>=', now()->subDays(7));
                    })->where('date_of_birth', '<=', now()->subDays(7))->count();
                    $activeAlerts = \App\Models\WeightAlert::where('acknowledged', false)->count();
                    $overdueForWeighing = \App\Models\PigManagement::countOverdueForWeighing();
                    
                    // Chart data
                    $dailyWeightsData = [];
                    for ($i = 6; $i >= 0; $i--) {
                        $date = now()->subDays($i);
                        $count = \App\Models\PigWeight::whereDate('created_at', $date)->count();
                        $dailyWeightsData[] = [
                            'date' => $date->format('M j'),
                            'count' => $count
                        ];
                    }

                    $pregNum = \App\Models\Mating::where('pregnancy_status', 'pregnant')->count();
                    $pregDenom = \App\Models\Mating::whereNotNull('female_id')->count();
                    $pregnancyRate = $pregDenom > 0 ? round($pregNum / $pregDenom * 100, 1) : 0;
                    $totalMatings = \App\Models\Mating::count();
                    $totalFarrowings = \App\Models\Farrowing::where('farrowing_date', '!=', null)->count();
                    $farrowingRate = $totalMatings > 0 ? round($totalFarrowings / $totalMatings * 100, 1) : 0;
                    $totalWeaned = \App\Models\Weaning::count();
                    $totalBorn = \App\Models\Farrowing::sum('live_births');
                    $weaningPercentage = $totalBorn > 0 ? round($totalWeaned / $totalBorn * 100, 1) : 0;
                    $avgStillborns = \App\Models\Farrowing::avg('stillbirths');
                    $avgBirthWeight = \App\Models\Farrowing::whereNotNull('average_birth_weight')->avg('average_birth_weight');
                @endphp

                <!-- Total Pigs -->
                <a href="{{ route('all-stocks.index') }}" class="block bg-gradient-to-br from-green-500 to-green-600 rounded-xl shadow-lg p-6 text-white hover:shadow-xl transition-all duration-300 transform hover:scale-105">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-green-100 text-sm font-medium">Total Pigs</p>
                            <p class="text-3xl font-bold">{{ number_format($totalPigs) }} • {{ number_format($pigletsCount) }} piglets</p>
                        </div>
                        <div class="w-12 h-12 bg-white bg-opacity-20 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                            </svg>
                        </div>
                    </div>
                </a>

                <!-- Today's Weights -->
                <a href="{{ route('weights.create') }}" class="block bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl shadow-lg p-6 text-white hover:shadow-xl transition-all duration-300 transform hover:scale-105">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-blue-100 text-sm font-medium">Weights Today</p>
                            <p class="text-3xl font-bold">{{ $todayWeights }}</p>
                        </div>
                        <div class="w-12 h-12 bg-white bg-opacity-20 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/>
                            </svg>
                        </div>
                    </div>
                </a>

                <!-- Pending Weighing -->
                <a href="{{ route('all-stocks.index', ['has_recent_weigh_date' => 'false', 'recent_weigh_days' => 7]) }}" class="block bg-gradient-to-br from-orange-500 to-orange-600 rounded-xl shadow-lg p-6 text-white hover:shadow-xl transition-all duration-300 transform hover:scale-105">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-orange-100 text-sm font-medium">Pending Weighing</p>
                            <p class="text-3xl font-bold">{{ $pendingWeighing }}</p>
                        </div>
                        <div class="w-12 h-12 bg-white bg-opacity-20 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                    </div>
                </a>

                <!-- Active Alerts -->
                <a href="{{ route('weight-alerts.index') }}" class="block bg-gradient-to-br from-red-500 to-red-600 rounded-xl shadow-lg p-6 text-white hover:shadow-xl transition-all duration-300 transform hover:scale-105">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-red-100 text-sm font-medium">Active Alerts</p>
                            <p class="text-3xl font-bold">{{ $activeAlerts }}</p>
                        </div>
                        <div class="w-12 h-12 bg-white bg-opacity-20 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.5 0L4.268 18.5c-.77.833.192 2.5 1.732 2.5z"/>
                            </svg>
                        </div>
                    </div>
                </a>

                <!-- Weighing Alerts -->
                <button onclick="toggleWeighingAlertsModal()" class="block w-full bg-gradient-to-br from-yellow-500 to-yellow-600 rounded-xl shadow-lg p-6 text-white hover:shadow-xl transition-all duration-300 transform hover:scale-105">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-yellow-100 text-sm font-medium">Weighing Alerts</p>
                            <p class="text-3xl font-bold">{{ $overdueForWeighing }}</p>
                            <p class="text-yellow-100 text-xs mt-1">{{ \App\Models\PigManagement::countDueForWeighing() }} due for weighing</p>
                        </div>
                        <div class="w-12 h-12 bg-white bg-opacity-20 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/>
                            </svg>
                        </div>
                    </div>
                </button>
                    </div>

            <!-- Charts Section -->
            <div class="mb-8">
                <!-- Daily Weights Chart -->
                <div class="bg-white rounded-2xl shadow-lg p-6 border border-gray-100">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h2 class="text-xl font-bold text-gray-900">Daily Weights Recorded</h2>
                            <p class="text-sm text-gray-500">Counts per day with 7-day moving average</p>
                        </div>
                        <div class="flex items-center space-x-4">
                            <!-- Date Range Selector -->
                            <div class="flex items-center space-x-2">
                                <label class="text-sm font-medium text-gray-700">From:</label>
                                <input type="date" id="startDate" class="px-3 py-1 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                            </div>
                            <div class="flex items-center space-x-2">
                                <label class="text-sm font-medium text-gray-700">To:</label>
                                <input type="date" id="endDate" class="px-3 py-1 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                            </div>
                            <button onclick="updateChart()" class="px-4 py-1 bg-emerald-600 text-white text-sm rounded-md hover:bg-emerald-700 transition-colors">
                                Update
                            </button>
                        </div>
                    </div>
                    <div class="h-64 relative">
                        <canvas id="dailyWeightsChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Main Content Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Left Column - Quick Actions -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Primary Actions -->
                    <div class="bg-white rounded-xl shadow-lg p-4">
                        <h2 class="text-xl font-bold text-gray-900 mb-4">Quick Actions</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <!-- Add New Pig -->
                            <a href="{{ route('all-stocks.create') }}" class="group p-4 bg-gradient-to-br from-green-500 to-green-600 rounded-lg text-white hover:from-green-600 hover:to-green-700 transition-all duration-300 transform hover:scale-105">
                                <div class="flex items-center mb-3">
                                    <div class="w-10 h-10 bg-white bg-opacity-20 rounded-lg flex items-center justify-center mr-3">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                        </svg>
                        </div>
                                    <div>
                                        <h3 class="text-base font-semibold">Add New Pig</h3>
                                        <p class="text-green-100 text-xs">Register new livestock</p>
                    </div>
                </div>
                                <div class="flex items-center text-green-100">
                                    <span class="text-xs">Start registration</span>
                                    <svg class="w-3 h-3 ml-2 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                    </svg>
                                </div>
                            </a>

                            <!-- Record Weight -->
                            <a href="{{ route('weights.create') }}" class="group p-4 bg-gradient-to-br from-blue-500 to-blue-600 rounded-lg text-white hover:from-blue-600 hover:to-blue-700 transition-all duration-300 transform hover:scale-105">
                                <div class="flex items-center mb-3">
                                    <div class="w-10 h-10 bg-white bg-opacity-20 rounded-lg flex items-center justify-center mr-3">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/>
                                        </svg>
                        </div>
                                    <div>
                                        <h3 class="text-base font-semibold">Record Weight</h3>
                                        <p class="text-blue-100 text-xs">Log weight measurements</p>
                        </div>
                    </div>
                                <div class="flex items-center text-blue-100">
                                    <span class="text-xs">Enter weight data</span>
                                    <svg class="w-3 h-3 ml-2 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                    </svg>
                </div>
                            </a>

                            <!-- Plan Mating -->
                            <a href="{{ route('mating.index') }}" class="group p-4 bg-gradient-to-br from-pink-500 to-pink-600 rounded-lg text-white hover:from-pink-600 hover:to-pink-700 transition-all duration-300 transform hover:scale-105">
                                <div class="flex items-center mb-3">
                                    <div class="w-10 h-10 bg-white bg-opacity-20 rounded-lg flex items-center justify-center mr-3">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                                        </svg>
                        </div>
                                    <div>
                                        <h3 class="text-base font-semibold">Plan Mating</h3>
                                        <p class="text-pink-100 text-xs">Schedule breeding</p>
                        </div>
                    </div>
                                <div class="flex items-center text-pink-100">
                                    <span class="text-xs">Create mating plan</span>
                                    <svg class="w-3 h-3 ml-2 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                    </svg>
                                </div>
                            </a>

                            <!-- View Reports -->
                            <a href="{{ route('weights.reports') }}" class="group p-4 bg-gradient-to-br from-purple-500 to-purple-600 rounded-lg text-white hover:from-purple-600 hover:to-purple-700 transition-all duration-300 transform hover:scale-105">
                                <div class="flex items-center mb-3">
                                    <div class="w-10 h-10 bg-white bg-opacity-20 rounded-lg flex items-center justify-center mr-3">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <h3 class="text-base font-semibold">View Weight Reports</h3>
                                        <p class="text-purple-100 text-xs">Analytics & insights</p>
                        </div>
                    </div>
                                <div class="flex items-center text-purple-100">
                                    <span class="text-xs">Generate reports</span>
                                    <svg class="w-3 h-3 ml-2 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                    </svg>
                        </div>
                        </a>
            </div>
                    </div>

                    <!-- Recent Activity -->
                    <div class="bg-white rounded-xl shadow-lg p-4">
                        <h2 class="text-lg font-bold text-gray-900 mb-4">Recent Activity</h2>
                    <div class="space-y-3">
                        @php
                                // Get recent activities from different sources
                                $recentWeights = \App\Models\PigWeight::with('pig')->latest()->limit(2)->get();
                                $recentPigs = \App\Models\PigManagement::latest()->limit(1)->get();
                                $recentSales = \App\Models\PigSale::with('pig')->latest()->limit(1)->get();
                                $recentDeaths = \App\Models\PigDeath::with('pig')->latest()->limit(1)->get();
                                $recentMatings = \App\Models\Mating::latest()->limit(1)->get();
                                
                                // Combine all activities with timestamps
                                $activities = collect();
                                
                                // Add weight activities
                                foreach($recentWeights as $weight) {
                                    $activities->push([
                                        'type' => 'weight',
                                        'title' => 'Weight recorded',
                                        'description' => $weight->pig->ear_tag_number ?? 'Unknown pig',
                                        'details' => $weight->weight . 'kg',
                                        'time' => $weight->created_at,
                                        'icon' => 'M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3',
                                        'color' => 'blue',
                                        'route' => route('weights.index')
                                    ]);
                                }
                                
                                // Add new pig activities
                                foreach($recentPigs as $pig) {
                                    $activities->push([
                                        'type' => 'pig',
                                        'title' => 'New pig added',
                                        'description' => $pig->ear_tag_number . ' (' . $pig->breed . ')',
                                        'details' => $pig->gender . ' • ' . $pig->stock_type,
                                        'time' => $pig->created_at,
                                        'icon' => 'M12 6v6m0 0v6m0-6h6m-6 0H6',
                                        'color' => 'green',
                                        'route' => route('pigs.show', $pig->id)
                                    ]);
                                }
                                
                                // Add sale activities
                                foreach($recentSales as $sale) {
                                    $activities->push([
                                        'type' => 'sale',
                                        'title' => 'Pig sold',
                                        'description' => $sale->pig->ear_tag_number ?? 'Unknown pig',
                                        'details' => '₵' . number_format($sale->sale_price, 2),
                                        'time' => $sale->created_at,
                                        'icon' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1',
                                        'color' => 'purple',
                                        'route' => route('sales.show', $sale->id)
                                    ]);
                                }
                                
                                // Add death activities
                                foreach($recentDeaths as $death) {
                                    $activities->push([
                                        'type' => 'death',
                                        'title' => 'Pig death recorded',
                                        'description' => $death->pig->ear_tag_number ?? 'Unknown pig',
                                        'details' => $death->cause_of_death,
                                        'time' => $death->created_at,
                                        'icon' => 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z',
                                        'color' => 'red',
                                        'route' => route('deaths.show', $death->id)
                                    ]);
                                }
                                
                                // Add mating activities
                                foreach($recentMatings as $mating) {
                                    $activities->push([
                                        'type' => 'mating',
                                        'title' => 'Mating planned',
                                        'description' => 'Sow: ' . ($mating->sow->ear_tag_number ?? 'Unknown') . ' × Boar: ' . ($mating->boar->ear_tag_number ?? 'Unknown'),
                                        'details' => $mating->mating_date ? $mating->mating_date->format('M j, Y') : 'TBD',
                                        'time' => $mating->created_at,
                                        'icon' => 'M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z',
                                        'color' => 'pink',
                                        'route' => route('mating.index')
                                    ]);
                                }
                                
                                // Sort by time and take the most recent 5
                                $recentActivities = $activities->sortByDesc('time')->take(5);
                        @endphp

                            @forelse($recentActivities as $activity)
                                <a href="{{ $activity['route'] }}" class="block">
                                    <div class="flex items-center p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                                        <div class="w-8 h-8 bg-{{ $activity['color'] }}-100 rounded-full flex items-center justify-center mr-3">
                                            <svg class="w-4 h-4 text-{{ $activity['color'] }}-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $activity['icon'] }}"/>
                                            </svg>
                                </div>
                                <div class="flex-grow">
                                            <p class="text-sm font-medium text-gray-900">{{ $activity['title'] }}</p>
                                            <p class="text-xs text-gray-600">{{ $activity['description'] }}</p>
                                            <p class="text-xs text-gray-500">{{ $activity['time']->diffForHumans() }}</p>
                                        </div>
                                        <div class="text-right">
                                            <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                            </svg>
                                    </div>
                                    </div>
                                </a>
                        @empty
                                <div class="text-center py-6">
                                    <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3">
                                        <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                    </svg>
                                    </div>
                                    <p class="text-sm text-gray-500">No recent activity</p>
                                </div>
                            @endforelse
                            </div>
                    </div>
                </div>

                <!-- Right Column - Tasks & Alerts -->
                <div class="space-y-6">
                    <!-- Today's Summary -->
                    <div class="bg-white rounded-2xl shadow-lg p-6">
                        <h2 class="text-xl font-bold text-gray-900 mb-4">Today's Summary</h2>
                        <div class="space-y-4">
                            <!-- Daily Weights Recorded -->
                            <div class="flex items-center justify-between p-3 bg-blue-50 rounded-lg">
                                <div class="flex items-center">
                                    <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center mr-3">
                                <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/>
                                </svg>
                            </div>
                            <div>
                                        <span class="text-sm font-medium text-gray-700">Weights Recorded</span>
                                        <p class="text-xs text-gray-500">Today</p>
                                    </div>
                                </div>
                                <span class="text-lg font-bold text-blue-600">{{ $todayWeights }}</span>
                            </div>

                            <!-- Daily New Pigs Added -->
                            <div class="flex items-center justify-between p-3 bg-green-50 rounded-lg">
                                <div class="flex items-center">
                                    <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center mr-3">
                                <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                </svg>
                            </div>
                            <div>
                                        <span class="text-sm font-medium text-gray-700">New Pigs Added</span>
                                        <p class="text-xs text-gray-500">Today</p>
                                    </div>
                                </div>
                                <span class="text-lg font-bold text-green-600">{{ \App\Models\PigManagement::whereDate('created_at', today())->count() }}</span>
                            </div>

                            <!-- Weekly Weights -->
                            <div class="flex items-center justify-between p-3 bg-purple-50 rounded-lg">
                                <div class="flex items-center">
                                    <div class="w-8 h-8 bg-purple-100 rounded-full flex items-center justify-center mr-3">
                                <svg class="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <span class="text-sm font-medium text-gray-700">Weights Added</span>
                                        <p class="text-xs text-gray-500">This Week</p>
                                    </div>
                                </div>
                                <span class="text-lg font-bold text-purple-600">{{ \App\Models\PigWeight::where('created_at', '>=', now()->startOfWeek())->count() }}</span>
                            </div>

                            <!-- Delayed Weighings -->
                            <div class="flex items-center justify-between p-3 bg-red-50 rounded-lg">
                                <div class="flex items-center">
                                    <div class="w-8 h-8 bg-red-100 rounded-full flex items-center justify-center mr-3">
                                        <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <span class="text-sm font-medium text-gray-700">Delayed Weighings</span>
                                        <p class="text-xs text-gray-500">Overdue pigs</p>
                                    </div>
                                </div>
                                <span class="text-lg font-bold text-red-600">{{ $overdueForWeighing }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Weighing Alerts Detail -->
                    <button onclick="toggleWeighingAlertsModal()" class="w-full bg-white rounded-2xl shadow-lg p-6 hover:shadow-xl transition-all duration-300 text-left">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-xl font-bold text-gray-900">Weighing Schedule Alerts</h2>
                            <div class="w-8 h-8 bg-yellow-100 rounded-lg flex items-center justify-center">
                                <svg class="w-4 h-4 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/>
                                </svg>
                            </div>
                        </div>
                        <div class="space-y-2">
                            @php
                            $overduePigs = \App\Models\PigManagement::getOverdueForWeighing(30)->take(5);
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
            </div>
        </div>
    </div>

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
                                $overdueDays = $daysSince - 14; // 14 is the weighing interval
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

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <script>
        function toggleWeighingAlertsModal() {
            const modal = document.getElementById('weighingAlertsModal');
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
                if (!modal.classList.contains('hidden')) {
                    toggleWeighingAlertsModal();
                }
            }
        });

        // Global chart variable
        let dailyWeightsChart = null;

        // Initialize Charts
        document.addEventListener('DOMContentLoaded', function() {
            const endDate = new Date();
            const startDate = new Date();
            startDate.setDate(startDate.getDate() - 6);
            document.getElementById('startDate').value = startDate.toISOString().split('T')[0];
            document.getElementById('endDate').value = endDate.toISOString().split('T')[0];
            initializeChart();
        });

        function createGradient(ctx) {
            const gradient = ctx.createLinearGradient(0, 0, 0, 300);
            gradient.addColorStop(0, 'rgba(16, 185, 129, 0.35)');
            gradient.addColorStop(1, 'rgba(16, 185, 129, 0.05)');
            return gradient;
        }

        function movingAverage(values, windowSize = 7) {
            const result = [];
            for (let i = 0; i < values.length; i++) {
                const start = Math.max(0, i - windowSize + 1);
                const window = values.slice(start, i + 1);
                const avg = window.reduce((a, b) => a + b, 0) / window.length;
                result.push(Number(avg.toFixed(2)));
            }
            return result;
        }

        function initializeChart() {
            const ctx = document.getElementById('dailyWeightsChart').getContext('2d');
            const dailyWeightsData = @json($dailyWeightsData);
            const labels = dailyWeightsData.map(item => item.date);
            const counts = dailyWeightsData.map(item => item.count);
            const avg7 = movingAverage(counts, 7);

            const fillGradient = createGradient(ctx);

            dailyWeightsChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels,
                    datasets: [
                        {
                            label: 'Weights Recorded',
                            data: counts,
                            borderColor: 'rgb(16, 185, 129)',
                            backgroundColor: fillGradient,
                            borderWidth: 2,
                            fill: true,
                            tension: 0.35,
                            pointBackgroundColor: 'rgb(16, 185, 129)',
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2,
                            pointRadius: 4
                        },
                        {
                            label: '7-day Avg',
                            data: avg7,
                            borderColor: 'rgb(5, 150, 105)',
                            backgroundColor: 'transparent',
                            borderDash: [6, 6],
                            borderWidth: 2,
                            tension: 0.35,
                            pointRadius: 0
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { intersect: false, mode: 'index' },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: 'rgba(0,0,0,0.85)',
                            titleColor: '#fff',
                            bodyColor: '#fff',
                            borderWidth: 0,
                            padding: 10,
                            displayColors: true,
                            callbacks: {
                                title: (items) => items[0].label,
                                label: (item) => `${item.dataset.label}: ${item.parsed.y}`
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: { color: 'rgba(0,0,0,0.06)', drawBorder: false },
                            ticks: { color: '#6B7280' }
                        },
                        x: {
                            grid: { display: false },
                            ticks: { color: '#6B7280' }
                        }
                    },
                    animation: { duration: 800, easing: 'easeOutQuart' }
                }
            });
        }

        function updateChart() {
            const startDate = document.getElementById('startDate').value;
            const endDate = document.getElementById('endDate').value;
            if (!startDate || !endDate) { alert('Please select both start and end dates'); return; }
            if (new Date(startDate) > new Date(endDate)) { alert('Start date cannot be after end date'); return; }

            const btn = document.querySelector('button[onclick="updateChart()"]');
            const originalText = btn.textContent; btn.textContent = 'Loading...'; btn.disabled = true;

            fetch(`/api/daily-weights-data?start_date=${startDate}&end_date=${endDate}`)
                .then(r => r.json())
                .then(data => {
                    const avg7 = movingAverage(data.data, 7);
                    dailyWeightsChart.data.labels = data.labels;
                    dailyWeightsChart.data.datasets[0].data = data.data;
                    dailyWeightsChart.data.datasets[1].data = avg7;
                    dailyWeightsChart.update('active');
                })
                .catch(err => { console.error('Error:', err); alert('Error loading chart data.'); })
                .finally(() => { btn.textContent = originalText; btn.disabled = false; });
        }
    </script>
</x-app-layout>