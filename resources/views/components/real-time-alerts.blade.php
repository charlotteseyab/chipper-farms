@php
use App\Services\RealTimeNotificationService;

try {
    // Get all real-time alerts
    $allAlerts = RealTimeNotificationService::getAllAlerts();
    $totalAlertCount = RealTimeNotificationService::getTotalAlertCount();
    $highPriorityAlerts = RealTimeNotificationService::getHighPriorityAlerts();

    // Combine all alerts
    $allAlertItems = collect();
    foreach ($allAlerts as $category => $data) {
        if (isset($data['alerts']) && is_array($data['alerts'])) {
            foreach ($data['alerts'] as $alert) {
                $allAlertItems->push($alert);
            }
        }
    }

    // Sort by severity (high priority first)
    $allAlertItems = $allAlertItems->sortByDesc(function($alert) {
        return $alert['severity'] === 'high' ? 2 : ($alert['severity'] === 'medium' ? 1 : 0);
    });
} catch (\Exception $e) {
    // If there's an error, just show no alerts
    $totalAlertCount = 0;
    $allAlertItems = collect();
}
@endphp

@if($totalAlertCount > 0)
<div x-data="{ openAlerts: false }" @click.outside="openAlerts = false" class="relative mr-4">
    <!-- Alerts Button -->
    <button 
        @click="openAlerts = !openAlerts" 
        class="relative inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition"
    >
        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
        </svg>
        <span class="font-semibold">Alerts</span>
        @if($totalAlertCount > 0)
            <span class="ml-2 inline-flex items-center justify-center px-2 py-0.5 text-xs font-bold leading-none text-white bg-red-800 rounded-full">
                {{ $totalAlertCount }}
            </span>
        @endif
    </button>

    <!-- Alerts Dropdown -->
    <div 
        x-show="openAlerts" 
        x-transition
        class="absolute right-0 mt-2 w-96 bg-white rounded-lg shadow-xl border border-gray-200 z-50 max-h-96 overflow-y-auto"
        style="display: none;"
    >
        <div class="px-4 py-3 border-b border-gray-200 bg-gray-50">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-semibold text-gray-900">Real-Time Alerts</h3>
                <span class="text-xs text-gray-500">{{ $totalAlertCount }} active</span>
            </div>
        </div>

        <div class="divide-y divide-gray-100">
            @forelse($allAlertItems as $alert)
                <div class="px-4 py-3 hover:bg-gray-50 transition">
                    <div class="flex items-start">
                        <div class="flex-shrink-0 mt-0.5">
                            <span class="text-2xl">{{ $alert['icon'] }}</span>
                        </div>
                        <div class="ml-3 flex-1">
                            <div class="flex items-center justify-between">
                                <p class="text-sm font-medium text-gray-900">
                                    {{ $alert['title'] }}
                                </p>
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                    @if($alert['severity'] === 'high') bg-red-100 text-red-800
                                    @elseif($alert['severity'] === 'medium') bg-yellow-100 text-yellow-800
                                    @else bg-blue-100 text-blue-800
                                    @endif">
                                    {{ $alert['count'] }}
                                </span>
                            </div>
                            <p class="text-xs text-gray-600 mt-1">{{ $alert['message'] }}</p>
                            
                            @if(!empty($alert['items']) && count($alert['items']) > 0)
                                <div class="mt-2 space-y-1">
                                    @foreach(array_slice($alert['items'], 0, 3) as $item)
                                        <div class="text-xs text-gray-500">
                                            @if(isset($item['ear_tag']))
                                                • {{ $item['ear_tag'] }} 
                                                @if(isset($item['days_since']))
                                                    ({{ $item['days_since'] }} days)
                                                @endif
                                            @elseif(isset($item['sow_ear_tag']))
                                                • Sow {{ $item['sow_ear_tag'] }}
                                                @if(isset($item['days_overdue']))
                                                    ({{ $item['days_overdue'] }} days overdue)
                                                @endif
                                            @elseif(isset($item['name']))
                                                • {{ $item['name'] }} ({{ $item['quantity'] }} {{ $item['unit'] }})
                                            @endif
                                        </div>
                                    @endforeach
                                    @if(count($alert['items']) > 3)
                                        <div class="text-xs text-gray-400">
                                            + {{ count($alert['items']) - 3 }} more...
                                        </div>
                                    @endif
                                </div>
                            @endif
                            
                            <div class="mt-2">
                                <a href="{{ $alert['link'] }}" class="text-xs font-medium text-indigo-600 hover:text-indigo-800">
                                    View details →
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="px-4 py-6 text-center text-sm text-gray-500">
                    No active alerts
                </div>
            @endforelse
        </div>

        <div class="px-4 py-3 border-t border-gray-200 bg-gray-50 text-center">
            <a href="{{ route('notifications.index') }}" class="text-xs font-medium text-indigo-600 hover:text-indigo-800">
                View all notifications
            </a>
        </div>
    </div>
</div>
@endif

<!-- Alert Badge for Navigation (if no dropdown) -->
@if($totalAlertCount > 0 && false)
<div class="mr-4">
    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">
        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
        </svg>
        {{ $totalAlertCount }} Alert(s)
    </span>
</div>
@endif

