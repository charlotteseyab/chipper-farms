<x-app-layout>

<div class="container mx-auto px-4 py-2">
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Finished Weaning Process</h1>
            <p class="text-gray-600 mt-2">Pigs that have successfully completed the full weaning journey</p>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
        <div class="bg-white p-4 rounded-lg shadow border border-gray-200">
            <div class="flex items-center">
                <div class="p-2 bg-green-100 rounded-lg">
                    <svg class="h-5 w-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-xs font-medium text-gray-600">Total Completed</p>
                    <p class="text-xl font-semibold text-gray-900">{{ $stats['total_completed'] }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white p-4 rounded-lg shadow border border-gray-200">
            <div class="flex items-center">
                <div class="p-2 bg-blue-100 rounded-lg">
                    <svg class="h-5 w-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3a4 4 0 118 0v4m-4 8a4 4 0 11-8 0v-1a4 4 0 014-4h4a4 4 0 014 4v1a4 4 0 11-8 0z"/>
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-xs font-medium text-gray-600">This Month</p>
                    <p class="text-xl font-semibold text-gray-900">{{ $stats['completed_this_month'] }}</p>
                </div>
            </div>
        </div>

        <!-- <div class="bg-white p-4 rounded-lg shadow border border-gray-200">
            <div class="flex items-center">
                <div class="p-2 bg-purple-100 rounded-lg">
                    <svg class="h-5 w-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-xs font-medium text-gray-600">To Breeding</p>
                    <p class="text-xl font-semibold text-gray-900">{{ $stats['breeding_destination'] }}</p>
                </div>
            </div>
        </div> -->

        <div class="bg-white p-4 rounded-lg shadow border border-gray-200">
            <div class="flex items-center">
                <div class="p-2 bg-indigo-100 rounded-lg">
                    <svg class="h-5 w-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-xs font-medium text-gray-600">Avg Maturity Age</p>
                    <p class="text-xl font-semibold text-gray-900">
                        @if($stats['average_maturity_age'])
                            {{ number_format($stats['average_maturity_age'], 1) }}d
                        @else
                            N/A
                        @endif
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Completed Pigs Table -->
    <div class="bg-white rounded-lg shadow-sm border">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-xl font-semibold text-gray-900">Completed Pigs ({{ $completedPigs->total() }})</h2>
            <p class="text-sm text-gray-600 mt-1">Pigs that have successfully completed the full weaning-to-maturity journey</p>
        </div>

        @if($completedPigs->count() > 0)
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pig</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Completion Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Age at Maturity</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Final Weight</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Health Status</th>
                            <!-- <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Destination</th> -->
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($completedPigs as $completion)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10">
                                            <div class="h-10 w-10 rounded-full bg-green-100 flex items-center justify-center">
                                                <span class="text-sm font-medium text-green-700">
                                                    {{ $completion->pig->gender === 'M' ? '🐗' : '🐷' }}
                                                </span>
                                            </div>
                                        </div>
                                        <div class="ml-4">
                                                <div class="text-sm text-gray-500">{{ $completion->pig->ear_tag_number }}</div>
                                            <div class="text-sm font-medium text-gray-900">{{ $completion->pig->code }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ $completion->maturity_completion_date->format('M d, Y') }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ $completion->age_at_maturity_days }} days
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    @if($completion->final_weight)
                                        {{ $completion->final_weight }} kg
                                    @else
                                        <span class="text-gray-400">Not recorded</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @php
                                        $healthBadge = match($completion->health_status_at_maturity) {
                                            'excellent' => 'bg-green-100 text-green-800',
                                            'good' => 'bg-blue-100 text-blue-800',
                                            'fair' => 'bg-yellow-100 text-yellow-800',
                                            'poor' => 'bg-red-100 text-red-800',
                                            default => 'bg-gray-100 text-gray-800'
                                        };
                                    @endphp
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $healthBadge }}">
                                        {{ ucfirst($completion->health_status_at_maturity) }}
                                    </span>
                                </td>
                                <!-- <td class="px-6 py-4 whitespace-nowrap">
                                    @php
                                        $destinationBadge = match($completion->next_destination) {
                                            'breeding' => 'bg-purple-100 text-purple-800',
                                            'market' => 'bg-green-100 text-green-800',
                                            'monitoring' => 'bg-yellow-100 text-yellow-800',
                                            'other' => 'bg-gray-100 text-gray-800',
                                            default => 'bg-gray-100 text-gray-800'
                                        };
                                    @endphp
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $destinationBadge }}">
                                        {{ ucfirst($completion->next_destination) }}
                                    </span>
                                </td> -->
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="relative inline-block text-left" x-data="{ open: false, place: 'down', toggle(){ this.open = !this.open; if(!this.open){ return; } this.$nextTick(() => { const menu = this.$refs.menu; const trigger = this.$refs.trigger; if(!menu || !trigger) return; const mr = menu.getBoundingClientRect(); const tr = trigger.getBoundingClientRect(); const below = window.innerHeight - tr.bottom; const above = tr.top; this.place = (below < mr.height + 16 && above > mr.height) ? 'up' : 'down'; }); } }">
                                        <button x-ref="trigger" @click="toggle()" class="flex items-center text-gray-600 hover:text-gray-900">
                                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"/>
                                            </svg>
                                        </button>
                                        <div x-ref="menu" x-show="open" @click.away="open = false" x-transition :class="place === 'down' ? 'origin-top-right mt-2 top-full' : 'origin-bottom-right mb-2 bottom-full'" class="absolute right-0 w-48 bg-white rounded-md shadow-lg z-50 border">
                                            <div class="py-1">
                                                <button onclick="openWeightModal({{ $completion->pig->id }}, '{{ $completion->pig->ear_tag_number }}')" class="flex items-center w-full px-4 py-2 text-sm text-blue-600 hover:bg-blue-50">
                                                    <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/>
                                                    </svg>
                                                    Update Weight
                                                </button>
                                                <a href="{{ route('pigs.show', $completion->pig->id) }}" class="flex items-center px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">
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
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="px-6 py-4 border-t border-gray-200">
                <x-simple-pagination :paginator="$completedPigs" />
            </div>
        @else
            <div class="px-6 py-12 text-center">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">No completed pigs</h3>
                <p class="mt-1 text-sm text-gray-500">Completed pigs will appear here once they finish the full weaning-to-maturity journey.</p>
            </div>
        @endif
    </div>
