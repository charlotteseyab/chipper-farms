<x-app-layout>
    <div class="container mx-auto px-4 py-6">
        <div class="max-w-4xl mx-auto">
            <!-- Header -->
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Weight Alert Details</h1>
                    <p class="text-gray-600 mt-1">Alert for {{ $weightAlert->pig->ear_tag_number }}</p>
                </div>
                <div class="flex gap-3">
                    <a href="{{ route('weight-alerts.index') }}" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition">
                        ← Back to Alerts
                    </a>
                    @if(!$weightAlert->acknowledged)
                        <form method="POST" action="{{ route('weight-alerts.acknowledge', $weightAlert) }}" class="inline">
                            @csrf
                            <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                                ✓ Acknowledge Alert
                            </button>
                        </form>
                    @endif
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Alert Information -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">🚨 Alert Information</h2>
                    
                    <div class="space-y-4">
                        <!-- Alert Type -->
                        <div>
                            <label class="text-sm font-medium text-gray-500">Alert Type</label>
                            <div class="mt-1">
                                @php
                                    $alertConfig = [
                                        'under_weight' => ['icon' => '⬇️', 'color' => 'text-blue-600', 'bg' => 'bg-blue-100', 'text' => 'Under Weight'],
                                        'over_weight' => ['icon' => '⬆️', 'color' => 'text-orange-600', 'bg' => 'bg-orange-100', 'text' => 'Over Weight'],
                                        'normal' => ['icon' => '✅', 'color' => 'text-green-600', 'bg' => 'bg-green-100', 'text' => 'Normal'],
                                        'no_standard' => ['icon' => '❓', 'color' => 'text-gray-600', 'bg' => 'bg-gray-100', 'text' => 'No Standard']
                                    ];
                                    $config = $alertConfig[$weightAlert->alert_type] ?? $alertConfig['no_standard'];
                                @endphp
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium {{ $config['bg'] }} {{ $config['color'] }}">
                                    {{ $config['icon'] }} {{ $config['text'] }}
                                </span>
                            </div>
                        </div>

                        <!-- Severity -->
                        <div>
                            <label class="text-sm font-medium text-gray-500">Severity</label>
                            <div class="mt-1">
                                @php
                                    $severityConfig = [
                                        'high' => ['color' => 'text-red-600', 'bg' => 'bg-red-100'],
                                        'medium' => ['color' => 'text-yellow-600', 'bg' => 'bg-yellow-100'],
                                        'low' => ['color' => 'text-gray-600', 'bg' => 'bg-gray-100']
                                    ];
                                    $sevConfig = $severityConfig[$weightAlert->severity] ?? $severityConfig['low'];
                                @endphp
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium {{ $sevConfig['bg'] }} {{ $sevConfig['color'] }}">
                                    {{ ucfirst($weightAlert->severity) }}
                                </span>
                            </div>
                        </div>

                        <!-- Alert Message -->
                        <div>
                            <label class="text-sm font-medium text-gray-500">Alert Message</label>
                            <div class="mt-1 text-sm text-gray-900">
                                {{ $weightAlert->alert_message }}
                            </div>
                        </div>

                        <!-- Created Date -->
                        <div>
                            <label class="text-sm font-medium text-gray-500">Created</label>
                            <div class="mt-1 text-sm text-gray-900">
                                {{ $weightAlert->created_at->format('M j, Y g:i A') }}
                                <span class="text-gray-500">({{ $weightAlert->created_at->diffForHumans() }})</span>
                            </div>
                        </div>

                        <!-- Acknowledgment Status -->
                        <div>
                            <label class="text-sm font-medium text-gray-500">Status</label>
                            <div class="mt-1">
                                @if($weightAlert->acknowledged)
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-600">
                                        ✓ Acknowledged
                                    </span>
                                    @if($weightAlert->acknowledgedBy)
                                        <div class="text-xs text-gray-500 mt-1">
                                            By {{ $weightAlert->acknowledgedBy->name }} on {{ $weightAlert->acknowledged_at->format('M j, Y g:i A') }}
                                        </div>
                                    @endif
                                @else
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-red-100 text-red-600">
                                        ⚠️ Unacknowledged
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pig Information -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">🐷 Pig Information</h2>
                    
                    <div class="space-y-4">
                        <div>
                            <label class="text-sm font-medium text-gray-500">Ear Tag</label>
                            <div class="mt-1 text-sm font-medium text-gray-900">{{ $weightAlert->pig->ear_tag_number }}</div>
                        </div>

                        <div>
                            <label class="text-sm font-medium text-gray-500">Breed & Gender</label>
                            <div class="mt-1 text-sm text-gray-900">{{ $weightAlert->pig->breed_display }} {{ $weightAlert->pig->gender_display }}</div>
                        </div>

                        <div>
                            <label class="text-sm font-medium text-gray-500">Stock Type</label>
                            <div class="mt-1 text-sm text-gray-900">{{ $weightAlert->pig->stock_type_display }}</div>
                        </div>

                        <div>
                            <label class="text-sm font-medium text-gray-500">Age</label>
                            <div class="mt-1 text-sm text-gray-900">
                                {{ $weightAlert->pig_age_weeks }} weeks
                                @if($weightAlert->pig->date_of_birth)
                                    <span class="text-gray-500">({{ $weightAlert->pig->age_compact }})</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Weight Information -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">⚖️ Weight Information</h2>
                    
                    <div class="space-y-4">
                        <div>
                            <label class="text-sm font-medium text-gray-500">Actual Weight</label>
                            <div class="mt-1 text-lg font-semibold text-gray-900">
                                {{ number_format($weightAlert->actual_weight, 1) }} kg
                                <span class="text-sm text-gray-500 font-normal">
                                    ({{ number_format($weightAlert->actual_weight * 2.20462, 1) }} lbs)
                                </span>
                            </div>
                        </div>

                        @if($weightAlert->expected_min_weight && $weightAlert->expected_max_weight)
                            <div>
                                <label class="text-sm font-medium text-gray-500">Expected Range</label>
                                <div class="mt-1 text-sm text-gray-900">
                                    {{ number_format($weightAlert->expected_min_weight, 1) }} - {{ number_format($weightAlert->expected_max_weight, 1) }} kg
                                </div>
                            </div>

                            @if($weightAlert->optimal_weight)
                                <div>
                                    <label class="text-sm font-medium text-gray-500">Optimal Weight</label>
                                    <div class="mt-1 text-sm text-gray-900">{{ number_format($weightAlert->optimal_weight, 1) }} kg</div>
                                </div>
                            @endif

                            @if($weightAlert->deviation_percentage)
                                <div>
                                    <label class="text-sm font-medium text-gray-500">Deviation</label>
                                    <div class="mt-1 text-sm font-medium {{ $weightAlert->deviation_percentage > 0 ? 'text-orange-600' : 'text-blue-600' }}">
                                        {{ $weightAlert->deviation_percentage > 0 ? '+' : '' }}{{ number_format($weightAlert->deviation_percentage, 1) }}%
                                        {{ $weightAlert->deviation_percentage > 0 ? 'above expected' : 'below expected' }}
                                    </div>
                                </div>
                            @endif
                        @endif

                        <div>
                            <label class="text-sm font-medium text-gray-500">Weigh Date</label>
                            <div class="mt-1 text-sm text-gray-900">
                                {{ $weightAlert->weightRecord->weigh_date ? \Carbon\Carbon::parse($weightAlert->weightRecord->weigh_date)->format('M j, Y') : 'N/A' }}
                            </div>
                        </div>

                        <div>
                            <label class="text-sm font-medium text-gray-500">Purpose</label>
                            <div class="mt-1 text-sm text-gray-900">{{ ucfirst($weightAlert->weightRecord->purpose ?? 'N/A') }}</div>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">🎯 Recommended Actions</h2>
                    
                    <div class="space-y-3">
                        @if($weightAlert->alert_type === 'under_weight')
                            <div class="p-3 bg-blue-50 border border-blue-200 rounded-lg">
                                <div class="text-sm font-medium text-blue-800">Under Weight Actions:</div>
                                <ul class="text-sm text-blue-700 mt-1 space-y-1">
                                    <li>• Increase daily feed portions</li>
                                    <li>• Check for health issues</li>
                                    <li>• Monitor for parasites</li>
                                    <li>• Consider supplemental feeding</li>
                                </ul>
                            </div>
                        @elseif($weightAlert->alert_type === 'over_weight')
                            <div class="p-3 bg-orange-50 border border-orange-200 rounded-lg">
                                <div class="text-sm font-medium text-orange-800">Over Weight Actions:</div>
                                <ul class="text-sm text-orange-700 mt-1 space-y-1">
                                    <li>• Reduce daily feed portions</li>
                                    <li>• Increase exercise/activity</li>
                                    <li>• Check feed quality</li>
                                    <li>• Monitor for overfeeding</li>
                                </ul>
                            </div>
                        @elseif($weightAlert->alert_type === 'no_standard')
                            <div class="p-3 bg-gray-50 border border-gray-200 rounded-lg">
                                <div class="text-sm font-medium text-gray-800">No Standard Actions:</div>
                                <ul class="text-sm text-gray-700 mt-1 space-y-1">
                                    <li>• Create weight standards for this pig type</li>
                                    <li>• Set expected weight ranges</li>
                                    <li>• Enable automatic monitoring</li>
                                </ul>
                            </div>
                        @endif

                        <div class="flex gap-2 pt-2">
                            <a href="{{ route('weights.create') }}?pig_id={{ $weightAlert->pig_id }}" class="px-3 py-2 bg-green-600 text-white rounded text-sm hover:bg-green-700 transition">
                                Record New Weight
                            </a>
                            <a href="{{ route('pigs.show', $weightAlert->pig_id) }}" class="px-3 py-2 bg-blue-600 text-white rounded text-sm hover:bg-blue-700 transition">
                                View Pig Details
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            @if($weightAlert->acknowledgment_notes)
                <div class="mt-6 bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">📝 Acknowledgment Notes</h2>
                    <div class="text-sm text-gray-700">{{ $weightAlert->acknowledgment_notes }}</div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
