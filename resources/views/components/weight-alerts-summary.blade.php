@php
    $unacknowledgedAlerts = App\Models\WeightAlert::where('acknowledged', false)
        ->whereIn('id', function($query) {
            $query->selectRaw('MAX(id)')
                  ->from('weight_alerts')
                  ->groupBy('pig_id');
        })
        ->count();
    $underWeightAlerts = App\Models\WeightAlert::where('alert_type', 'under_weight')
        ->whereIn('id', function($query) {
            $query->selectRaw('MAX(id)')
                  ->from('weight_alerts')
                  ->groupBy('pig_id');
        })
        ->count();
    $overWeightAlerts = App\Models\WeightAlert::where('alert_type', 'over_weight')
        ->whereIn('id', function($query) {
            $query->selectRaw('MAX(id)')
                  ->from('weight_alerts')
                  ->groupBy('pig_id');
        })
        ->count();
@endphp

    <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-4 mb-6">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-lg font-medium text-gray-900 flex items-center">
                <svg class="w-5 h-5 text-orange-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                </svg>
                Weight Alerts
            </h3>
            <a href="{{ route('weight-alerts.index') }}" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                View All →
            </a>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <!-- Total Unacknowledged -->
            <a href="{{ route('weight-alerts.index', ['acknowledged' => 0]) }}"
               class="flex flex-col items-center bg-red-50 border border-red-200 rounded-lg py-4 transition hover:bg-red-100 hover:shadow cursor-pointer group">
                <div class="flex items-center mb-2">
                    <svg class="w-7 h-7 text-red-500 mr-2 group-hover:text-red-700 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                    </svg>
                    <span class="text-3xl font-extrabold text-red-600 group-hover:text-red-700 transition">{{ $unacknowledgedAlerts }}</span>
                </div>
                <div class="text-xs text-gray-600 font-medium uppercase tracking-wide">Unacknowledged</div>
            </a>

            <!-- Under Weight -->
            <a href="{{ route('weight-alerts.index', ['alert_type' => 'under_weight']) }}"
               class="flex flex-col items-center bg-blue-50 border border-blue-200 rounded-lg py-4 transition hover:bg-blue-100 hover:shadow cursor-pointer group">
                <div class="flex items-center mb-2">
                    <svg class="w-7 h-7 text-blue-500 mr-2 group-hover:text-blue-700 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 17l-4 4m0 0l-4-4m4 4V3"/>
                    </svg>
                    <span class="text-3xl font-extrabold text-blue-600 group-hover:text-blue-700 transition">{{ $underWeightAlerts }}</span>
                </div>
                <div class="text-xs text-gray-600 font-medium uppercase tracking-wide">Under Weight</div>
            </a>

            <!-- Over Weight -->
            <a href="{{ route('weight-alerts.index', ['alert_type' => 'over_weight']) }}"
               class="flex flex-col items-center bg-orange-50 border border-orange-200 rounded-lg py-4 transition hover:bg-orange-100 hover:shadow cursor-pointer group">
                <div class="flex items-center mb-2">
                    <svg class="w-7 h-7 text-orange-500 mr-2 group-hover:text-orange-700 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7l4-4m0 0l4 4m-4-4v18"/>
                    </svg>
                    <span class="text-3xl font-extrabold text-orange-600 group-hover:text-orange-700 transition">{{ $overWeightAlerts }}</span>
                </div>
                <div class="text-xs text-gray-600 font-medium uppercase tracking-wide">Over Weight</div>
            </a>
        </div>
    </div>
