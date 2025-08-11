<x-app-layout>
    <main class="flex-1 p-8">
        <div class="max-w-6xl mx-auto">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-3xl font-bold text-gray-800">📋 Manage Matings</h2>
                <a href="{{ route('mating.index') }}" class="px-4 py-2 bg-pink-600 text-white rounded-lg font-semibold hover:bg-pink-700">
                    Plan New Mating
                </a>
            </div>

            <!-- Stats Cards -->
            <div class="space-y-6 mb-8">
                <!-- Primary Mating Status -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="bg-white p-4 rounded-lg shadow-sm border">
                        <div class="flex items-center">
                            <div class="p-2 bg-blue-100 rounded-lg">
                                <svg class="h-5 w-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v4"/>
                                </svg>
                            </div>
                            <div class="ml-3">
                                <p class="text-xs font-medium text-gray-600">Total Matings</p>
                                <p class="text-xl font-semibold text-gray-900">{{ $stats['total'] }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white p-4 rounded-lg shadow-sm border">
                        <div class="flex items-center">
                            <div class="p-2 bg-yellow-100 rounded-lg">
                                <svg class="h-5 w-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                            </div>
                            <div class="ml-3">
                                <p class="text-xs font-medium text-gray-600">Planned</p>
                                <p class="text-xl font-semibold text-gray-900">{{ $stats['planned'] }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white p-4 rounded-lg shadow-sm border">
                        <div class="flex items-center">
                            <div class="p-2 bg-green-100 rounded-lg">
                                <svg class="h-5 w-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <div class="ml-3">
                                <p class="text-xs font-medium text-gray-600">Completed</p>
                                <p class="text-xl font-semibold text-gray-900">{{ $stats['completed'] }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white p-4 rounded-lg shadow-sm border">
                        <div class="flex items-center">
                            <div class="p-2 bg-red-100 rounded-lg">
                                <svg class="h-5 w-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <div class="ml-3">
                                <p class="text-xs font-medium text-gray-600">Overdue</p>
                                <p class="text-xl font-semibold text-gray-900">{{ $stats['overdue'] }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pregnancy & Schedule Status -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="bg-white p-4 rounded-lg shadow-sm border group relative">
                        <div class="flex items-center">
                            <div class="p-2 bg-orange-100 rounded-lg">
                                <svg class="h-5 w-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <div class="ml-3 flex-1">
                                <p class="text-xs font-medium text-gray-600">Pregnancy Checks Due</p>
                                <p class="text-xl font-semibold text-gray-900">{{ $stats['pregnancy_checks_due'] }}</p>
                                @if($stats['pregnancy_checks_overdue'] > 0)
                                    <p class="text-xs text-red-600">{{ $stats['pregnancy_checks_overdue'] }} overdue</p>
                                @else
                                    <p class="text-xs text-gray-500">{{ config('breeding.cycle.pregnancy_check_days') }}+ days after mating</p>
                                @endif
                            </div>
                            <div class="opacity-0 group-hover:opacity-100 transition-opacity">
                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                        </div>
                        <!-- Tooltip -->
                        <div class="absolute left-0 top-full mt-2 w-64 p-3 bg-gray-900 text-white text-xs rounded-lg opacity-0 group-hover:opacity-100 transition-opacity z-10 pointer-events-none">
                            <strong>Pregnancy Checks Due:</strong><br>
                            • Completed matings from {{ config('breeding.cycle.pregnancy_check_days') }}+ days ago<br>
                            • Still awaiting pregnancy check<br>
                            • Overdue after {{ config('breeding.cycle.pregnancy_check_days') + 7 }} days
                        </div>
                    </div>

                    <div class="bg-white p-4 rounded-lg shadow-sm border group relative">
                        <div class="flex items-center">
                            <div class="p-2 bg-pink-100 rounded-lg">
                                <svg class="h-5 w-5 text-pink-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                                </svg>
                            </div>
                            <div class="ml-3 flex-1">
                                <p class="text-xs font-medium text-gray-600">Confirmed Pregnant</p>
                                <p class="text-xl font-semibold text-gray-900">{{ $stats['pregnant'] }}</p>
                                @if($stats['not_pregnant'] > 0)
                                    <p class="text-xs text-gray-600">{{ $stats['not_pregnant'] }} not pregnant</p>
                                @else
                                    <p class="text-xs text-gray-500">Pregnancy confirmed</p>
                                @endif
                            </div>
                            <div class="opacity-0 group-hover:opacity-100 transition-opacity">
                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                        </div>
                        <!-- Tooltip -->
                        <div class="absolute left-0 top-full mt-2 w-64 p-3 bg-gray-900 text-white text-xs rounded-lg opacity-0 group-hover:opacity-100 transition-opacity z-10 pointer-events-none">
                            <strong>Confirmed Pregnant:</strong><br>
                            • Matings with pregnancy_status = 'pregnant'<br>
                            • Pregnancy check completed and positive<br>
                            • Expected farrowing date calculated
                        </div>
                    </div>

                    <div class="bg-white p-4 rounded-lg shadow-sm border">
                        <div class="flex items-center">
                            <div class="p-2 bg-purple-100 rounded-lg">
                                <svg class="h-5 w-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                                </svg>
                            </div>
                            <div class="ml-3">
                                <p class="text-xs font-medium text-gray-600">Upcoming Matings</p>
                                <p class="text-xl font-semibold text-gray-900">{{ $stats['upcoming'] }}</p>
                                <p class="text-xs text-gray-600">Next 7 days</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Matings Table -->
            <div class="bg-white shadow-lg rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-800">Planned Matings</h3>
                </div>

                @if($matings->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Breeding Pair</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Mating Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pregnancy</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Notes</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($matings as $mating)
                                    <tr class="hover:bg-gray-50 {{ $mating->is_overdue ? 'bg-red-50' : '' }}">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div>
                                                    <div class="text-sm font-medium text-gray-900">
                                                        {{ $mating->male->code }} × {{ $mating->female->code }}
                                                    </div>
                                                    <div class="text-sm text-gray-500">
                                                        {{ $mating->male->ear_tag_number }} × {{ $mating->female->ear_tag_number }}
                                                    </div>
                                                    @if($mating->status === 'completed')
                                                        @if($mating->pregnancy_status === 'not_pregnant')
                                                            <div class="text-xs text-green-600 mt-1">
                                                                ✅ Both pigs now available for breeding
                                                            </div>
                                                        @elseif($mating->pregnancy_status === 'pending')
                                                            <div class="text-xs text-orange-600 mt-1">
                                                                ♂️ Male available, ♀️ Female awaiting pregnancy check
                                                            </div>
                                                        @elseif($mating->pregnancy_status === 'pregnant')
                                                            <div class="text-xs text-orange-600 mt-1">
                                                                ♂️ Male available, ♀️ Female pregnant
                                                            </div>
                                                        @elseif($mating->pregnancy_status === 'uncertain')
                                                            <div class="text-xs text-orange-600 mt-1">
                                                                ♂️ Male available, ♀️ Female pregnancy uncertain
                                                            </div>
                                                        @endif
                                                    @elseif($mating->status === 'planned')
                                                        <div class="text-xs text-blue-600 mt-1">
                                                            🔒 Both pigs reserved for planned mating
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            @if($mating->status === 'completed' && $mating->actual_date)
                                                <div class="text-sm text-gray-900">{{ $mating->actual_date->format('M d, Y') }}</div>
                                                <div class="text-xs text-gray-500">{{ $mating->days_since_mating }} days ago</div>
                                            @else
                                                <div class="text-sm text-gray-900">{{ $mating->planned_date->format('M d, Y') }}</div>
                                                @if($mating->is_overdue)
                                                    <div class="text-xs text-red-600">{{ abs($mating->days_until_planned) }} days overdue</div>
                                                @elseif($mating->days_until_planned > 0)
                                                    <div class="text-xs text-gray-500">{{ $mating->days_until_planned }} days to go</div>
                                                @elseif($mating->days_until_planned == 0)
                                                    <div class="text-xs text-blue-600">Today</div>
                                                @endif
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $mating->status_badge }}">
                                                {{ $mating->status_icon }} {{ ucfirst($mating->status) }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            @if($mating->status === 'completed')
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $mating->pregnancy_status_badge }}">
                                                    {{ $mating->pregnancy_status_icon }} {{ ucfirst(str_replace('_', ' ', $mating->pregnancy_status)) }}
                                                </span>
                                                @if($mating->pregnancy_check_due)
                                                    <div class="text-xs text-orange-600 mt-1">Check due</div>
                                                @elseif($mating->pregnancy_check_overdue)
                                                    <div class="text-xs text-red-600 mt-1">Check overdue!</div>
                                                @endif
                                            @else
                                                <span class="text-xs text-gray-400">Not applicable</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="text-sm text-gray-900 max-w-xs truncate">
                                                {{ $mating->notes ?: 'No notes' }}
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            @if($mating->status === 'planned')
                                                <button onclick="openCompleteMatingModal({{ $mating->id }}, '{{ $mating->male->code }}', '{{ $mating->female->code }}', '{{ $mating->planned_date->format('Y-m-d') }}')"
                                                        class="text-green-600 hover:text-green-900 mr-3">
                                                    Complete
                                                </button>
                                                <button class="text-red-600 hover:text-red-900">Cancel</button>
                                            @elseif($mating->status === 'completed' && $mating->pregnancy_status === 'pending')
                                                <button onclick="openPregnancyCheckModal({{ $mating->id }}, '{{ $mating->male->code }}', '{{ $mating->female->code }}', '{{ $mating->actual_date->format('M d, Y') }}', {{ $mating->days_since_mating }})"
                                                        class="text-blue-600 hover:text-blue-900 mr-3">
                                                    Record Check
                                                </button>
                                            @elseif($mating->pregnancy_status !== 'pending')
                                                <span class="text-gray-400">Check completed</span>
                                            @else
                                                <span class="text-gray-400">No actions</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="px-6 py-4 border-t border-gray-200">
                        {{ $matings->links() }}
                    </div>
                @else
                    <div class="px-6 py-12 text-center">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900">No matings planned</h3>
                        <p class="mt-1 text-sm text-gray-500">Get started by planning your first mating.</p>
                        <div class="mt-6">
                            <a href="{{ route('mating.index') }}" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-pink-600 hover:bg-pink-700">
                                Plan First Mating
                            </a>
                        </div>
                    </div>
                @endif
            </div>

            <!-- Current Functionality -->
            <div class="mt-8 bg-white shadow-lg rounded-lg p-6">
                <h3 class="text-xl font-semibold mb-4 text-gray-800">What You Can Do Now</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="flex items-start gap-4">
                        <div class="flex-shrink-0">
                            <div class="w-10 h-10 bg-pink-100 rounded-lg flex items-center justify-center">
                                <svg class="h-6 w-6 text-pink-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                </svg>
                            </div>
                        </div>
                        <div>
                            <h4 class="font-semibold text-gray-800 mb-2">Plan Matings</h4>
                            <p class="text-gray-600 text-sm mb-3">Use the mating planner to select breeding pairs and check for inbreeding risks with real-time compatibility analysis.</p>
                            <a href="{{ route('mating.index') }}" class="text-pink-600 hover:text-pink-700 font-medium text-sm">
                                Go to Mating Planner →
                            </a>
                        </div>
                    </div>
                    
                    <div class="flex items-start gap-4">
                        <div class="flex-shrink-0">
                            <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                                <svg class="h-6 w-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                        </div>
                        <div>
                            <h4 class="font-semibold text-gray-800 mb-2">Compatibility Checking</h4>
                            <p class="text-gray-600 text-sm mb-3">Advanced inbreeding detection system that analyzes relationships and provides breeding recommendations.</p>
                            <span class="text-green-600 font-medium text-sm">
                                ✓ Already Available
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Development Roadmap -->
            <!-- <div class="mt-8 bg-gray-50 rounded-lg p-6">
                <h3 class="text-lg font-semibold mb-4 text-gray-800">🚀 Development Roadmap</h3>
                <div class="space-y-3">
                    <div class="flex items-center gap-3">
                        <div class="w-4 h-4 bg-yellow-400 rounded-full"></div>
                        <span class="text-gray-700">Create planned matings database table and model</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="w-4 h-4 bg-yellow-400 rounded-full"></div>
                        <span class="text-gray-700">Build mating management interface with CRUD operations</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="w-4 h-4 bg-yellow-400 rounded-full"></div>
                        <span class="text-gray-700">Add mating status tracking (planned, completed, cancelled)</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="w-4 h-4 bg-yellow-400 rounded-full"></div>
                        <span class="text-gray-700">Implement breeding outcome recording and reporting</span>
                    </div>
                </div>
            </div> -->
        </div>
    </main>

    <!-- Complete Mating Modal -->
    <div id="completeMatingModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Complete Mating</h3>
                    <button onclick="closeCompleteMatingModal()" class="text-gray-400 hover:text-gray-600">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <div id="completeMatingInfo" class="bg-gray-50 p-4 rounded-lg mb-4">
                    <!-- Mating info will be populated by JavaScript -->
                </div>

                <form id="completeMatingForm" method="POST">
                    @csrf
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Actual Mating Date</label>
                            <input type="date" name="actual_date" required
                                   max="{{ now()->toDateString() }}"
                                   class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-green-500 focus:border-green-500">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Notes (optional)</label>
                            <textarea name="outcome_notes" rows="3"
                                      placeholder="Any observations about the mating process..."
                                      class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-green-500 focus:border-green-500"></textarea>
                        </div>

                        <div class="bg-blue-50 p-3 rounded-lg">
                            <div class="text-sm text-blue-800">
                                <strong>Next Step:</strong> Pregnancy check will be due 14 days after the actual mating date.
                            </div>
                        </div>

                        <div class="flex justify-end space-x-3 pt-4">
                            <button type="button" onclick="closeCompleteMatingModal()"
                                    class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">
                                Cancel
                            </button>
                            <button type="submit"
                                    class="px-4 py-2 bg-green-600 text-white rounded-md text-sm font-medium hover:bg-green-700">
                                Complete Mating
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Pregnancy Check Modal -->
    <div id="pregnancyCheckModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Record Pregnancy Check</h3>
                    <button onclick="closePregnancyCheckModal()" class="text-gray-400 hover:text-gray-600">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <div id="matingInfo" class="bg-gray-50 p-4 rounded-lg mb-4">
                    <!-- Mating info will be populated by JavaScript -->
                </div>

                <form id="pregnancyCheckForm" method="POST">
                    @csrf
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Check Date</label>
                            <input type="date" name="pregnancy_check_date" value="{{ now()->toDateString() }}"
                                   max="{{ now()->toDateString() }}" required
                                   class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-pink-500 focus:border-pink-500">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Check Method</label>
                            <select name="pregnancy_check_method" required class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-pink-500 focus:border-pink-500">
                                <option value="">-- Select Method --</option>
                                <option value="physical_exam">Physical Examination</option>
                                <option value="blood_test">Blood Test</option>
                                <option value="ultrasound">Ultrasound</option>
                                <option value="behavioral_signs">Behavioral Signs</option>
                                <option value="other">Other</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Result</label>
                            <select name="pregnancy_status" required class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-pink-500 focus:border-pink-500">
                                <option value="">-- Select Result --</option>
                                <option value="pregnant">🤰 Pregnant</option>
                                <option value="not_pregnant">❌ Not Pregnant</option>
                                <option value="uncertain">❓ Uncertain</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Confidence Level</label>
                            <select name="pregnancy_confidence" required class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-pink-500 focus:border-pink-500">
                                <option value="">-- Select Confidence --</option>
                                <option value="high">High - Very confident in result</option>
                                <option value="medium">Medium - Reasonably confident</option>
                                <option value="low">Low - May need follow-up</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Notes (optional)</label>
                            <textarea name="pregnancy_check_notes" rows="3"
                                      placeholder="Any additional observations or notes..."
                                      class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-pink-500 focus:border-pink-500"></textarea>
                        </div>

                        <div class="flex justify-end space-x-3 pt-4">
                            <button type="button" onclick="closePregnancyCheckModal()"
                                    class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">
                                Cancel
                            </button>
                            <button type="submit"
                                    class="px-4 py-2 bg-pink-600 text-white rounded-md text-sm font-medium hover:bg-pink-700">
                                Record Check
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openCompleteMatingModal(matingId, maleCode, femaleCode, plannedDate) {
            // Update form action
            document.getElementById('completeMatingForm').action = `/mating/${matingId}/complete`;

            // Set default actual date to planned date
            document.querySelector('input[name="actual_date"]').value = plannedDate;

            // Update mating info
            document.getElementById('completeMatingInfo').innerHTML = `
                <div class="text-sm">
                    <div class="font-medium text-gray-900 mb-2">Breeding Pair: ${maleCode} × ${femaleCode}</div>
                    <div class="text-gray-600">Planned Date: ${new Date(plannedDate).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}</div>
                    <div class="text-green-600 mt-2">✓ Ready to mark as completed</div>
                </div>
            `;

            // Show modal
            document.getElementById('completeMatingModal').classList.remove('hidden');
        }

        function closeCompleteMatingModal() {
            document.getElementById('completeMatingModal').classList.add('hidden');
            document.getElementById('completeMatingForm').reset();
        }

        function openPregnancyCheckModal(matingId, maleCode, femaleCode, matingDate, daysSince) {
            // Update form action
            document.getElementById('pregnancyCheckForm').action = `/mating/${matingId}/pregnancy-check`;

            // Update mating info
            document.getElementById('matingInfo').innerHTML = `
                <div class="text-sm">
                    <div class="font-medium text-gray-900 mb-2">Breeding Pair: ${maleCode} × ${femaleCode}</div>
                    <div class="text-gray-600">Mating Date: ${matingDate}</div>
                    <div class="text-gray-600">Days Since Mating: ${daysSince} days</div>
                    <div class="text-blue-600 mt-2">
                        ${daysSince >= 14 ? '✓ Ready for pregnancy check' : `⏳ Check recommended after ${14 - daysSince} more days`}
                    </div>
                </div>
            `;

            // Show modal
            document.getElementById('pregnancyCheckModal').classList.remove('hidden');
        }

        function closePregnancyCheckModal() {
            document.getElementById('pregnancyCheckModal').classList.add('hidden');
            document.getElementById('pregnancyCheckForm').reset();
        }

        // Close modals when clicking outside
        document.getElementById('completeMatingModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeCompleteMatingModal();
            }
        });

        document.getElementById('pregnancyCheckModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closePregnancyCheckModal();
            }
        });
    </script>
</x-app-layout>
