<x-app-layout>
    <main class="flex-1 pt-0 p-8">
        <div class="max-w-7xl mx-auto">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-bold text-gray-900">Weight Management</h2>
                <div class="flex gap-2 flex-wrap">
                    <a href="{{ route('weights.create') }}" class="px-4 py-2 bg-green-600 text-white rounded-lg font-semibold hover:bg-green-700 transition">
                        <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                        </svg>
                        Record Weight
                    </a>
                    <a href="{{ route('weights.reports') }}" class="px-4 py-2 bg-blue-600 text-white rounded-lg font-semibold hover:bg-blue-700 transition">
                        <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        Weight Reports
                    </a>
                    <form method="GET" action="{{ route('weights.index.export-pdf') }}" class="inline">
                        @foreach(request()->except('page') as $key => $value)
                            <input type="hidden" name="{{ $key }}" value="{{ $value }}" />
                        @endforeach
                        <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg font-semibold hover:bg-red-700 transition" title="Export all weights (filtered) as PDF">
                            <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                            Export PDF
                        </button>
                    </form>
                    <form method="GET" action="{{ route('weights.index.export-excel') }}" class="inline flex items-center gap-2">
                        @foreach(request()->except(['page','month']) as $key => $value)
                            <input type="hidden" name="{{ $key }}" value="{{ $value }}" />
                        @endforeach
                        <label class="text-xs text-gray-600">Month</label>
                        <input type="month" name="month" value="{{ request('month') }}" class="border border-gray-300 rounded px-2 py-1 text-sm focus:ring-emerald-500 focus:border-emerald-500">
                        <button type="submit" class="px-4 py-2 bg-emerald-600 text-white rounded-lg font-semibold hover:bg-emerald-700 transition" title="Download weekly gain analysis (Excel/CSV)">
                            <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5h14a2 2 0 012 2v6a2 2 0 01-2 2h-4l-4 4v-4H5a2 2 0 01-2-2V7a2 2 0 012-2z"/>
                            </svg>
                            Export Excel
                        </button>
                    </form>
                </div>
            </div>

            <!-- Weight Alerts Summary -->
            <x-weight-alerts-summary />

            <!-- Filters -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
                <form method="GET" id="weightsFilterForm" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Pig</label>
                        <select name="pig_id" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-green-500 focus:border-green-500 auto-submit">
                            <option value="" disabled selected >-- All Pigs --</option>
                            @foreach($pigs as $pig)
                                <option value="{{ $pig->id }}" {{ request('pig_id') == $pig->id ? 'selected' : '' }}>
                                    {{ $pig->ear_tag_number }} - {{ $pig->code }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Purpose</label>
                        <select name="purpose" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-green-500 focus:border-green-500 auto-submit">
                            <option value="" disabled selected>-- All Purposes --</option>
                            @foreach($purposes as $purpose)
                                <option value="{{ $purpose }}" {{ request('purpose') == $purpose ? 'selected' : '' }}>
                                    {{ ucfirst($purpose) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Date From</label>
                        <input type="date" name="date_from" value="{{ request('date_from') }}" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-green-500 focus:border-green-500 auto-debounce">
                    </div> -->

                    <!-- <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Date To</label>
                        <input type="date" name="date_to" value="{{ request('date_to') }}" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-green-500 focus:border-green-500 auto-debounce">
                    </div> -->

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Weight From (kg)</label>
                        <input type="number" name="weight_from" value="{{ request('weight_from') }}" step="0.1" min="0" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-green-500 focus:border-green-500 auto-debounce">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Weight To (kg)</label>
                        <input type="number" name="weight_to" value="{{ request('weight_to') }}" step="0.1" min="0" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-green-500 focus:border-green-500 auto-debounce">
                    </div>

                    <div class="flex items-end gap-2">
                        <button type="submit" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition">
                            <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.207A1 1 0 013 6.5V4z"/>
                            </svg>
                            Filter
                        </button>
                        <a href="{{ route('weights.index') }}" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 transition">Clear</a>
                    </div>
                </form>
            </div>

            <!-- Weight Records Table -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">Weight Records ({{ $weights->total() }})</h3>
                </div>

                @if($weights->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pig</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Gender</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Weight & Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Purpose</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Age at Weighing</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Health Status</th>
                                    <!-- <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Body Condition</th> -->
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($weights as $weight)
                                    <tr class="hover:bg-gray-50">
                                        @php $pig = $weight->pig; @endphp
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div>
                                                    <div class="text-sm font-medium text-gray-900">{{ $pig->ear_tag_number ?? 'N/A' }}</div>
                                                    <div class="text-sm text-gray-500">{{ $pig->code ?? 'N/A' }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            @if($pig && $pig->gender)
                                                <span class="inline-flex items-center">
                                                    @if($pig->gender === 'M')
                                                        <svg class="w-4 h-4 text-blue-600 mr-1" fill="currentColor" viewBox="0 0 24 24">
                                                            <circle cx="9" cy="15" r="6" stroke="currentColor" stroke-width="2" fill="none"/>
                                                            <path d="M15 9L21 3M21 3h-5M21 3v5" stroke="currentColor" stroke-width="2" fill="none"/>
                                                        </svg>
                                                        <span class="text-sm font-medium text-blue-600">Male</span>
                                                    @else
                                                        <svg class="w-4 h-4 text-pink-600 mr-1" fill="currentColor" viewBox="0 0 24 24">
                                                            <circle cx="12" cy="8" r="5" stroke="currentColor" stroke-width="2" fill="none"/>
                                                            <path d="M12 13v8M9 18h6" stroke="currentColor" stroke-width="2" fill="none"/>
                                                        </svg>
                                                        <span class="text-sm font-medium text-pink-600">Female</span>
                                                    @endif
                                                </span>
                                            @else
                                                <span class="text-sm text-gray-400">-</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center gap-2">
                                                <div>
                                                    <div class="text-sm font-medium text-gray-900">{{ number_format($weight->weight, 1) }} kg</div>
                                                    <div class="text-sm text-gray-500">{{ number_format($weight->weight_in_lbs, 1) }} lbs</div>
                                                </div>
                                                @if($weight->weightAlerts->count() > 0)
                                                    @php
                                                        $alert = $weight->weightAlerts->first();
                                                        $alertConfig = [
                                                            'under_weight' => ['icon' => '⬇️', 'color' => 'text-blue-600', 'bg' => 'bg-blue-100', 'text' => 'Under Weight'],
                                                            'over_weight' => ['icon' => '⬆️', 'color' => 'text-orange-600', 'bg' => 'bg-orange-100', 'text' => 'Over Weight'],
                                                            'normal' => ['icon' => '✅', 'color' => 'text-green-600', 'bg' => 'bg-green-100', 'text' => 'Normal'],
                                                            'no_standard' => ['icon' => '❓', 'color' => 'text-gray-600', 'bg' => 'bg-gray-100', 'text' => 'No Standard']
                                                        ];
                                                        $config = $alertConfig[$alert->alert_type] ?? $alertConfig['no_standard'];
                                                        $severityColor = $alert->severity == 'high' ? 'border-red-500' : ($alert->severity == 'medium' ? 'border-yellow-500' : 'border-gray-300');
                                                    @endphp
                                                    <div class="flex items-center gap-1">
                                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium {{ $config['bg'] }} {{ $config['color'] }} border {{ $severityColor }}"
                                                              title="{{ $alert->alert_message }}">
                                                            {{ $config['icon'] }} {{ $config['text'] }}
                                                            @if($alert->severity == 'high')
                                                                <span class="ml-1 text-red-600 font-bold">!</span>
                                                            @endif
                                                        </span>
                                                        @if($alert->deviation_percentage)
                                                            <span class="text-xs {{ $alert->severity == 'high' ? 'text-red-600' : 'text-gray-500' }}">
                                                                ({{ number_format(abs($alert->deviation_percentage), 1) }}% {{ $alert->alert_type == 'under_weight' ? 'under' : 'over' }})
                                                            </span>
                                                        @endif
                                                    </div>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ $weight->weigh_date->format('M d, Y') }}
                                            @if($weight->weigh_date->format('H:i') !== '00:00')
                                                <div class="text-xs text-gray-500">{{ $weight->weigh_date->format('H:i') }}</div>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                {{ $weight->purpose === 'routine' ? 'bg-blue-100 text-blue-800' : 
                                                   ($weight->purpose === 'breeding' ? 'bg-green-100 text-green-800' : 
                                                   ($weight->purpose === 'health' ? 'bg-red-100 text-red-800' : 
                                                   ($weight->purpose === 'sale' ? 'bg-purple-100 text-purple-800' : 'bg-gray-100 text-gray-800'))) }}">
                                                {{ ucfirst($weight->purpose) }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            @if($pig && $pig->date_of_birth)
                                                @php
                                                    $ageAtWeighing = $pig->date_of_birth->diffInDays($weight->weigh_date);
                                                    $years = floor($ageAtWeighing / 365);
                                                    $remainingDays = $ageAtWeighing % 365;
                                                    $months = floor($remainingDays / 30);
                                                    $days = $remainingDays % 30;

                                                    $ageParts = [];
                                                    if ($years > 0) $ageParts[] = $years . 'y';
                                                    if ($months > 0) $ageParts[] = $months . 'm';
                                                    if ($days > 0 || empty($ageParts)) $ageParts[] = $days . 'd';

                                                    $ageDisplay = implode(' ', $ageParts);
                                                @endphp
                                                <span title="{{ $ageAtWeighing }} days">{{ $ageDisplay }}</span>
                                            @else
                                                N/A
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            @if($weight->health_status)
                                                @php
                                                    $healthConfig = [
                                                        'excellent' => ['color' => 'text-green-600', 'icon' => '🟢'],
                                                        'good' => ['color' => 'text-blue-600', 'icon' => '🔵'],
                                                        'fair' => ['color' => 'text-yellow-600', 'icon' => '🟡'],
                                                        'poor' => ['color' => 'text-red-600', 'icon' => '🔴']
                                                    ];
                                                    $config = $healthConfig[$weight->health_status] ?? $healthConfig['good'];
                                                @endphp
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium {{ $config['color'] }}">
                                                    {{ $config['icon'] }} {{ ucfirst($weight->health_status) }}
                                                </span>
                                            @else
                                                <span class="text-gray-400">-</span>
                                            @endif
                                        </td>
                                        <!-- <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            @if($weight->body_condition_score)
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium
                                                    {{ $weight->body_condition_score <= 2 ? 'text-red-800' : 
                                                    ($weight->body_condition_score == 3 ? 'text-yellow-800' : 'text-green-800') }}">
                                                    {{ $weight->body_condition_score }}/5
                                                </span>
                                            @else
                                                <span class="text-gray-400">-</span>
                                            @endif
                                        </td> -->
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <div class="flex gap-2">
                                                <a href="{{ route('weights.edit', $weight) }}" class="text-green-600 hover:text-green-900">Edit</a>
                                                @if($pig)
                                                    <a href="{{ route('pigs.show', $pig->id) }}#weight" class="text-blue-600 hover:text-blue-900">View Pig</a>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="px-6 py-4 border-t border-gray-200">
                        <div class="flex items-center justify-between">
                            <div class="text-sm text-gray-700">
                                Showing {{ $weights->firstItem() }} to {{ $weights->lastItem() }} of {{ $weights->total() }} results
                            </div>
                            <div class="flex items-center space-x-2">
                                <x-simple-pagination :paginator="$weights" />
                            </div>
                        </div>
                    </div>
                @else
                    <div class="px-6 py-12 text-center">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/>
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900">No weight records found</h3>
                        <p class="mt-1 text-sm text-gray-500">Get started by recording your first weight measurement.</p>
                        <div class="mt-6">
                            <a href="{{ route('weights.create') }}" class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                </svg>
                                Record Weight
                            </a>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('weightsFilterForm');

            // Auto-submit on select change
            document.querySelectorAll('#weightsFilterForm select.auto-submit').forEach(sel => {
                sel.addEventListener('change', () => form.submit());
            });

            // Debounced submit for date/number inputs
            let debounceTimer;
            function debouncedSubmit() {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(() => form.submit(), 400);
            }

            document.querySelectorAll('#weightsFilterForm input.auto-debounce').forEach(inp => {
                inp.addEventListener('input', debouncedSubmit);
                inp.addEventListener('change', () => form.submit());
            });
        });
    </script>
</x-app-layout>
