<x-app-layout>
    <div class="container mx-auto px-4 py-6">
        <div class="max-w-7xl mx-auto">
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

            <!-- Top Row: Alert Status & Pig Info -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                <!-- Alert Status Card -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
                    <h3 class="text-base font-semibold text-gray-900 mb-4 flex items-center">
                        <span class="text-lg mr-2">🚨</span>
                        Alert Status
                    </h3>
                    
                    <div class="space-y-4">
                        <!-- Alert Type -->
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Alert Type</label>
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

                        <!-- Severity -->
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Severity</label>
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

                        <!-- Status -->
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Status</label>
                            @if($weightAlert->acknowledged)
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-600">
                                    ✓ Acknowledged
                                </span>
                                @if($weightAlert->acknowledgedBy)
                                    <div class="text-sm text-gray-600 mt-1">
                                        By {{ $weightAlert->acknowledgedBy->name }}
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        {{ $weightAlert->acknowledged_at->format('M j, Y g:i A') }}
                                    </div>
                                @endif
                            @else
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-red-100 text-red-600">
                                    ⚠️ Unacknowledged
                                </span>
                            @endif
                        </div>

                        <!-- Created Date -->
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Created</label>
                            <div class="text-sm text-gray-900">
                                {{ $weightAlert->created_at->format('M j, Y g:i A') }}
                            </div>
                            <div class="text-xs text-gray-500">
                                {{ $weightAlert->created_at->diffForHumans() }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pig Info Card -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
                    <h3 class="text-base font-semibold text-gray-900 mb-4 flex items-center">
                        <span class="text-lg mr-2">🐷</span>
                        Pig Details
                    </h3>
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Ear Tag</label>
                            <div class="text-lg font-semibold text-gray-900">{{ $weightAlert->pig->ear_tag_number }}</div>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Breed & Gender</label>
                            <div class="text-sm text-gray-900">
                                <span class="inline-flex items-center">
                                    @if($weightAlert->pig->breed)
                                        {{ ucfirst($weightAlert->pig->breed) }}
                                    @else
                                        <span class="text-gray-400">No breed set</span>
                                    @endif
                                    @if($weightAlert->pig->gender)
                                        <span class="mx-2 text-gray-400">•</span>
                                        @if($weightAlert->pig->gender === 'M')
                                            <svg class="w-4 h-4 text-blue-600 mr-1" fill="currentColor" viewBox="0 0 24 24">
                                                <circle cx="9" cy="15" r="6" stroke="currentColor" stroke-width="2" fill="none"/>
                                                <path d="M15 9L21 3M21 3h-5M21 3v5" stroke="currentColor" stroke-width="2" fill="none"/>
                                            </svg>
                                            Male
                                        @else
                                            <svg class="w-4 h-4 text-pink-600 mr-1" fill="currentColor" viewBox="0 0 24 24">
                                                <circle cx="12" cy="8" r="5" stroke="currentColor" stroke-width="2" fill="none"/>
                                                <path d="M12 13v8M9 18h6" stroke="currentColor" stroke-width="2" fill="none"/>
                                            </svg>
                                            Female
                                        @endif
                                    @else
                                        <span class="text-gray-400">• No gender set</span>
                                    @endif
                                </span>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Stock Type</label>
                            <div class="text-sm text-gray-900">
                                @if($weightAlert->pig->stock_type)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                        @if($weightAlert->pig->stock_type === 'breeding') bg-purple-100 text-purple-800
                                        @elseif($weightAlert->pig->stock_type === 'finisher') bg-green-100 text-green-800
                                        @elseif($weightAlert->pig->stock_type === 'grower') bg-blue-100 text-blue-800
                                        @elseif($weightAlert->pig->stock_type === 'weaner') bg-yellow-100 text-yellow-800
                                        @else bg-gray-100 text-gray-800
                                        @endif">
                                        {{ ucfirst($weightAlert->pig->stock_type) }}
                                    </span>
                                @else
                                    <span class="text-gray-400">No stock type set</span>
                                @endif
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Age</label>
                            <div class="text-sm text-gray-900">
                                {{ $weightAlert->pig_age_weeks }} weeks
                                @if($weightAlert->pig->date_of_birth)
                                    <span class="text-gray-500">({{ $weightAlert->pig->age_compact }})</span>
                                @endif
                            </div>
                        </div>

                        @if($weightAlert->pig->room)
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Location</label>
                                <div class="text-sm text-gray-900">
                                    {{ $weightAlert->pig->room->name }}
                                    @if($weightAlert->pig->room->structure)
                                        <span class="text-gray-500">({{ $weightAlert->pig->room->structure->name }})</span>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Weight Summary Card -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
                    <h3 class="text-base font-semibold text-gray-900 mb-4 flex items-center">
                        <span class="text-lg mr-2">⚖️</span>
                        Weight Information
                    </h3>
                    
                    <!-- Two Column Layout -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Left Column -->
                        <div class="space-y-3">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Actual Weight</label>
                                <div class="text-xl font-bold text-gray-900">
                                    {{ number_format($weightAlert->actual_weight, 1) }} kg
                                </div>
                                <div class="text-sm text-gray-500">
                                    ({{ number_format($weightAlert->actual_weight * 2.20462, 1) }} lbs)
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Expected Range</label>
                                <div class="text-sm text-gray-900">
                                    @if($weightAlert->expected_min_weight && $weightAlert->expected_max_weight)
                                        {{ number_format($weightAlert->expected_min_weight, 1) }} - {{ number_format($weightAlert->expected_max_weight, 1) }} kg
                                    @else
                                        N/A
                                    @endif
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Optimal Weight</label>
                                <div class="text-sm text-gray-900">
                                    @if($weightAlert->optimal_weight)
                                        {{ number_format($weightAlert->optimal_weight, 1) }} kg
                                    @else
                                        N/A
                                    @endif
                                </div>
                            </div>
                        </div>

                        <!-- Right Column -->
                        <div class="space-y-3">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Target Week</label>
                                <div class="text-sm text-gray-900">{{ $weightAlert->target_week }}</div>
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Deviation</label>
                                <div class="text-sm font-medium {{ $weightAlert->deviation_percentage > 0 ? 'text-orange-600' : 'text-blue-600' }}">
                                    @if($weightAlert->deviation_percentage)
                                        {{ $weightAlert->deviation_percentage > 0 ? '+' : '' }}{{ number_format($weightAlert->deviation_percentage, 1) }}%
                                        <div class="text-xs {{ $weightAlert->deviation_percentage > 0 ? 'text-orange-500' : 'text-blue-500' }}">
                                            {{ $weightAlert->deviation_percentage > 0 ? 'above expected' : 'below expected' }}
                                        </div>
                                    @else
                                        N/A
                                    @endif
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Weigh Date</label>
                                <div class="text-sm text-gray-900">
                                    {{ $weightAlert->weightRecord->weigh_date ? \Carbon\Carbon::parse($weightAlert->weightRecord->weigh_date)->format('M j, Y') : 'N/A' }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Purpose - Full Width Below -->
                    <div class="mt-4 pt-4 border-t border-gray-200">
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Purpose</label>
                        <div class="text-sm text-gray-900">{{ ucfirst($weightAlert->weightRecord->purpose ?? 'N/A') }}</div>
                    </div>
                </div>
            </div>

            <!-- Bottom Row: Alert Message & Actions -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Alert Message -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
                    <h3 class="text-base font-semibold text-gray-900 mb-4 flex items-center">
                        <span class="text-lg mr-2">📝</span>
                        Alert Message
                    </h3>
                    <div class="text-sm text-gray-700 leading-relaxed bg-gray-50 p-4 rounded-lg">
                        {{ $weightAlert->alert_message }}
                    </div>
                    
                    @if($weightAlert->acknowledged && $weightAlert->acknowledgedBy)
                        <div class="mt-4 pt-4 border-t border-gray-200">
                            <div class="text-sm text-gray-600">
                                <span class="font-medium">Acknowledged by:</span> {{ $weightAlert->acknowledgedBy->name }}
                            </div>
                            <div class="text-xs text-gray-500 mt-1">
                                {{ $weightAlert->acknowledged_at->format('M j, Y g:i A') }}
                            </div>
                        </div>
                    @endif
                </div>

                <!-- Recommended Actions -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
                    <h3 class="text-base font-semibold text-gray-900 mb-4 flex items-center">
                        <span class="text-lg mr-2">🎯</span>
                        Recommended Actions
                    </h3>
                    
                    <div class="space-y-4">
                        @if($weightAlert->alert_type === 'under_weight')
                            <div class="p-4 bg-blue-50 border border-blue-200 rounded-lg">
                                <div class="text-sm font-semibold text-blue-800 mb-3">Under Weight Actions:</div>
                                <ul class="text-sm text-blue-700 space-y-2">
                                    <li class="flex items-start">
                                        <span class="text-blue-600 mr-2">•</span>
                                        <span>Increase daily feed portions</span>
                                    </li>
                                    <li class="flex items-start">
                                        <span class="text-blue-600 mr-2">•</span>
                                        <span>Check for health issues</span>
                                    </li>
                                    <li class="flex items-start">
                                        <span class="text-blue-600 mr-2">•</span>
                                        <span>Monitor for parasites</span>
                                    </li>
                                    <li class="flex items-start">
                                        <span class="text-blue-600 mr-2">•</span>
                                        <span>Consider supplemental feeding</span>
                                    </li>
                                </ul>
                            </div>
                        @elseif($weightAlert->alert_type === 'over_weight')
                            <div class="p-4 bg-orange-50 border border-orange-200 rounded-lg">
                                <div class="text-sm font-semibold text-orange-800 mb-3">Over Weight Actions:</div>
                                <ul class="text-sm text-orange-700 space-y-2">
                                    <li class="flex items-start">
                                        <span class="text-orange-600 mr-2">•</span>
                                        <span>Reduce daily feed portions</span>
                                    </li>
                                    <li class="flex items-start">
                                        <span class="text-orange-600 mr-2">•</span>
                                        <span>Increase exercise/activity</span>
                                    </li>
                                    <li class="flex items-start">
                                        <span class="text-orange-600 mr-2">•</span>
                                        <span>Check feed quality</span>
                                    </li>
                                    <li class="flex items-start">
                                        <span class="text-orange-600 mr-2">•</span>
                                        <span>Monitor for overfeeding</span>
                                    </li>
                                </ul>
                            </div>
                        @elseif($weightAlert->alert_type === 'no_standard')
                            <div class="p-4 bg-gray-50 border border-gray-200 rounded-lg">
                                <div class="text-sm font-semibold text-gray-800 mb-3">No Standard Actions:</div>
                                <ul class="text-sm text-gray-700 space-y-2">
                                    <li class="flex items-start">
                                        <span class="text-gray-600 mr-2">•</span>
                                        <span>Create weight standards for this pig type</span>
                                    </li>
                                    <li class="flex items-start">
                                        <span class="text-gray-600 mr-2">•</span>
                                        <span>Set expected weight ranges</span>
                                    </li>
                                    <li class="flex items-start">
                                        <span class="text-gray-600 mr-2">•</span>
                                        <span>Enable automatic monitoring</span>
                                    </li>
                                </ul>
                            </div>
                        @endif

                        <div class="flex gap-3 pt-2">
                            <a href="{{ route('weights.create') }}?pig_id={{ $weightAlert->pig_id }}" class="flex-1 px-4 py-2 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700 transition text-center">
                                Record New Weight
                            </a>
                            <a href="{{ route('pigs.show', $weightAlert->pig_id) }}" class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition text-center">
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