</div>

<!-- Weight Update Modal -->
<div id="weightModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
    <div class="relative top-10 mx-auto p-5 border w-full max-w-2xl shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Update Weaned Piglet Weight & Weekly Tracking</h3>
            <form id="weightForm" method="POST">
                @csrf
                <div id="weightInfo" class="bg-green-50 p-4 rounded-lg mb-4">
                    <!-- Weight info will be populated by JavaScript -->
                </div>

                <!-- Current Weight Section -->
                <div class="bg-white border border-gray-200 rounded-lg p-4 mb-4">
                    <h4 class="text-md font-medium text-gray-900 mb-3">📏 Current Weight</h4>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Current Weight (kg)</label>
                            <input type="number" name="weight" step="0.1" min="0" max="100" required
                                   class="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500">
                            <p class="text-xs text-gray-500 mt-1">Weaned piglets typically weigh 7-25kg</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Weigh Date</label>
                            <input type="date" name="weigh_date" required
                                   class="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500"
                                   value="{{ date('Y-m-d') }}">
                        </div>
                    </div>

                    <div class="mt-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Target Week</label>
                        <select name="target_week" required class="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500">
                            <option value="" disabled selected>-- Select target week --</option>
                            @for($week = 1; $week <= 52; $week++)
                                <option value="{{ $week }}">Week {{ $week }}</option>
                            @endfor
                        </select>
                        <p class="text-xs text-gray-500 mt-1">Which week of growth does this weight represent?</p>
                    </div>
                </div>

                <!-- Weekly Weight Tracking Section -->
                <!-- <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
                    <h4 class="text-md font-medium text-gray-900 mb-3">📊 Weekly Weight History (Optional)</h4>
                    <p class="text-sm text-gray-600 mb-4">Record previous weekly weights to build growth history</p>

                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Week 1 (kg)</label>
                            <input type="number" name="week_1_weight" step="0.1" min="0" max="100"
                                   class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                   placeholder="Optional">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Week 2 (kg)</label>
                            <input type="number" name="week_2_weight" step="0.1" min="0" max="100"
                                   class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                   placeholder="Optional">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Week 3 (kg)</label>
                            <input type="number" name="week_3_weight" step="0.1" min="0" max="100"
                                   class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                   placeholder="Optional">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Week 4 (kg)</label>
                            <input type="number" name="week_4_weight" step="0.1" min="0" max="100"
                                   class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                   placeholder="Optional">
                        </div>
                    </div>

                    <div class="mt-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Weekly Growth Notes</label>
                        <textarea name="weekly_notes" rows="2"
                                  class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                  placeholder="Notes about weekly growth patterns, feeding changes, health observations..."></textarea>
                    </div>
                </div> -->

                <!-- Health & Condition Section -->
                <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 mb-4">
                    <h4 class="text-md font-medium text-gray-900 mb-3">🏥 Health & Condition</h4>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Body Condition Score</label>
                            <select name="body_condition_score" class="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500">
                                <option value="" disabled selected>-- Select condition --</option>
                                <option value="1">1 - Very Thin</option>
                                <option value="2">2 - Thin</option>
                                <option value="3">3 - Ideal</option>
                                <option value="4">4 - Fat</option>
                                <option value="5">5 - Very Fat</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Health Status</label>
                            <select name="health_status" class="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500">
                                <option value="" disabled selected>-- Select status --</option>
                                <option value="excellent">Excellent</option>
                                <option value="good">Good</option>
                                <option value="fair">Fair</option>
                                <option value="poor">Poor</option>
                            </select>
                        </div>
                    </div>

                    <div class="mt-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">General Notes</label>
                        <textarea name="notes" rows="3"
                                  class="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500"
                                  placeholder="Any notes about the weighing, piglet condition, behavior, etc..."></textarea>
                    </div>
                </div>

                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeWeightModal()"
                            class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                        Cancel
                    </button>
                    <button type="submit"
                            class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                        Update Weight & Weekly Data
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Weight Modal Functions
function openWeightModal(pigId, pigEarTag) {
    document.getElementById('weightForm').action = `/weaning/${pigId}/update-weight`;
    document.getElementById('weightInfo').innerHTML = `
        <div class="text-sm">
            <div class="font-medium text-gray-900 mb-2">Weaned Piglet: ${pigEarTag}</div>
            <div class="text-gray-600">Update the current weight for this successfully weaned piglet</div>
            <div class="text-green-600 mt-1">✅ Monitor post-weaning growth and development</div>
        </div>
    `;
    document.getElementById('weightModal').classList.remove('hidden');
}

function closeWeightModal() {
    document.getElementById('weightModal').classList.add('hidden');
    document.getElementById('weightForm').reset();
}

// Close weight modal when clicking outside
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
