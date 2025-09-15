<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Weight Alerts Management
            </h2>
            <div class="flex space-x-2">
                <!-- <a href="{{ route('weight-alerts.dashboard') }}" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
                    📊 Dashboard
                </a> -->
                <a href="{{ route('weights.index') }}" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
                    ⚖️ Weight Records
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            <!-- Alert Statistics Cards -->
            <!-- <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <div class="bg-white overflow-hidden shadow-sm rounded-lg">
                    <div class="p-4">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-red-100 rounded-full flex items-center justify-center">
                                    <span class="text-red-600 font-bold text-sm">!</span>
                                </div>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-500">Unacknowledged</p>
                                <p class="text-2xl font-semibold text-gray-900">{{ $stats['unacknowledged'] }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow-sm rounded-lg">
                    <div class="p-4">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-red-100 rounded-full flex items-center justify-center">
                                    <span class="text-red-600 font-bold text-sm">🚨</span>
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
                    <div class="p-4">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                                    <span class="text-blue-600 font-bold text-sm">⬇️</span>
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
                    <div class="p-4">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-orange-100 rounded-full flex items-center justify-center">
                                    <span class="text-orange-600 font-bold text-sm">⬆️</span>
                                </div>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-500">Over Weight</p>
                                <p class="text-2xl font-semibold text-gray-900">{{ $stats['over_weight'] }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div> -->

            <!-- Filters -->
            <div class="bg-white shadow-sm rounded-lg mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Filter Alerts</h3>
                    <form method="GET" action="{{ route('weight-alerts.index') }}" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Alert Type</label>
                            <select name="alert_type" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-600 focus:border-blue-600">
                                <option value="">All Types</option>
                                <option value="under_weight" {{ request('alert_type') == 'under_weight' ? 'selected' : '' }}>Under Weight</option>
                                <option value="over_weight" {{ request('alert_type') == 'over_weight' ? 'selected' : '' }}>Over Weight</option>
                                <option value="normal" {{ request('alert_type') == 'normal' ? 'selected' : '' }}>Normal</option>
                                <option value="no_standard" {{ request('alert_type') == 'no_standard' ? 'selected' : '' }}>No Standard</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Severity</label>
                            <select name="severity" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-600 focus:border-blue-600">
                                <option value="">All Severities</option>
                                <option value="high" {{ request('severity') == 'high' ? 'selected' : '' }}>High</option>
                                <option value="medium" {{ request('severity') == 'medium' ? 'selected' : '' }}>Medium</option>
                                <option value="low" {{ request('severity') == 'low' ? 'selected' : '' }}>Low</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                            <select name="acknowledged" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-600 focus:border-blue-600">
                                <option value="">All Alerts</option>
                                <option value="0" {{ request('acknowledged') === '0' ? 'selected' : '' }}>Unacknowledged</option>
                                <option value="1" {{ request('acknowledged') === '1' ? 'selected' : '' }}>Acknowledged</option>
                            </select>
                        </div>

                        <div class="flex items-end">
                            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium">
                                Apply Filters
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Alerts Table -->
            <div class="bg-white shadow-sm rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200">
                    <div class="flex justify-between items-center">
                        <h3 class="text-lg font-medium text-gray-900">Weight Alerts ({{ $alerts->total() }})</h3>
                        @if(Auth::user() && (Auth::user()->isAdmin() || Auth::user()->isSuperuser()) && $alerts->where('acknowledged', false)->count() > 0)
                            <button onclick="openBulkAcknowledgeModal()" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
                                ✅ Bulk Acknowledge
                            </button>
                        @endif
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <input type="checkbox" id="selectAll" class="rounded border-gray-300">
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pig</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Alert Type</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Severity</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Weight Info</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Age</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($alerts as $alert)
                                <tr class="hover:bg-gray-50 {{ $alert->severity == 'high' ? 'bg-red-50' : '' }}">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if(!$alert->acknowledged && Auth::user() && (Auth::user()->isAdmin() || Auth::user()->isSuperuser()))
                                            <input type="checkbox" name="alert_ids[]" value="{{ $alert->id }}" class="alert-checkbox rounded border-gray-300">
                                        @endif
                                    </td>
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
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if($alert->acknowledged)
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                Acknowledged
                                            </span>
                                        @else
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                                Pending
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <div class="flex space-x-2">
                                            <a href="{{ route('weight-alerts.show', $alert) }}" class="text-blue-600 hover:text-blue-900">View</a>
                                            @if(!$alert->acknowledged && Auth::user() && (Auth::user()->isAdmin() || Auth::user()->isSuperuser()))
                                                <button onclick="acknowledgeAlert({{ $alert->id }})" class="text-green-600 hover:text-green-900">Acknowledge</button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="px-6 py-12 text-center text-gray-500">
                                        <div class="text-lg">📊 No weight alerts found</div>
                                        <p class="mt-2">Weight alerts will appear here when pigs' weights deviate from expected standards.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                @if($alerts->hasPages())
                <div class="px-6 py-4 border-t border-gray-200 bg-white">
                        <div class="flex items-center justify-between">
                            <div class="text-sm text-gray-700">
                                Showing {{ $alerts->firstItem() }} to {{ $alerts->lastItem() }} of {{ $alerts->total() }} results
                            </div>
                            <div class="flex items-center space-x-2">
                                <x-simple-pagination :paginator="$alerts" />
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Bulk Acknowledge Modal -->
    <div id="bulkAcknowledgeModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Bulk Acknowledge Alerts</h3>
                <form id="bulkAcknowledgeForm" method="POST" action="{{ route('weight-alerts.bulk-acknowledge') }}">
                    @csrf
                    <div id="selectedAlertsContainer"></div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Notes (Optional)</label>
                        <textarea name="acknowledgment_notes" rows="3" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-600 focus:border-blue-600" placeholder="Add any notes about these alerts..."></textarea>
                    </div>
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeBulkAcknowledgeModal()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">Acknowledge Selected</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Bulk acknowledge functionality
        function openBulkAcknowledgeModal() {
            const selectedCheckboxes = document.querySelectorAll('.alert-checkbox:checked');
            if (selectedCheckboxes.length === 0) {
                alert('Please select at least one alert to acknowledge.');
                return;
            }

            const container = document.getElementById('selectedAlertsContainer');
            container.innerHTML = '';
            
            selectedCheckboxes.forEach(checkbox => {
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'alert_ids[]';
                hiddenInput.value = checkbox.value;
                container.appendChild(hiddenInput);
            });

            document.getElementById('bulkAcknowledgeModal').classList.remove('hidden');
        }

        function closeBulkAcknowledgeModal() {
            document.getElementById('bulkAcknowledgeModal').classList.add('hidden');
        }

        // Select all functionality
        document.getElementById('selectAll').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.alert-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });

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
