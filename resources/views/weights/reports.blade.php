<x-app-layout>
    <main class="flex-1 p-8">
        <div class="max-w-7xl mx-auto">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-bold text-gray-900">Weight Reports & Analytics</h2>
                <div class="flex gap-2">
                    <a href="{{ route('weights.create') }}" class="px-4 py-2 bg-green-600 text-white rounded-lg font-semibold hover:bg-green-700 transition">
                        <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                        </svg>
                        Record Weight
                    </a>
                    <a href="{{ route('weights.index') }}" class="px-4 py-2 bg-gray-600 text-white rounded-lg font-semibold hover:bg-gray-700 transition">
                        <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                        </svg>
                        All Weights
                    </a>
                </div>
            </div>

            <!-- Pig Selection Dropdown -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
                <form method="GET" class="flex items-end gap-4">
                    <div class="flex-1">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Select Pig</label>
                        <select name="pig_id" id="pigSelect" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500" onchange="this.form.submit()">
                            <option value="">-- Select a Pig --</option>
                            @foreach($pigs as $pigOption)
                                <option value="{{ $pigOption->id }}" 
                                        data-ear="{{ $pigOption->ear_tag_number }}"
                                        data-code="{{ $pigOption->code }}"
                                        data-breed="{{ $pigOption->breed }}"
                                        data-gender="{{ $pigOption->gender === 'M' ? 'Male' : 'Female' }}"
                                        {{ request('pig_id') == $pigOption->id ? 'selected' : '' }}>
                                    {{ $pigOption->ear_tag_number }} - {{ $pigOption->code }} ({{ $pigOption->breed }}, {{ $pigOption->gender === 'M' ? 'Male' : 'Female' }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    
                    <div class="flex-shrink-0">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Quick Search</label>
                        <input type="text" id="pigSearch" placeholder="Search pigs..." class="w-64 border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    </div>

                    @if($pig)
                        <button type="button" onclick="exportToPDF()" class="px-6 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition">
                            <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-4-4m4 4l4-4m3 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            Export PDF
                        </button>
                    @endif

                    <a href="{{ route('weights.reports') }}" class="px-6 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition">
                        Clear Selection
                    </a>
                </form>
            </div>

            @if($pig)
                <!-- Pig Information -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Pig Information</h3>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                        <div class="text-center">
                            <div class="text-2xl font-bold text-blue-600">{{ $pig->ear_tag_number }}</div>
                            <div class="text-sm text-gray-500">Ear Tag</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-gray-900">{{ $pig->code }}</div>
                            <div class="text-sm text-gray-500">Code</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-green-600">{{ $pig->breed }}</div>
                            <div class="text-sm text-gray-500">Breed</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-purple-600">{{ floor($pig->current_age_in_days / 30) }}m</div>
                            <div class="text-sm text-gray-500">Age ({{ $pig->current_age_in_days }} days)</div>
                        </div>
                    </div>
                </div>

                @if(!empty($analytics))
                    <!-- Weight Analytics -->
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                            <div class="flex items-center">
                                <div class="p-3 bg-blue-100 rounded-lg">
                                    <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                                    </svg>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-600">Total Records</p>
                                    <p class="text-2xl font-semibold text-gray-900">{{ $analytics['total_records'] }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                            <div class="flex items-center">
                                <div class="p-3 bg-green-100 rounded-lg">
                                    <svg class="h-6 w-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                                    </svg>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-600">Total Weight Gain</p>
                                    <p class="text-2xl font-semibold text-gray-900">{{ number_format($analytics['weight_gain'], 1) }} kg</p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                            <div class="flex items-center">
                                <div class="p-3 bg-yellow-100 rounded-lg">
                                    <svg class="h-6 w-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-600">Avg Daily Gain</p>
                                    <p class="text-2xl font-semibold text-gray-900">{{ number_format($analytics['avg_daily_gain'], 3) }} kg</p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                            <div class="flex items-center">
                                <div class="p-3 bg-purple-100 rounded-lg">
                                    <svg class="h-6 w-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/>
                                    </svg>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-600">Current Weight</p>
                                    <p class="text-2xl font-semibold text-gray-900">{{ number_format($analytics['latest_weight']->weight, 1) }} kg</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Growth Periods -->
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-6">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h3 class="text-lg font-semibold text-gray-900">Growth Periods Analysis</h3>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Period</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Duration</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Weight Range</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Phase</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Gain</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Daily Gain</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Performance</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($analytics['growth_periods'] as $period)
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                {{ $period['from_date']->format('M d') }} - {{ $period['to_date']->format('M d, Y') }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                {{ $period['days'] }} days
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                {{ number_format($period['from_weight'], 1) }} → {{ number_format($period['to_weight'], 1) }} kg
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                 @php
                                                 $endWeight = $period['to_weight'];
                                                 if ($endWeight < 25) {
                                                 $phase = 'Starter';
                                                 $badge = 'bg-blue-100 text-blue-800';
                                                 } elseif ($endWeight < 60) {
                                                 $phase = 'Grower';
                                                $badge = 'bg-yellow-100 text-yellow-800';
                                                } else {
                                                 $phase = 'Finisher';
                                                    $badge = 'bg-green-100 text-green-800';
                                                }
                                                     @endphp

                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $badge }}">
                                                   {{ $phase }}
                                                </span>
                                            </td>

                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <span class="{{ $period['weight_gain'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                                    {{ $period['weight_gain'] >= 0 ? '+' : '' }}{{ number_format($period['weight_gain'], 1) }} kg
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                {{ number_format($period['daily_gain'], 3) }} kg/day
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                @if($period['daily_gain'] >= 0.5)
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Excellent</span>
                                                @elseif($period['daily_gain'] >= 0.3)
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">Good</span>
                                                @elseif($period['daily_gain'] >= 0.1)
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">Average</span>
                                                @else
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">Poor</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                <!-- Weight History -->
                @if($weights->count() > 0)
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h3 class="text-lg font-semibold text-gray-900">Weight History ({{ $weights->count() }} records)</h3>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Weight</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Age</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Purpose</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Body Condition</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Health Status</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Notes</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($weights as $weight)
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                {{ $weight->weigh_date->format('M d, Y') }}
                                                @if($weight->weigh_date->format('H:i') !== '00:00')
                                                    <div class="text-xs text-gray-500">{{ $weight->weigh_date->format('H:i') }}</div>
                                                @endif
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900">{{ number_format($weight->weight, 1) }} kg</div>
                                                <div class="text-sm text-gray-500">{{ number_format($weight->weight_in_lbs, 1) }} lbs</div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                {{ $pig->date_of_birth ? $pig->date_of_birth->diffInDays($weight->weigh_date) : 'N/A' }} days
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
                                                @if($weight->body_condition_score)
                                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium
                                                        {{ $weight->body_condition_score <= 2 ? 'bg-red-100 text-red-800' : 
                                                           ($weight->body_condition_score == 3 ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800') }}">
                                                        {{ $weight->body_condition_score }}/5
                                                    </span>
                                                @else
                                                    <span class="text-gray-400">-</span>
                                                @endif
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                @if($weight->health_status)
                                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium
                                                        {{ $weight->health_status === 'excellent' ? 'bg-green-100 text-green-800' : 
                                                           ($weight->health_status === 'good' ? 'bg-blue-100 text-blue-800' : 
                                                           ($weight->health_status === 'fair' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800')) }}">
                                                        {{ ucfirst($weight->health_status) }}
                                                    </span>
                                                @else
                                                    <span class="text-gray-400">-</span>
                                                @endif
                                            </td>
                                            <td class="px-6 py-4 text-sm text-gray-900">
                                                @if($weight->notes)
                                                    <div class="max-w-xs truncate" title="{{ $weight->notes }}">{{ $weight->notes }}</div>
                                                @else
                                                    <span class="text-gray-400">-</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            @else
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-12 text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">No Pig Selected</h3>
                    <p class="mt-1 text-sm text-gray-500">Select a pig from the dropdown above to view detailed weight analytics and growth reports.</p>
                </div>
            @endif
        </div>
    </main>

    <script>
        // PDF export functionality
        function exportToPDF() {
            @if($pig)
                const pigId = {{ $pig->id }};
                window.open(`{{ route('weights.reports.export-pdf') }}?pig_id=${pigId}`, '_blank');
            @endif
        }

        // Quick search functionality for pig selection
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('pigSearch');
            const selectElement = document.getElementById('pigSelect');
            
            if (!searchInput || !selectElement) return;
            
            function debounce(fn, wait) {
                let t;
                return function() {
                    const ctx = this, args = arguments;
                    clearTimeout(t);
                    t = setTimeout(function(){ fn.apply(ctx, args); }, wait);
                };
            }
            
            const searchHandler = debounce(function() {
                const searchTerm = searchInput.value.toLowerCase().trim();
                const options = selectElement.querySelectorAll('option');
                
                options.forEach(function(option) {
                    if (option.value === '') return; // Skip the placeholder option
                    
                    const ear = option.dataset.ear || '';
                    const code = option.dataset.code || '';
                    const breed = option.dataset.breed || '';
                    const gender = option.dataset.gender || '';
                    
                    const searchText = (ear + ' ' + code + ' ' + breed + ' ' + gender).toLowerCase();
                    
                    if (searchText.includes(searchTerm)) {
                        option.style.display = '';
                    } else {
                        option.style.display = 'none';
                    }
                });
            }, 150);
            
            searchInput.addEventListener('input', searchHandler);
            
            // Clear search when form is submitted
            selectElement.addEventListener('change', function() {
                searchInput.value = '';
                // Show all options again
                const options = selectElement.querySelectorAll('option');
                options.forEach(function(option) {
                    option.style.display = '';
                });
            });
        });
    </script>
</x-app-layout>
