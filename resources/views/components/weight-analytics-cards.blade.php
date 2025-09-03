@php
    // Calculate weight analytics
    $totalPigs = \App\Models\PigManagement::count();
    $pigsWithWeights = \App\Models\PigManagement::whereHas('weights')->count();
    $totalWeightRecords = \App\Models\PigWeight::count();
    $recentWeights = \App\Models\PigWeight::where('created_at', '>=', now()->subDays(7))->count();
    
    // Average weight calculations
    $avgWeight = \App\Models\PigWeight::avg('weight');
    $maxWeight = \App\Models\PigWeight::max('weight');
    $minWeight = \App\Models\PigWeight::min('weight');
    
    // Weight alerts
    $activeAlerts = \App\Models\WeightAlert::where('acknowledged', false)->count();
    $criticalAlerts = \App\Models\WeightAlert::where('acknowledged', false)->where('severity', 'high')->count();
    
    // Recent weight trends (last 30 days)
    $thirtyDaysAgo = now()->subDays(30);
    $recentWeightsData = \App\Models\PigWeight::where('created_at', '>=', $thirtyDaysAgo)
        ->selectRaw('DATE(created_at) as date, AVG(weight) as avg_weight, COUNT(*) as count')
        ->groupBy('date')
        ->orderBy('date')
        ->get();
    
    $weightTrend = 'stable';
    $trendColor = 'gray';
    if ($recentWeightsData->count() >= 2) {
        $firstAvg = $recentWeightsData->first()->avg_weight;
        $lastAvg = $recentWeightsData->last()->avg_weight;
        if ($lastAvg > $firstAvg * 1.05) {
            $weightTrend = 'increasing';
            $trendColor = 'green';
        } elseif ($lastAvg < $firstAvg * 0.95) {
            $weightTrend = 'decreasing';
            $trendColor = 'red';
        }
    }
    
    // Breed performance
    $breedPerformance = \App\Models\PigWeight::join('pigs_management', 'pig_weights.pig_id', '=', 'pigs_management.id')
        ->selectRaw('pigs_management.breed, AVG(pig_weights.weight) as avg_weight, COUNT(*) as weight_count')
        ->groupBy('pigs_management.breed')
        ->orderBy('avg_weight', 'desc')
        ->get();
    
    // Age group analysis
    $ageGroups = [
        'Young (0-90 days)' => \App\Models\PigManagement::where('age_in_days', '<=', 90)->count(),
        'Growing (91-180 days)' => \App\Models\PigManagement::whereBetween('age_in_days', [91, 180])->count(),
        'Mature (181+ days)' => \App\Models\PigManagement::where('age_in_days', '>', 180)->count(),
    ];
@endphp

