<x-app-layout>
    <main class="flex-1 p-8">
        <div class="max-w-6xl mx-auto">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h2 class="text-2xl font-bold">Growing Finishers (50-80kg)</h2>
                    <p class="text-gray-600">Pigs in the early finisher stage, building towards market weight</p>
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
                        <div class="p-3 bg-blue-100 rounded-lg">
                            <svg class="h-8 w-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Total Growing</p>
                            <p class="text-3xl font-bold text-blue-600">{{ $stats['total_growing'] }}</p>
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
                        <div class="p-3 bg-yellow-100 rounded-lg">
                            <svg class="h-8 w-8 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Near Market (70-80kg)</p>
                            <p class="text-3xl font-bold text-yellow-600">{{ $stats['expected_market_ready'] }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Progress Information -->
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                <div class="flex">
                    <svg class="h-5 w-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-blue-800">Growing Stage Information</h3>
                        <div class="mt-2 text-sm text-blue-700">
                            <p>• <strong>Weight Range:</strong> 50-80kg (finisher stage entry to near-market)</p>
                            <p>• <strong>Expected Growth:</strong> 0.5-0.8kg per day with proper feeding</p>
                            <p>• <strong>Feed Focus:</strong> High-energy finisher feed for optimal weight gain</p>
                            <p>• <strong>Monitoring:</strong> Weekly weighing recommended to track progress</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Growing Finishers Table -->
            <div class="bg-white shadow-xl rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold">Growing Finishers (50-80kg)</h3>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pig Code</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Gender</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Age</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Weight (kg)</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Progress</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Parents</th>
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
                                                {{ number_format((($pig->current_weight - 50) / 30) * 100, 1) }}% to market range
                                            </div>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if($pig->current_weight)
                                            @php
                                                $progress = (($pig->current_weight - 50) / 30) * 100;
                                                $progressColor = $progress >= 75 ? 'bg-yellow-500' : ($progress >= 50 ? 'bg-blue-500' : 'bg-green-500');
                                            @endphp
                                            <div class="w-full bg-gray-200 rounded-full h-2">
                                                <div class="{{ $progressColor }} h-2 rounded-full" style="width: {{ min($progress, 100) }}%"></div>
                                            </div>
                                            <div class="text-xs text-gray-500 mt-1">
                                                @if($progress >= 75)
                                                    Near market ready
                                                @elseif($progress >= 50)
                                                    Good progress
                                                @else
                                                    Early stage
                                                @endif
                                            </div>
                                        @else
                                            <span class="text-gray-400">No weight data</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <div>Mother: {{ $pig->mother ? $pig->mother->code : 'N/A' }}</div>
                                        <div>Father: {{ $pig->father ? $pig->father->code : 'N/A' }}</div>
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
                                        No growing finishers found. Growing finishers are pigs weighing between 50-80kg.
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
                <h3 class="text-lg font-medium text-gray-900 mb-4">Update Growing Finisher Weight & Weekly Tracking</h3>
                <form id="weightForm" method="POST">
                    @csrf
                    <div id="weightInfo" class="bg-orange-50 p-4 rounded-lg mb-4">
                        <!-- Weight info will be populated by JavaScript -->
                    </div>

                    <!-- Current Weight Section -->
                    <div class="bg-white border border-gray-200 rounded-lg p-4 mb-4">
                        <h4 class="text-md font-medium text-gray-900 mb-3">📏 Current Weight</h4>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">New Weight (kg)</label>
                                <input type="number" name="weight" step="0.1" min="50" max="300" required
                                       class="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500">
                                <p class="text-xs text-gray-500 mt-1">Growing finishers typically weigh 50-80kg</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Weigh Date</label>
                                <input type="date" name="weigh_date" required
                                       class="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500"
                                       value="{{ date('Y-m-d') }}">
                            </div>
                        </div>

                        <div class="mt-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Growth Week</label>
                            <select name="target_week" required class="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500">
                                <option value="">Select growth week...</option>
                                @for($week = 10; $week <= 26; $week++)
                                    <option value="{{ $week }}">Week {{ $week }} ({{ 10 + ($week-10)*2 }}-{{ 12 + ($week-10)*2 }}kg expected)</option>
                                @endfor
                            </select>
                            <p class="text-xs text-gray-500 mt-1">Which week of the growing phase does this weight represent?</p>
                        </div>
                    </div>

                    <!-- Weekly Weight Tracking Section -->
                    <!-- <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
                        <h4 class="text-md font-medium text-gray-900 mb-3">📊 Weekly Weight Progression (Optional)</h4>
                        <p class="text-sm text-gray-600 mb-4">Track weekly weight gains to monitor growth performance</p>

                        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Week -3 (kg)</label>
                                <input type="number" name="week_minus_3_weight" step="0.1" min="0" max="300"
                                       class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                       placeholder="3 weeks ago">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Week -2 (kg)</label>
                                <input type="number" name="week_minus_2_weight" step="0.1" min="0" max="300"
                                       class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                       placeholder="2 weeks ago">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Week -1 (kg)</label>
                                <input type="number" name="week_minus_1_weight" step="0.1" min="0" max="300"
                                       class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                       placeholder="Last week">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Target Next Week (kg)</label>
                                <input type="number" name="target_next_week" step="0.1" min="0" max="300"
                                       class="w-full border-gray-300 rounded-md shadow-sm focus:ring-purple-500 focus:border-purple-500"
                                       placeholder="Growth target">
                            </div>
                        </div>

                        <div class="mt-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Weekly Growth Analysis</label>
                            <textarea name="weekly_notes" rows="2"
                                      class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                      placeholder="Notes about growth rate, feed conversion, any growth concerns or improvements..."></textarea>
                        </div>
                    </div> -->

                    <!-- Feed & Performance Section -->
                    <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-4">
                        <h4 class="text-md font-medium text-gray-900 mb-3">🌾 Feed & Performance</h4>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Daily Feed Intake (kg)</label>
                                <input type="number" name="daily_feed_intake" step="0.1" min="0" max="10"
                                       class="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500"
                                       placeholder="Optional">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Feed Conversion Ratio</label>
                                <input type="number" name="feed_conversion_ratio" step="0.1" min="0" max="10"
                                       class="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500"
                                       placeholder="Optional">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Average Daily Gain (kg)</label>
                                <input type="number" name="average_daily_gain" step="0.01" min="0" max="2"
                                       class="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500"
                                       placeholder="Optional">
                            </div>
                        </div>
                    </div>

                    <!-- Health & Condition Section -->
                    <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 mb-4">
                        <h4 class="text-md font-medium text-gray-900 mb-3">🏥 Health & Market Readiness</h4>

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
                                <label class="block text-sm font-medium text-gray-700 mb-2">Market Readiness</label>
                                <select name="market_readiness" class="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500">
                                    <option value="">Select readiness...</option>
                                    <option value="not_ready">Not Ready (< 80kg)</option>
                                    <option value="approaching">Approaching (80-90kg)</option>
                                    <option value="ready">Ready (90-110kg)</option>
                                    <option value="overweight">Overweight (> 110kg)</option>
                                </select>
                            </div>
                        </div>

                        <div class="mt-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">General Notes</label>
                            <textarea name="notes" rows="3"
                                      class="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500"
                                      placeholder="Any notes about the weighing, pig condition, behavior, market potential..."></textarea>
                        </div>
                    </div>

                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeWeightModal()"
                                class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                            Cancel
                        </button>
                        <button type="submit"
                                class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                            Update Weight & Growth Data
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
                    <div class="font-medium text-gray-900 mb-2">Growing Finisher: ${pigCode}</div>
                    <div class="text-gray-600">Update weight for this growing finisher (50-80kg range)</div>
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
