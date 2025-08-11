<x-app-layout>
    <main class="flex-1 p-8">
        <div class="max-w-6xl mx-auto">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h2 class="text-2xl font-bold">Near Market Ready (80-100kg)</h2>
                    <p class="text-gray-600">Finishers approaching market weight - monitor closely for optimal sale timing</p>
                </div>
                <div class="flex gap-3">
                    <a href="{{ route('finishers.index') }}" class="px-4 py-2 bg-gray-600 text-white rounded-lg font-semibold hover:bg-gray-700">All Finishers</a>
                    <a href="{{ route('finishers.export.csv') }}" class="px-4 py-2 bg-green-600 text-white rounded-lg font-semibold hover:bg-green-700">Export CSV</a>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <div class="bg-white p-6 rounded-lg shadow">
                    <div class="flex items-center">
                        <div class="p-3 bg-yellow-100 rounded-lg">
                            <svg class="h-8 w-8 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Near Market Ready</p>
                            <p class="text-3xl font-bold text-yellow-600">{{ $stats['total_near_market'] }}</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-lg shadow">
                    <div class="flex items-center">
                        <div class="p-3 bg-green-100 rounded-lg">
                            <svg class="h-8 w-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Average Weight</p>
                            <p class="text-3xl font-bold text-green-600">{{ number_format($stats['average_weight'] ?? 0, 1) }}kg</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-lg shadow">
                    <div class="flex items-center">
                        <div class="p-3 bg-blue-100 rounded-lg">
                            <svg class="h-8 w-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Est. Days to Market</p>
                            <p class="text-3xl font-bold text-blue-600">{{ $stats['days_to_market'] }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Market Readiness Information -->
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
                <div class="flex">
                    <svg class="h-5 w-5 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-yellow-800">Near Market Stage Information</h3>
                        <div class="mt-2 text-sm text-yellow-700">
                            <p>• <strong>Weight Range:</strong> 80-100kg (approaching optimal market weight)</p>
                            <p>• <strong>Expected Growth:</strong> 0.6-0.9kg per day with finisher feed</p>
                            <p>• <strong>Market Timing:</strong> Monitor closely - optimal sale weight is 100-120kg</p>
                            <p>• <strong>Feed Strategy:</strong> Continue high-energy feed for final weight gain</p>
                            <p>• <strong>Monitoring:</strong> Weigh every 3-5 days to catch optimal market timing</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Near Market Ready Table -->
            <div class="bg-white shadow-xl rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold">Near Market Ready Finishers (80-100kg)</h3>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pig Code</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Gender</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Age</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Weight (kg)</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Market Progress</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Est. Market Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($finishers as $pig)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="text-sm font-medium text-gray-900">{{ $pig->code }}</div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $pig->gender === 'M' ? 'bg-blue-100 text-blue-800' : 'bg-pink-100 text-pink-800' }}">
                                            {{ $pig->gender === 'M' ? 'Male' : 'Female' }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ $pig->age_in_days }} days
                                        @if($pig->age_in_weeks > 0)
                                            <span class="text-gray-500">({{ $pig->age_in_weeks }}w)</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">{{ $pig->current_weight ?? 'N/A' }}</div>
                                        @if($pig->current_weight)
                                            <div class="text-xs text-gray-500">
                                                {{ 100 - $pig->current_weight }}kg to market weight
                                            </div>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if($pig->current_weight)
                                            @php
                                                $progress = (($pig->current_weight - 80) / 20) * 100;
                                                $progressColor = $progress >= 75 ? 'bg-green-500' : ($progress >= 50 ? 'bg-yellow-500' : 'bg-blue-500');
                                            @endphp
                                            <div class="w-full bg-gray-200 rounded-full h-2">
                                                <div class="{{ $progressColor }} h-2 rounded-full" style="width: {{ min($progress, 100) }}%"></div>
                                            </div>
                                            <div class="text-xs text-gray-500 mt-1">
                                                {{ number_format($progress, 1) }}% to market ready
                                            </div>
                                        @else
                                            <span class="text-gray-400">No weight data</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        @if($pig->current_weight)
                                            @php
                                                $daysToMarket = ceil((100 - $pig->current_weight) / 0.7); // Assuming 0.7kg/day growth
                                                $marketDate = now()->addDays($daysToMarket);
                                            @endphp
                                            <div class="text-sm text-gray-900">{{ $marketDate->format('M d, Y') }}</div>
                                            <div class="text-xs text-gray-500">~{{ $daysToMarket }} days</div>
                                        @else
                                            <span class="text-gray-400">Need weight data</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <div class="relative" x-data="{ open: false }">
                                            <button @click="open = !open" class="flex items-center text-gray-600 hover:text-gray-900">
                                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"/>
                                                </svg>
                                            </button>
                                            <div x-show="open" @click.away="open = false" x-transition class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg z-10 border">
                                                <div class="py-1">
                                                    <button onclick="openWeightModal({{ $pig->id }}, '{{ $pig->code }}')" class="flex items-center w-full px-4 py-2 text-sm text-blue-600 hover:bg-blue-50">
                                                        <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/>
                                                        </svg>
                                                        Update Weight
                                                    </button>
                                                    @if($pig->current_weight >= 95)
                                                        <a href="{{ route('pigs.sale-form', $pig->id) }}" class="flex items-center px-4 py-2 text-sm text-green-600 hover:bg-green-50">
                                                            <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm0 10c-4.41 0-8-1.79-8-4V6c0-2.21 3.59-4 8-4s8 1.79 8 4v8c0 2.21-3.59 4-8 4z"/>
                                                            </svg>
                                                            Prepare Sale
                                                        </a>
                                                    @endif
                                                    <a href="{{ route('pigs.show', $pig->id) }}" class="flex items-center px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">
                                                        <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                                        </svg>
                                                        View Details
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-6 py-4 text-center text-gray-500">
                                        No near market ready finishers found. These are pigs weighing between 80-100kg.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                @if($finishers->hasPages())
                    <div class="px-6 py-4 border-t border-gray-200">
                        {{ $finishers->links() }}
                    </div>
                @endif
            </div>
        </div>
    </main>

    <!-- Weight Update Modal -->
    <div id="weightModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
        <div class="relative top-10 mx-auto p-5 border w-full max-w-3xl shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Update Near Market Ready Weight & Pre-Sale Assessment</h3>
                <form id="weightForm" method="POST">
                    @csrf
                    <div id="weightInfo" class="bg-yellow-50 p-4 rounded-lg mb-4">
                        <!-- Weight info will be populated by JavaScript -->
                    </div>

                    <!-- Current Weight Section -->
                    <div class="bg-white border border-gray-200 rounded-lg p-4 mb-4">
                        <h4 class="text-md font-medium text-gray-900 mb-3">📏 Current Weight</h4>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">New Weight (kg)</label>
                                <input type="number" name="weight" step="0.1" min="80" max="300" required
                                       class="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500">
                                <p class="text-xs text-gray-500 mt-1">Near market finishers typically weigh 80-100kg</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Weigh Date</label>
                                <input type="date" name="weigh_date" required
                                       class="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500"
                                       value="{{ date('Y-m-d') }}">
                            </div>
                        </div>

                        <div class="mt-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Growth Stage</label>
                            <select name="growth_stage" required class="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500">
                                <option value="">Select growth stage...</option>
                                <option value="approaching_market">Approaching Market (80-90kg)</option>
                                <option value="near_market">Near Market (90-100kg)</option>
                                <option value="ready_soon">Ready Soon (100-110kg)</option>
                                <option value="monitor_closely">Monitor Closely (110kg+)</option>
                            </select>
                            <p class="text-xs text-gray-500 mt-1">Current growth stage affects monitoring frequency</p>
                        </div>
                    </div>

                    <!-- Pre-Market Assessment Section -->
                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">
                        <h4 class="text-md font-medium text-gray-900 mb-3">🎯 Pre-Market Assessment</h4>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Days to Market Weight</label>
                                <input type="number" name="days_to_market" min="0" max="90"
                                       class="w-full border-gray-300 rounded-md shadow-sm focus:ring-yellow-500 focus:border-yellow-500"
                                       placeholder="Estimated days">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Target Market Weight (kg)</label>
                                <input type="number" name="target_market_weight" step="0.1" min="90" max="150"
                                       class="w-full border-gray-300 rounded-md shadow-sm focus:ring-yellow-500 focus:border-yellow-500"
                                       placeholder="Target weight">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Expected Weekly Gain (kg)</label>
                                <input type="number" name="expected_weekly_gain" step="0.1" min="0" max="10"
                                       class="w-full border-gray-300 rounded-md shadow-sm focus:ring-yellow-500 focus:border-yellow-500"
                                       placeholder="Weekly gain">
                            </div>
                        </div>

                        <div class="mt-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Market Preparation Notes</label>
                            <textarea name="market_prep_notes" rows="2"
                                      class="w-full border-gray-300 rounded-md shadow-sm focus:ring-yellow-500 focus:border-yellow-500"
                                      placeholder="Notes about market preparation, buyer contacts, timing considerations..."></textarea>
                        </div>
                    </div>

                    <!-- Health & Condition Section -->
                    <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 mb-4">
                        <h4 class="text-md font-medium text-gray-900 mb-3">🏥 Health & Readiness</h4>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Body Condition Score</label>
                                <select name="body_condition_score" class="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500">
                                    <option value="">Select condition...</option>
                                    <option value="1">1 - Very Thin</option>
                                    <option value="2">2 - Thin</option>
                                    <option value="3">3 - Ideal</option>
                                    <option value="4">4 - Fat</option>
                                    <option value="5">5 - Very Fat</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Market Readiness Status</label>
                                <select name="market_readiness_status" class="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500">
                                    <option value="">Select status...</option>
                                    <option value="not_ready">Not Ready Yet</option>
                                    <option value="approaching">Approaching Ready</option>
                                    <option value="nearly_ready">Nearly Ready</option>
                                    <option value="ready_next_week">Ready Next Week</option>
                                    <option value="ready_now">Ready Now</option>
                                </select>
                            </div>
                        </div>

                        <div class="mt-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">General Notes</label>
                            <textarea name="notes" rows="3"
                                      class="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500"
                                      placeholder="Any notes about the weighing, pig condition, market timing, health observations..."></textarea>
                        </div>
                    </div>

                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeWeightModal()"
                                class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                            Cancel
                        </button>
                        <button type="submit"
                                class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                            Update Weight & Assessment
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openWeightModal(pigId, pigCode) {
            document.getElementById('weightForm').action = `/finishers/${pigId}/update-weight`;
            document.getElementById('weightInfo').innerHTML = `
                <div class="text-sm">
                    <div class="font-medium text-gray-900 mb-2">Near Market Ready: ${pigCode}</div>
                    <div class="text-gray-600">Update weight for this near market ready finisher (80-100kg range)</div>
                    <div class="text-yellow-600 mt-1">⚠️ Monitor closely - approaching market weight!</div>
                </div>
            `;
            document.getElementById('weightModal').classList.remove('hidden');
        }

        function closeWeightModal() {
            document.getElementById('weightModal').classList.add('hidden');
            document.getElementById('weightForm').reset();
        }

        // Close modal when clicking outside
        document.getElementById('weightModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeWeightModal();
            }
        });

        // Check for success message and refresh page if weight was updated
        document.addEventListener('DOMContentLoaded', function() {
            // Check if there's a success message (Laravel flash message)
            @if(session('success'))
                // Show success message
                const successMessage = "{{ session('success') }}";

                // Create a temporary success notification
                const notification = document.createElement('div');
                notification.className = 'fixed top-20 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg z-50';
                notification.innerHTML = `
                    <div class="flex items-center">
                        <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        ${successMessage}
                    </div>
                `;
                document.body.appendChild(notification);

                // Remove notification after 3 seconds
                setTimeout(() => {
                    notification.remove();
                }, 3000);

                // Refresh the page after a short delay to show updated data
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            @endif
        });
    </script>
</x-app-layout>
