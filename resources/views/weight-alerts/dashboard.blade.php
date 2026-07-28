<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Weight Alerts Dashboard
            </h2>
            <div class="flex space-x-2">
                <a href="{{ route('weight-alerts.index') }}" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
                    List All Alerts
                </a>
                <a href="{{ route('weights.index') }}" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
                    Weight Weight Records
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            <!-- Alert Statistics -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <div class="bg-white overflow-hidden shadow-sm rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-red-100 rounded-full flex items-center justify-center">
                                    <span class="text-red-600 font-bold text-sm">!</span>
                                </div>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-500">Total Unacknowledged</p>
                                <p class="text-2xl font-semibold text-gray-900">{{ $stats['total_unacknowledged'] }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow-sm rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-red-100 rounded-full flex items-center justify-center">
                                    <span class="text-red-600 font-bold text-sm">Alert</span>
                                </div>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-500">High Severity</p>
                                <p class="text-2xl font-semibold text-gray-900">{{ $stats['high_severity'] }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow-sm rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                                    <span class="text-blue-600 font-bold text-sm">Under</span>
                                </div>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-500">Under Weight</p>
                                <p class="text-2xl font-semibold text-gray-900">{{ $stats['under_weight'] }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow-sm rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-orange-100 rounded-full flex items-center justify-center">
                                    <span class="text-orange-600 font-bold text-sm">Over</span>
                                </div>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-500">Over Weight</p>
                                <p class="text-2xl font-semibold text-gray-900">{{ $stats['over_weight'] }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Alerts -->
            <div class="bg-white shadow-sm rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Recent Weight Alerts</h3>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pig</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Alert Type</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Severity</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Weight Info</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Age</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($recentAlerts as $alert)
                                <tr class="hover:bg-gray-50 {{ $alert->severity == 'high' ? 'bg-red-50' : '' }}">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">{{ $alert->pig->ear_tag_number ?? 'Unknown' }}</div>
                                        <div class="text-sm text-gray-500">{{ $alert->pig->breed ?? 'Unknown' }} {{ $alert->pig->gender ?? '' }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                            {{ $alert->alert_type == 'under_weight' ? 'bg-blue-100 text-blue-800' : '' }}
                                            {{ $alert->alert_type == 'over_weight' ? 'bg-orange-100 text-orange-800' : '' }}
                                            {{ $alert->alert_type == 'normal' ? 'bg-green-100 text-green-800' : '' }}
                                            {{ $alert->alert_type == 'no_standard' ? 'bg-gray-100 text-gray-800' : '' }}">
                                            {{ $alert->alert_type_display }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                            {{ $alert->severity == 'high' ? 'bg-red-100 text-red-800' : '' }}
                                            {{ $alert->severity == 'medium' ? 'bg-yellow-100 text-yellow-800' : '' }}
                                            {{ $alert->severity == 'low' ? 'bg-green-100 text-green-800' : '' }}">
                                            {{ ucfirst($alert->severity) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <div><strong>Actual:</strong> {{ number_format($alert->actual_weight, 1) }}kg</div>
                                        @if($alert->optimal_weight)
                                            <div class="text-xs text-gray-500">Expected: {{ number_format($alert->expected_min_weight, 1) }}-{{ number_format($alert->expected_max_weight, 1) }}kg</div>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ $alert->pig_age_weeks }} weeks
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ $alert->created_at->format('M j, Y') }}
                                        <div class="text-xs">{{ $alert->created_at->diffForHumans() }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <div class="flex space-x-2">
                                            <a href="{{ route('weight-alerts.show', $alert) }}" class="text-blue-600 hover:text-blue-900">View</a>
                                            <button onclick="acknowledgeAlert({{ $alert->id }})" class="text-green-600 hover:text-green-900">Acknowledge</button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                                        <div class="text-lg">No unacknowledged weight alerts!</div>
                                        <p class="mt-2">All pig weights are within expected ranges or have been acknowledged.</p>
                                        <div class="mt-4">
                                            <a href="{{ route('weights.create') }}" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
                                                Weight Record New Weight
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($recentAlerts->count() > 0)
                    <div class="px-6 py-4 border-t border-gray-200 text-center">
                        <a href="{{ route('weight-alerts.index') }}" class="text-blue-600 hover:text-blue-900 font-medium">
                            View All Weight Alerts →
                        </a>
                    </div>
                @endif
            </div>

            <!-- System Status -->
            <div class="mt-6 bg-green-50 border border-green-200 rounded-lg p-4">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                            <span class="text-green-600 font-bold text-sm">OK</span>
                        </div>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-lg font-medium text-green-800">Automatic Weight Alert System Active</h3>
                        <p class="text-sm text-green-600 mt-1">
                            Weight alerts are automatically generated when pig weights are recorded. 
                            The system compares actual weights against expected standards and creates alerts for deviations.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Individual acknowledge
        function acknowledgeAlert(alertId) {
            if (confirm('Are you sure you want to acknowledge this alert?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = `/weight-alerts/${alertId}/acknowledge`;
                
                const csrfToken = document.createElement('input');
                csrfToken.type = 'hidden';
                csrfToken.name = '_token';
                csrfToken.value = '{{ csrf_token() }}';
                form.appendChild(csrfToken);
                
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</x-app-layout>