<!-- Weight Analytics Section -->
<div class="mb-8">
    <h2 class="text-xl font-semibold text-gray-900 mb-4">📊 Weight Analytics Overview</h2>
    
    <!-- Main Weight Metrics -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
        <!-- Total Weight Records -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Weight Records</p>
                    <p class="text-2xl font-bold text-gray-900">{{ number_format($totalWeightRecords) }}</p>
                </div>
                <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                </div>
            </div>
            <div class="mt-4">
                <div class="flex items-center text-sm text-gray-600">
                    <span class="text-blue-600 font-medium">{{ $recentWeights }}</span>
                    <span class="ml-1">records this week</span>
                </div>
            </div>
        </div>

        <!-- Average Weight -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Average Weight</p>
                    <p class="text-2xl font-bold text-gray-900">{{ number_format($avgWeight, 1) }} kg</p>
                </div>
                <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/>
                    </svg>
                </div>
            </div>
            <div class="mt-4">
                <div class="flex items-center text-sm text-gray-600">
                    <span class="text-{{ $trendColor }}-600 font-medium">{{ ucfirst($weightTrend) }}</span>
                    <span class="ml-1">trend (30 days)</span>
                </div>
            </div>
        </div>

        <!-- Weight Range -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Weight Range</p>
                    <p class="text-2xl font-bold text-gray-900">{{ number_format($minWeight, 1) }} - {{ number_format($maxWeight, 1) }} kg</p>
                </div>
                <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"/>
                    </svg>
                </div>
            </div>
            <div class="mt-4">
                <div class="flex items-center text-sm text-gray-600">
                    <span class="text-purple-600 font-medium">{{ $pigsWithWeights }}</span>
                    <span class="ml-1">pigs with weights</span>
                </div>
            </div>
        </div>

        <!-- Weight Alerts -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Weight Alerts</p>
                    <p class="text-2xl font-bold text-{{ $activeAlerts > 0 ? 'red' : 'green' }}-600">{{ $activeAlerts }}</p>
                </div>
                <div class="w-12 h-12 bg-{{ $activeAlerts > 0 ? 'red' : 'green' }}-100 rounded-lg flex items-center justify-center">
                    @if($activeAlerts > 0)
                        <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                        </svg>
                    @else
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    @endif
                </div>
            </div>
            <div class="mt-4">
                <div class="flex items-center text-sm text-{{ $activeAlerts > 0 ? 'red' : 'green' }}-600">
                    @if($activeAlerts > 0)
                        <span class="font-medium">{{ $criticalAlerts }} critical</span>
                        <span class="ml-1">need attention</span>
                    @else
                        <span class="font-medium">All weights normal</span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Detailed Analytics -->
    <!-- <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        Breed Performance
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">🐷 Breed Performance</h3>
            @if($breedPerformance->count() > 0)
                <div class="space-y-3">
                    @foreach($breedPerformance as $breed)
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <div class="w-3 h-3 rounded-full bg-blue-500 mr-3"></div>
                                <span class="text-sm font-medium text-gray-700">{{ $breed->breed }}</span>
                            </div>
                            <div class="text-right">
                                <div class="text-sm font-semibold text-gray-900">{{ number_format($breed->avg_weight, 1) }} kg</div>
                                <div class="text-xs text-gray-500">{{ $breed->weight_count }} records</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-gray-500 text-sm">No weight data available for breed comparison</p>
            @endif
        </div>

        Age Distribution
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">📅 Age Distribution</h3>
            <div class="space-y-3">
                @foreach($ageGroups as $group => $count)
                    @php
                        $percentage = $totalPigs > 0 ? ($count / $totalPigs) * 100 : 0;
                        $color = match($group) {
                            'Young (0-90 days)' => 'blue',
                            'Growing (91-180 days)' => 'green',
                            'Mature (181+ days)' => 'purple',
                            default => 'gray'
                        };
                    @endphp
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="w-3 h-3 rounded-full bg-{{ $color }}-500 mr-3"></div>
                            <span class="text-sm font-medium text-gray-700">{{ $group }}</span>
                        </div>
                        <div class="text-right">
                            <div class="text-sm font-semibold text-gray-900">{{ $count }}</div>
                            <div class="text-xs text-gray-500">{{ number_format($percentage, 1) }}%</div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div> -->

    <!-- Quick Actions -->
    <!-- <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4">
        <a href="{{ route('weights.create') }}" class="bg-blue-600 text-white rounded-lg p-4 text-center hover:bg-blue-700 transition">
            <div class="flex items-center justify-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                </svg>
                <span class="font-medium">Record Weight</span>
            </div>
        </a>
        
        <a href="{{ route('weights.reports') }}" class="bg-green-600 text-white rounded-lg p-4 text-center hover:bg-green-700 transition">
            <div class="flex items-center justify-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
                <span class="font-medium">View Reports</span>
            </div>
        </a>
        
        <a href="{{ route('weight-alerts.index') }}" class="bg-{{ $activeAlerts > 0 ? 'red' : 'gray' }}-600 text-white rounded-lg p-4 text-center hover:bg-{{ $activeAlerts > 0 ? 'red' : 'gray' }}-700 transition">
            <div class="flex items-center justify-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                </svg>
                <span class="font-medium">Weight Alerts ({{ $activeAlerts }})</span>
            </div>
        </a>
    </div> -->
</div>
