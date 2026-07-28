@php
    // Calculate key weight metrics
    $totalWeightRecords = \App\Models\PigWeight::count();
    $avgWeight = \App\Models\PigWeight::avg('weight');
    $activeAlerts = \App\Models\WeightAlert::where('acknowledged', false)->count();
    $recentWeights = \App\Models\PigWeight::where('created_at', '>=', now()->subDays(7))->count();
    $overdueForWeighing = \App\Models\PigManagement::countOverdueForWeighing();
@endphp

<!-- Weight Analytics Summary -->
<div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-semibold text-gray-900">Analytics Weight Management Overview</h2>
        <a href="{{ route('weights.reports') }}" class="text-blue-600 hover:text-blue-800 text-sm font-medium">View Full Analytics →</a>
    </div>
    
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
        <!-- Total Records -->
        <div class="text-center">
            <div class="text-2xl font-bold text-blue-600">{{ number_format($totalWeightRecords) }}</div>
            <div class="text-sm text-gray-600">Total Records</div>
        </div>
        
        <!-- Average Weight -->
        <div class="text-center">
            <div class="text-2xl font-bold text-green-600">{{ number_format($avgWeight, 1) }} kg</div>
            <div class="text-sm text-gray-600">Average Weight</div>
        </div>
        
        <!-- Recent Activity -->
        <div class="text-center">
            <div class="text-2xl font-bold text-purple-600">{{ $recentWeights }}</div>
            <div class="text-sm text-gray-600">This Week</div>
        </div>
        
        <!-- Active Alerts -->
        <div class="text-center">
            <div class="text-2xl font-bold text-{{ $activeAlerts > 0 ? 'red' : 'green' }}-600">{{ $activeAlerts }}</div>
            <div class="text-sm text-gray-600">Weight Alerts</div>
        </div>
        
        <!-- Weighing Alerts -->
        <div class="text-center">
            <div class="text-2xl font-bold text-{{ $overdueForWeighing > 0 ? 'yellow' : 'green' }}-600">{{ $overdueForWeighing }}</div>
            <div class="text-sm text-gray-600">Overdue Weighing</div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="mt-4 flex flex-wrap gap-2">
        <a href="{{ route('weights.create') }}" class="inline-flex items-center px-3 py-1 bg-blue-100 text-blue-700 rounded-full text-sm hover:bg-blue-200 transition">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
            </svg>
            Record Weight
        </a>
        
        <a href="{{ route('weight-alerts.index') }}" class="inline-flex items-center px-3 py-1 bg-{{ $activeAlerts > 0 ? 'red' : 'gray' }}-100 text-{{ $activeAlerts > 0 ? 'red' : 'gray' }}-700 rounded-full text-sm hover:bg-{{ $activeAlerts > 0 ? 'red' : 'gray' }}-200 transition">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/>
            </svg>
            View Alerts
        </a>
        
        <a href="{{ route('weights.reports') }}" class="inline-flex items-center px-3 py-1 bg-green-100 text-green-700 rounded-full text-sm hover:bg-green-200 transition">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
            </svg>
            Reports
        </a>
    </div>
</div>
