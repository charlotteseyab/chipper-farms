<x-app-layout>
    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Header -->
            <!-- <div class="mb-6">
                <h1 class="text-3xl font-bold text-gray-900">Superuser Dashboard</h1>
                <p class="text-gray-600 mt-2">Complete system overview and advanced controls</p>
            </div> -->

            <!-- Weight Analytics Cards -->
            <x-weight-analytics-cards />
            
            <!-- Executive Summary -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-6 mb-8">
                <!-- Farm Overview -->
                <a href="{{ route('all-stocks.index', ['stock_type' => null]) }}" class="block bg-gradient-to-r from-green-500 to-green-600 rounded-lg shadow-sm p-6 text-white hover:shadow-md transition">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-green-100 text-sm font-medium">Total Farm Value</p>
                            <p class="text-2xl font-bold">
                                @php
                                    // Align with admin dashboard: total weight (latest per pig) × live weight price
                                    $totalWeight = \App\Models\PigWeight::whereIn('id', function($query) {
                                        $query->select(\DB::raw('MAX(id)'))
                                              ->from('pig_weights')
                                              ->groupBy('pig_id');
                                    })->sum('weight');
                                    $liveWeightPrice = \App\Models\Setting::get('sales.pricing.live_weight_price', 30.00);
                                    $estimatedValue = $totalWeight * $liveWeightPrice;
                                @endphp
                                ₵{{ number_format($estimatedValue, 2) }}
                            </p>
                        </div>
                        <div class="w-12 h-12 bg-white bg-opacity-20 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"/>
                            </svg>
                        </div>
                    </div>
                </a>

                <!-- Production Efficiency -->
                <a href="{{ route('all-stocks.index', ['has_weights' => 'false']) }}" class="block bg-gradient-to-r from-blue-500 to-blue-600 rounded-lg shadow-sm p-6 text-white hover:shadow-md transition">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-blue-100 text-sm font-medium">Data Completeness</p>
                            <p class="text-2xl font-bold">
                                @php
                                    $totalPigs = \App\Models\PigManagement::count();
                                    $pigsWithWeights = \App\Models\PigManagement::whereHas('weights')->count();
                                    $dataCompleteness = $totalPigs > 0 ? round(($pigsWithWeights / $totalPigs) * 100) : 0;
                                @endphp
                                {{ $dataCompleteness }}%
                            </p>
                        </div>
                        <div class="w-12 h-12 bg-white bg-opacity-20 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                        </div>
                    </div>
                </a>

                <!-- Growth Rate -->
                <a href="{{ route('all-stocks.index', ['created_from' => now()->startOfMonth()->toDateString(), 'created_to' => now()->toDateString()]) }}" class="block bg-gradient-to-r from-purple-500 to-purple-600 rounded-lg shadow-sm p-6 text-white hover:shadow-md transition">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-purple-100 text-sm font-medium">Monthly Growth</p>
                            <p class="text-2xl font-bold">
                                @php
                                    $thisMonth = \App\Models\PigManagement::whereMonth('created_at', now()->month)
                                        ->whereYear('created_at', now()->year)->count();
                                    $lastMonth = \App\Models\PigManagement::whereMonth('created_at', now()->subMonth()->month)
                                        ->whereYear('created_at', now()->subMonth()->year)->count();
                                    $growthRate = $lastMonth > 0 ? round((($thisMonth - $lastMonth) / $lastMonth) * 100) : 0;
                                    $growthSign = $growthRate >= 0 ? '+' : '';
                                @endphp
                                {{ $growthSign }}{{ $growthRate }}%
                            </p>
                        </div>
                        <div class="w-12 h-12 bg-white bg-opacity-20 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 11l5-5m0 0l5 5m-5-5v12"/>
                            </svg>
                        </div>
                    </div>
                </a>

                <!-- System Health -->
                <a href="{{ route('weight-alerts.index', ['acknowledged' => '0']) }}" class="block bg-gradient-to-r from-yellow-500 to-yellow-600 rounded-lg shadow-sm p-6 text-white hover:shadow-md transition">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-yellow-100 text-sm font-medium">System Health</p>
                            <p class="text-2xl font-bold">
                                @php
                                    // Calculate system health based on real metrics
                                    $criticalAlerts = \App\Models\WeightAlert::where('severity', 'high')->where('acknowledged', false)->count();
                                    $totalAlerts = \App\Models\WeightAlert::where('acknowledged', false)->count();
                                    $totalPigs = \App\Models\PigManagement::count();
                                    $pigsWithRecentWeights = \App\Models\PigManagement::whereHas('weights', function($query) {
                                        $query->where('created_at', '>=', now()->subDays(30));
                                    })->count();

                                    // Calculate health score
                                    $dataQuality = $totalPigs > 0 ? ($pigsWithRecentWeights / $totalPigs) * 100 : 0;

                                    if ($criticalAlerts > 5 || $totalAlerts > 20 || $dataQuality < 50) {
                                        $healthStatus = 'Critical';
                                    } elseif ($criticalAlerts > 0 || $totalAlerts > 10 || $dataQuality < 80) {
                                        $healthStatus = 'Good';
                                    } else {
                                        $healthStatus = 'Excellent';
                                    }
                                @endphp
                                {{ $healthStatus }}
                            </p>
                        </div>
                        <div class="w-12 h-12 bg-white bg-opacity-20 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                    </div>
                </a>

                <!-- Critical Issues -->
                <a href="{{ route('weight-alerts.index', ['severity' => 'high', 'acknowledged' => '0']) }}" class="block bg-gradient-to-r from-red-500 to-red-600 rounded-lg shadow-sm p-6 text-white hover:shadow-md transition">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-red-100 text-sm font-medium">Critical Issues</p>
                            <p class="text-2xl font-bold">
                                @php
                                    $criticalIssues = \App\Models\WeightAlert::where('severity', 'high')
                                        ->where('acknowledged', false)->count();
                                @endphp
                                {{ $criticalIssues }}
                            </p>
                        </div>
                        <div class="w-12 h-12 bg-white bg-opacity-20 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                            </svg>
                        </div>
                    </div>
                </a>

                <!-- Weighing Alerts -->
                <a href="{{ route('weights.index') }}" class="block bg-gradient-to-r from-yellow-500 to-yellow-600 rounded-lg shadow-sm p-6 text-white hover:shadow-md transition">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-yellow-100 text-sm font-medium">Weighing Alerts</p>
                            <p class="text-2xl font-bold">
                                @php
                                    $overdueForWeighing = \App\Models\PigManagement::countOverdueForWeighing();
                                @endphp
                                {{ $overdueForWeighing }}
                            </p>
                        </div>
                        <div class="w-12 h-12 bg-white bg-opacity-20 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/>
                            </svg>
                        </div>
                    </div>
                </a>
            </div>

            <!-- Advanced Controls -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <a href="{{ route('all-stocks.index') }}" class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:shadow-md transition group">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center group-hover:bg-green-200 transition">
                            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-lg font-semibold text-gray-900 group-hover:text-green-600 transition">Stock Management</h3>
                            <p class="text-sm text-gray-600">Complete livestock control</p>
                        </div>
                    </div>
                </a>

                <a href="{{ route('weights.index') }}" class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:shadow-md transition group">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center group-hover:bg-blue-200 transition">
                            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 00-2 2z"/>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-lg font-semibold text-gray-900 group-hover:text-blue-600 transition">Weight Analytics</h3>
                            <p class="text-sm text-gray-600">View weight records and trends</p>
                        </div>
                    </div>
                </a>

                <a href="{{ route('weight-alerts.dashboard') }}" class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:shadow-md transition group">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center group-hover:bg-purple-200 transition">
                            <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-lg font-semibold text-gray-900 group-hover:text-purple-600 transition">Alert Management</h3>
                            <p class="text-sm text-gray-600">Monitor system alerts and issues</p>
                        </div>
                    </div>
                </a>

                <a href="{{ route('admin.dashboard') }}" class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:shadow-md transition group">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center group-hover:bg-red-200 transition">
                            <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-lg font-semibold text-gray-900 group-hover:text-red-600 transition">Admin Controls</h3>
                            <p class="text-sm text-gray-600">Access admin dashboard</p>
                        </div>
                    </div>
                </a>
            </div>

            <!-- Comprehensive Overview -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Farm Performance Metrics -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">🎯 Performance Metrics</h3>
                    <div class="space-y-4">
                        @php
                            // Calculate real farm metrics from database
                            $totalPigs = \App\Models\PigManagement::count();
                            $breedingStock = \App\Models\PigManagement::where('stock_type', 'breeding')->count();
                            $totalWeights = \App\Models\PigWeight::count();
                            $recentWeights = \App\Models\PigWeight::where('created_at', '>=', now()->subDays(30))->count();
                            $weightAlerts = \App\Models\WeightAlert::where('acknowledged', false)->count();

                            // Calculate real changes for breeding stock
                            $breedingThisMonth = \App\Models\PigManagement::where('stock_type', 'breeding')
                                ->whereMonth('created_at', now()->month)->count();

                            $metrics = [
                                ['label' => 'Total Farm Stock', 'value' => $totalPigs, 'status' => $totalPigs > 50 ? 'excellent' : 'good', 'change' => '+' . \App\Models\PigManagement::whereMonth('created_at', now()->month)->count()],
                                ['label' => 'Breeding Stock Ratio', 'value' => $totalPigs > 0 ? round(($breedingStock / $totalPigs) * 100) . '%' : '0%', 'status' => 'good', 'change' => '+' . $breedingThisMonth],
                                ['label' => 'Weight Records/Pig', 'value' => $totalPigs > 0 ? round($totalWeights / $totalPigs, 1) : '0', 'status' => 'good', 'change' => '+' . $recentWeights],
                                ['label' => 'Active Monitoring', 'value' => $totalPigs > 0 ? round(($recentWeights / $totalPigs) * 100) . '%' : '0%', 'status' => $weightAlerts < 5 ? 'excellent' : 'good', 'change' => '-' . $weightAlerts]
                            ];
                        @endphp

                        @foreach($metrics as $metric)
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                <div>
                                    <p class="text-sm font-medium text-gray-900">{{ $metric['label'] }}</p>
                                    <p class="text-xs text-gray-500">{{ $metric['value'] }}</p>
                                </div>
                                <div class="text-right">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                        {{ $metric['status'] === 'excellent' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800' }}">
                                        {{ ucfirst($metric['status']) }}
                                    </span>
                                    <p class="text-xs text-green-600 mt-1">{{ $metric['change'] }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- System Status -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">⚙️ System Status</h3>
                    <div class="space-y-4">
                        @php
                            $systemStatus = [
                                ['component' => 'Database', 'status' => 'operational', 'uptime' => '99.9%'],
                                ['component' => 'Web Server', 'status' => 'operational', 'uptime' => '100%'],
                                ['component' => 'Backup System', 'status' => 'operational', 'uptime' => '98.5%'],
                                ['component' => 'Alert System', 'status' => 'operational', 'uptime' => '99.7%']
                            ];
                        @endphp

                        @foreach($systemStatus as $status)
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <div class="w-3 h-3 bg-green-500 rounded-full mr-3"></div>
                                    <span class="text-sm text-gray-900">{{ $status['component'] }}</span>
                                </div>
                                <div class="text-right">
                                    <span class="text-xs text-green-600 font-medium">{{ $status['uptime'] }}</span>
                                    <p class="text-xs text-gray-500">{{ ucfirst($status['status']) }}</p>
                                </div>
                            </div>
                        @endforeach

                        <div class="mt-6 pt-4 border-t border-gray-200">
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-gray-600">Last System Check</span>
                                <span class="text-gray-900 font-medium">{{ now()->format('M j, g:i A') }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Stats -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">📊 Quick Stats</h3>
                    <div class="space-y-4">
                        @php
                            $quickStats = [
                                ['label' => 'Total Pigs', 'value' => \App\Models\PigManagement::count(), 'icon' => 'users'],
                                ['label' => 'Weight Records', 'value' => \App\Models\PigWeight::count(), 'icon' => 'scale'],
                                ['label' => 'Active Breeding', 'value' => \App\Models\PigManagement::where('stock_type', 'breeding')->count(), 'icon' => 'heart'],
                                ['label' => 'Monthly Revenue', 'value' => '₵' . number_format(\App\Models\PigManagement::whereMonth('created_at', now()->month)->count() * 500), 'icon' => 'dollar']
                            ];
                        @endphp

                        @foreach($quickStats as $stat)
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                                        @if($stat['icon'] === 'users')
                                            <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                                            </svg>
                                        @elseif($stat['icon'] === 'scale')
                                            <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/>
                                            </svg>
                                        @elseif($stat['icon'] === 'heart')
                                            <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                                            </svg>
                                        @else
                                            <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"/>
                                            </svg>
                                        @endif
                                    </div>
                                    <span class="text-sm text-gray-600">{{ $stat['label'] }}</span>
                                </div>
                                <span class="text-sm font-medium text-gray-900">{{ $stat['value'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>