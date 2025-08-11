@php
    $unacknowledgedAlerts = App\Models\WeightAlert::where('acknowledged', false)->count();
    $highSeverityAlerts = App\Models\WeightAlert::where('severity', 'high')->where('acknowledged', false)->count();
    $underWeightAlerts = App\Models\WeightAlert::where('alert_type', 'under_weight')->where('acknowledged', false)->count();
    $overWeightAlerts = App\Models\WeightAlert::where('alert_type', 'over_weight')->where('acknowledged', false)->count();
@endphp

@if($unacknowledgedAlerts > 0)
    <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-4 mb-6">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-lg font-medium text-gray-900 flex items-center">
                <svg class="w-5 h-5 text-orange-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                </svg>
                Weight Alerts
            </h3>
            <a href="{{ route('weight-alerts.dashboard') }}" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                View All →
            </a>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <!-- Total Unacknowledged -->
            <div class="text-center">
                <div class="text-2xl font-bold text-red-600">{{ $unacknowledgedAlerts }}</div>
                <div class="text-xs text-gray-500">Unacknowledged</div>
            </div>

            <!-- High Severity -->
            @if($highSeverityAlerts > 0)
                <div class="text-center">
                    <div class="text-2xl font-bold text-red-600">{{ $highSeverityAlerts }}</div>
                    <div class="text-xs text-gray-500">High Severity</div>
                </div>
            @endif

            <!-- Under Weight -->
            @if($underWeightAlerts > 0)
                <div class="text-center">
                    <div class="text-2xl font-bold text-blue-600">{{ $underWeightAlerts }}</div>
                    <div class="text-xs text-gray-500">Under Weight</div>
                </div>
            @endif

            <!-- Over Weight -->
            @if($overWeightAlerts > 0)
                <div class="text-center">
                    <div class="text-2xl font-bold text-orange-600">{{ $overWeightAlerts }}</div>
                    <div class="text-xs text-gray-500">Over Weight</div>
                </div>
            @endif
        </div>

        @if($highSeverityAlerts > 0)
            <div class="mt-3 p-2 bg-red-50 border border-red-200 rounded text-sm text-red-700">
                <strong>⚠️ Attention:</strong> {{ $highSeverityAlerts }} pig(s) have high severity weight alerts requiring immediate attention.
            </div>
        @endif
    </div>
@endif
