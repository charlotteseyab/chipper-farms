<x-app-layout>
    <main class="flex-1 p-6">
        <div class="max-w-6xl mx-auto bg-gray-50 rounded-lg p-6 shadow-sm">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-bold">Finisher Management</h2>
                <div class="flex gap-3">
                    <a href="{{ route('finishers.export.csv') }}" class="px-4 py-2 bg-green-600 text-white rounded-lg font-semibold hover:bg-green-700">Export CSV</a>
                    <a href="{{ route('finishers.export.pdf') }}" class="px-4 py-2 bg-red-600 text-white rounded-lg font-semibold hover:bg-red-700">Export PDF</a>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
                <div class="bg-white p-4 rounded-lg shadow">
                    <div class="flex items-center">
                        <div class="p-2 bg-blue-100 rounded-lg">
                            <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Total Finishers</p>
                            <p class="text-2xl font-bold text-gray-900">{{ $stats['total_finishers'] }}</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white p-4 rounded-lg shadow">
                    <div class="flex items-center">
                        <div class="p-2 bg-blue-100 rounded-lg">
                            <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Growing (50-80kg)</p>
                            <p class="text-2xl font-bold text-blue-600">{{ $stats['growing'] }}</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white p-4 rounded-lg shadow">
                    <div class="flex items-center">
                        <div class="p-2 bg-yellow-100 rounded-lg">
                            <svg class="h-6 w-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Near Market (80-100kg)</p>
                            <p class="text-2xl font-bold text-yellow-600">{{ $stats['near_market'] }}</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white p-4 rounded-lg shadow">
                    <div class="flex items-center">
                        <div class="p-2 bg-green-100 rounded-lg">
                            <svg class="h-6 w-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Market Ready (100kg+)</p>
                            <p class="text-2xl font-bold text-green-600">{{ $stats['market_ready'] }}</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white p-4 rounded-lg shadow">
                    <div class="flex items-center">
                        <div class="p-2 bg-gray-100 rounded-lg">
                            <svg class="h-6 w-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Avg Weight</p>
                            <p class="text-2xl font-bold text-gray-900">{{ number_format($stats['average_weight'] ?? 0, 1) }}kg</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Navigation -->
            <div class="bg-white rounded-lg shadow mb-6">
                <div class="p-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold">Quick Navigation</h3>
                </div>
                <div class="p-4">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <a href="{{ route('finishers.growing') }}" class="flex items-center p-4 bg-blue-50 rounded-lg hover:bg-blue-100 transition">
                            <svg class="h-8 w-8 text-blue-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                            </svg>
                            <div>
                                <h4 class="font-semibold text-blue-900">Growing Finishers</h4>
                                <p class="text-sm text-blue-700">50-80kg weight range</p>
                                <p class="text-lg font-bold text-blue-600">{{ $stats['growing'] }} pigs</p>
                            </div>
                        </a>

                        <a href="{{ route('finishers.ready') }}" class="flex items-center p-4 bg-yellow-50 rounded-lg hover:bg-yellow-100 transition">
                            <svg class="h-8 w-8 text-yellow-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <div>
                                <h4 class="font-semibold text-yellow-900">Near Market</h4>
                                <p class="text-sm text-yellow-700">80-100kg weight range</p>
                                <p class="text-lg font-bold text-yellow-600">{{ $stats['near_market'] }} pigs</p>
                            </div>
                        </a>

                        <a href="{{ route('finishers.market-ready') }}" class="flex items-center p-4 bg-green-50 rounded-lg hover:bg-green-100 transition">
                            <svg class="h-8 w-8 text-green-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <div>
                                <h4 class="font-semibold text-green-900">Market Ready</h4>
                                <p class="text-sm text-green-700">100kg+ ready for sale</p>
                                <p class="text-lg font-bold text-green-600">{{ $stats['market_ready'] }} pigs</p>
                            </div>
                        </a>
                    </div>
                </div>
            </div>

            <!-- All Finishers Table -->
            <div class="bg-white shadow-xl rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold">All Finishers (50kg+)</h3>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pig Code</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Gender</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Age</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Weight (kg)</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
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
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if($pig->current_weight >= 100)
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                Market Ready
                                            </span>
                                        @elseif($pig->current_weight >= 80)
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                                Near Market
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                Growing
                                            </span>
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
                                                    @if($pig->current_weight >= 100)
                                                        <a href="{{ route('pigs.sale-form', $pig->id) }}" class="flex items-center px-4 py-2 text-sm text-green-600 hover:bg-green-50">
                                                            <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm0 10c-4.41 0-8-1.79-8-4V6c0-2.21 3.59-4 8-4s8 1.79 8 4v8c0 2.21-3.59 4-8 4z"/>
                                                            </svg>
                                                            Mark for Sale
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
                                        No finishers found. Finishers are pigs weighing 50kg or more.
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
    </div>
    </main>

    <!-- Weight Update Modal -->
    <div id="weightModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Update Weight</h3>
                <form id="weightForm" method="POST">
                    @csrf
                    <div id="weightInfo" class="bg-gray-50 p-4 rounded-lg mb-4">
                        <!-- Weight info will be populated by JavaScript -->
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">New Weight (kg)</label>
                        <input type="number" name="weight" step="0.1" min="0" max="300" required
                               class="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500">
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Weigh Date</label>
                        <input type="date" name="weigh_date" required
                               class="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500"
                               value="{{ date('Y-m-d') }}">
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Notes (Optional)</label>
                        <textarea name="notes" rows="3"
                                  class="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500"
                                  placeholder="Any notes about the weighing..."></textarea>
                    </div>

                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeWeightModal()" 
                                class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                            Update Weight
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
                    <div class="font-medium text-gray-900 mb-2">Pig: ${pigCode}</div>
                    <div class="text-gray-600">Update the current weight for this finisher pig</div>
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
