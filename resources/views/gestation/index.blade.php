<x-app-layout>
    <div class="min-h-screen bg-gray-50">
        <!-- Header -->
        <div class="bg-white shadow-sm border-b border-gray-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-3">
                        <div class="p-2 bg-gray-100 rounded-lg">
                            <svg class="h-6 w-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div>
                            <h1 class="text-2xl font-semibold text-gray-900">Gestation Period Management</h1>
                            <p class="text-gray-500 text-sm">Monitor pregnant sows and track farrowing</p>
                        </div>
                    </div>
                    <div class="flex items-center space-x-3">
                        <a href="{{ route('mating.index') }}" class="inline-flex items-center px-3 py-2 bg-gray-600 text-white text-sm font-medium rounded-lg hover:bg-gray-700 transition">
                            <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                            </svg>
                            Plan Mating
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">

            <!-- Statistics Cards -->
            <div class="space-y-6 mb-8">
                <!-- Primary Stats Row -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="bg-white p-4 rounded-lg shadow border border-gray-200">
                        <div class="flex items-center">
                            <div class="p-2 bg-blue-100 rounded-lg">
                                <svg class="h-5 w-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                                </svg>
                            </div>
                            <div class="ml-3">
                                <p class="text-xs font-medium text-gray-600">Total Pregnant</p>
                                <p class="text-xl font-semibold text-gray-900">{{ $stats['total_pregnant'] }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white p-4 rounded-lg shadow border border-gray-200">
                        <div class="flex items-center">
                            <div class="p-2 bg-yellow-100 rounded-lg">
                                <svg class="h-5 w-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <div class="ml-3">
                                <p class="text-xs font-medium text-gray-600">Due This Week</p>
                                <p class="text-xl font-semibold text-gray-900">{{ $stats['due_this_week'] }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white p-4 rounded-lg shadow border border-gray-200">
                        <div class="flex items-center">
                            <div class="p-2 bg-green-100 rounded-lg">
                                <svg class="h-5 w-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                            </div>
                            <div class="ml-3">
                                <p class="text-xs font-medium text-gray-600">Due Next Week</p>
                                <p class="text-xl font-semibold text-gray-900">{{ $stats['due_next_week'] }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white p-4 rounded-lg shadow border border-gray-200">
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
            </div>

            <!-- Pregnant Sows Table -->
            <div class="bg-white shadow rounded-lg border border-gray-200">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">Pregnant Sows</h2>
                    <p class="text-sm text-gray-600 mt-1">Monitor gestation progress and record farrowing</p>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Breeding Pair</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Mating Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Expected Farrowing</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Gestation Days</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($pregnantMatings as $mating)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div>
                                                <div class="text-sm text-gray-500">
                                                    <span class="inline-flex items-center">
                                                        <svg class="w-6 h-6 text-blue-600 mr-2" fill="currentColor" viewBox="0 0 24 24">
                                                            <circle cx="9" cy="15" r="6" stroke="currentColor" stroke-width="2" fill="none"/>
                                                            <path d="M15 9L21 3M21 3h-5M21 3v5" stroke="currentColor" stroke-width="2" fill="none"/>
                                                        </svg>
                                                        <span class="font-medium">{{ $mating->male->ear_tag_number }}</span>
                                                    </span>
                                                    <span class="mx-2 text-gray-400">×</span>
                                                    <span class="inline-flex items-center">
                                                        <svg class="w-6 h-6 text-pink-600 mr-2" fill="currentColor" viewBox="0 0 24 24">
                                                            <circle cx="12" cy="8" r="5" stroke="currentColor" stroke-width="2" fill="none"/>
                                                            <path d="M12 13v8M9 18h6" stroke="currentColor" stroke-width="2" fill="none"/>
                                                        </svg>
                                                        <span class="font-medium">{{ $mating->female->ear_tag_number }}</span>
                                                    </span>
                                                </div>
                                                <!-- <div class="text-sm font-medium text-gray-900">
                                                    <span class="inline-flex items-center">
                                                        <span class="text-blue-600 font-medium">♂</span>
                                                        <span class="ml-1">{{ $mating->male->ear_tag_number }}</span>
                                                    </span>
                                                    <span class="mx-2 text-gray-400">×</span>
                                                    <span class="inline-flex items-center">
                                                        <span class="text-pink-600 font-medium">♀</span>
                                                        <span class="ml-1">{{ $mating->female->code }}</span>
                                                    </span>
                                                </div> -->
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">{{ $mating->actual_date->format('M d, Y') }}</div>
                                        <div class="text-xs text-gray-500">{{ $mating->actual_date->diffForHumans() }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">{{ $mating->expected_farrowing_date->format('M d, Y') }}</div>
                                        @if($mating->pregnancy_status === 'farrowed' && $mating->actual_farrowing_date)
                                            @php $delta = $mating->expected_farrowing_date->diffInDays($mating->actual_farrowing_date, false);
                                            @endphp
                                            <div class="mt-1 space-x-2">
                                                @if($delta < 0)
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">Farrowed early by {{ abs($delta) }} days</span>
                                                @elseif($delta === 0)
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Farrowed on time</span>
                                                @else
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-800">Farrowed late by {{ $delta }} days</span>
                                                @endif
                                            </div>
                                            <div class="text-xs text-gray-500 mt-1">Actual: {{ $mating->actual_farrowing_date->format('M d, Y') }} • Expected: {{ $mating->expected_farrowing_date->format('M d, Y') }}</div>
                                        @else
                                            <div class="text-xs text-gray-500">
                                                @if($mating->is_overdue_for_farrowing)
                                                    {{ abs($mating->days_to_farrowing) }} days overdue
                                                @elseif($mating->days_to_farrowing == 0)
                                                    Due today
                                                @else
                                                    {{ $mating->days_to_farrowing }} days to go
                                                @endif
                                            </div>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $mating->farrowing_status_badge }}">
                                            {{ $mating->pregnancy_status_icon }} {{ $mating->farrowing_status }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ $mating->gestation_days }} days
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <div class="flex items-center justify-center">
                                            <!-- Actions Dropdown -->
                                            <div class="relative inline-block text-left">
                                                <div>
                                                    <button type="button" class="inline-flex justify-center w-full rounded-md border border-gray-300 shadow-sm px-3 py-1.5 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" id="actions-menu-{{ $mating->id }}" onclick="toggleDropdown({{ $mating->id }})">
                                                        Actions
                                                        <svg class="-mr-1 ml-2 h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                                                        </svg>
                                                    </button>
                                                </div>
                                                <div class="origin-top-right absolute right-0 mt-2 w-64 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 hidden z-50" id="dropdown-menu-{{ $mating->id }}">
                                                    <div class="py-1" role="menu">
                                                        <!-- Status Information -->
                                                        <div class="px-4 py-2 border-b border-gray-100">
                                                            @if($mating->pregnancy_status === 'pregnant')
                                                                <div class="flex items-center space-x-2">
                                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">Pregnant</span>
                                                                </div>
                                                            @elseif($mating->pregnancy_status === 'farrowed')
                                                                <div class="space-y-1">
                                                                    <div class="flex items-center space-x-2">
                                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">✓ Farrowed</span>
                                                                    </div>
                                                                    @if($mating->actual_farrowing_date && $mating->expected_farrowing_date)
                                                                        @php $delta = $mating->expected_farrowing_date->diffInDays($mating->actual_farrowing_date, false);
                                                                        @endphp
                                                                        <div class="flex items-center space-x-2">
                                                                            @if($delta < 0)
                                                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">Early by {{ abs($delta) }}d</span>
                                                                            @elseif($delta === 0)
                                                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">On time</span>
                                                                            @else
                                                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-800">Late by {{ $delta }}d</span>
                                                                            @endif
                                                                        </div>
                                                                        <div class="text-xs text-gray-500">
                                                                            {{ $mating->actual_farrowing_date->format('M d, Y') }}
                                                                        </div>
                                                                    @endif
                                                                </div>
                                                            @else
                                                                <div class="flex items-center space-x-2">
                                                                    <span class="text-gray-400 text-sm">Completed</span>
                                                                </div>
                                                            @endif
                                                        </div>
                                                        
                                                        <!-- Action Buttons -->
                                                        @if($mating->pregnancy_status === 'pregnant')
                                                            <button onclick="openFarrowingModal({{ $mating->id }}, '{{ $mating->female->ear_tag_number }}', '{{ $mating->expected_farrowing_date->format('Y-m-d') }}')" class="block w-full text-left px-4 py-2 text-sm text-green-600 hover:bg-green-50" role="menuitem">
                                                                Record Farrowing
                                                            </button>
                                                            <button onclick="openStatusModal({{ $mating->id }}, '{{ $mating->female->ear_tag_number }}')" class="block w-full text-left px-4 py-2 text-sm text-blue-600 hover:bg-blue-50" role="menuitem">
                                                                Update Status
                                                            </button>
                                                        @endif
                                                        <a href="{{ route('gestation.edit', $mating->id) }}" class="block w-full text-left px-4 py-2 text-sm text-purple-600 hover:bg-purple-50" role="menuitem">
                                                            Edit Gestation
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-6 py-12 text-center">
                                        <div class="text-gray-500">
                                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                                            </svg>
                                            <h3 class="mt-2 text-sm font-medium text-gray-900">No pregnant sows</h3>
                                            <p class="mt-1 text-sm text-gray-500">No confirmed pregnancies at this time.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($pregnantMatings->hasPages())
                    <div class="px-6 py-4 border-t border-gray-200">
                        <div class="flex items-center justify-between">
                            <div class="text-sm text-gray-700">
                                Showing {{ $pregnantMatings->firstItem() }} to {{ $pregnantMatings->lastItem() }} of {{ $pregnantMatings->total() }} results
                            </div>
                            <div class="flex items-center space-x-2">
                                <x-simple-pagination :paginator="$pregnantMatings" />
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </main>
    </div>

    <!-- Record Farrowing Modal -->
    <div id="farrowingModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-10 mx-auto p-6 border max-w-4xl shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Record Farrowing</h3>
                    <button onclick="closeFarrowingModal()" class="text-gray-400 hover:text-gray-600">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <div id="farrowingInfo" class="bg-gray-50 p-4 rounded-lg mb-4">
                    <!-- Farrowing info will be populated by JavaScript -->
                </div>

                <!-- Server-side validation errors -->
                @if($errors->any())
                    <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-4">
                        <div class="flex">
                            <svg class="h-5 w-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-red-800">Validation Errors</h3>
                                <div class="mt-2 text-sm text-red-700">
                                    <ul class="list-disc pl-5 space-y-1">
                                        @foreach($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                <form id="farrowingForm" method="POST" onsubmit="return validateFarrowingForm()">
                    @csrf
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <!-- Left Column -->
                        <div class="space-y-4">
                            <h4 class="text-lg font-semibold text-gray-800 border-b pb-2">Basic Information</h4>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Farrowing Date</label>
                                    <input type="date" name="farrowing_date" required
                                           max="{{ now()->toDateString() }}"
                                           class="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500 text-sm">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Time (optional)</label>
                                    <input type="time" name="farrowing_time"
                                           class="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500 text-sm">
                                </div>
                            </div>

                            <div class="bg-gray-50 p-3 rounded-lg">
                                <h5 class="text-sm font-medium text-gray-700 mb-2">Piglet Counts</h5>
                                <p class="text-xs text-gray-500 mb-2">💡 Live + Stillborn must equal Total Born</p>
                                <div class="grid grid-cols-3 gap-2">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 mb-1">Total Born</label>
                                        <input type="number" name="total_piglets_born" min="0" max="20" required
                                               class="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500 text-sm"
                                               onchange="updateTotals()" id="totalPigletsInput">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 mb-1">Live</label>
                                        <input type="number" name="live_births" min="0" max="20" required
                                               class="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500 text-sm"
                                               onchange="autoCalculateTotal(); updateTotals();" id="liveBirthsInput">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 mb-1">Stillborn</label>
                                        <input type="number" name="stillbirths" min="0" max="20" required
                                               class="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500 text-sm"
                                               onchange="autoCalculateTotal(); updateTotals();" id="stillbirthsInput">
                                    </div>
                                </div>
                            </div>

                            <div class="bg-blue-50 p-3 rounded-lg">
                                <h5 class="text-sm font-medium text-blue-800 mb-2">Gender Distribution</h5>
                                <p class="text-xs text-blue-600 mb-2">💡 Optional: Males + Females must equal Total Born if provided</p>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 mb-1">Males</label>
                                        <input type="number" name="number_of_males" min="0" max="20"
                                               class="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500 text-sm"
                                               onchange="updateTotals()">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 mb-1">Females</label>
                                        <input type="number" name="number_of_females" min="0" max="20"
                                               class="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500 text-sm"
                                               onchange="updateTotals()">
                                    </div>
                                </div>
                                <div id="genderValidation" class="mt-2 text-xs"></div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Average Birth Weight (kg)</label>
                                <input type="number" name="average_birth_weight" step="0.1" min="0" max="5" placeholder="Optional"
                                       class="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500 text-sm">
                            </div>
                        </div>

                        <!-- Right Column -->
                        <div class="space-y-4">
                            <h4 class="text-lg font-semibold text-gray-800 border-b pb-2">Medical Details</h4>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Farrowing Type</label>
                                    <select name="farrowing_type" required
                                            class="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500 text-sm">
                                        <option value="natural">Natural</option>
                                        <option value="assisted">Assisted</option>
                                        <option value="cesarean">Cesarean</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Difficulty Level</label>
                                    <select name="farrowing_difficulty" required
                                            class="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500 text-sm">
                                        <option value="easy">Easy</option>
                                        <option value="moderate">Moderate</option>
                                        <option value="difficult">Difficult</option>
                                        <option value="complicated">Complicated</option>
                                    </select>
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Sow Condition</label>
                                <select name="sow_condition" required
                                        class="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500 text-sm">
                                    <option value="excellent">Excellent</option>
                                    <option value="good">Good</option>
                                    <option value="fair">Fair</option>
                                    <option value="poor">Poor</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Complications</label>
                                <textarea name="complications" rows="3" placeholder="Any complications during farrowing..."
                                          class="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500 text-sm"></textarea>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Additional Notes</label>
                                <textarea name="notes" rows="4" placeholder="Any observations about the farrowing process..."
                                          class="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500 text-sm"></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="flex justify-between items-center pt-6 border-t mt-6">
                        <div class="text-sm text-gray-500">
                            All required fields must be completed
                        </div>
                        <div class="flex space-x-3">
                            <button type="button" onclick="closeFarrowingModal()"
                                    class="px-6 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">
                                Cancel
                            </button>
                            <button type="submit"
                                    class="px-6 py-2 bg-green-600 text-white rounded-md text-sm font-medium hover:bg-green-700">
                                Record Farrowing
                            </button>
                        </div>
                    </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Update Status Modal -->
    <div id="statusModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Update Gestation Status</h3>
                    <button onclick="closeStatusModal()" class="text-gray-400 hover:text-gray-600">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <div id="statusInfo" class="bg-gray-50 p-4 rounded-lg mb-4">
                    <!-- Status info will be populated by JavaScript -->
                </div>

                <form id="statusForm" method="POST">
                    @csrf
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Status Date</label>
                            <input type="date" name="status_date" required
                                   max="{{ now()->toDateString() }}"
                                   class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">New Status</label>
                            <select name="status" required class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                <option value="" disabled selected>-- Select Status --</option>
                                <option value="pregnant">🤰 Still Pregnant</option>
                                <option value="lost_pregnancy">💔 Lost Pregnancy</option>
                                <option value="aborted">🚫 Aborted</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Notes (optional)</label>
                            <textarea name="notes" rows="3"
                                      placeholder="Any additional information..."
                                      class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500"></textarea>
                        </div>

                        <div class="flex justify-end space-x-3 pt-4">
                            <button type="button" onclick="closeStatusModal()"
                                    class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">
                                Cancel
                            </button>
                            <button type="submit"
                                    class="px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700">
                                Update Status
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openFarrowingModal(matingId, femaleEarTag, expectedDate) {
            try {
                // Update form action
                const form = document.getElementById('farrowingForm');
                if (form) {
                    form.action = `/gestation/${matingId}/farrowing`;
                }

                // Set default farrowing date to expected date
                const dateInput = document.querySelector('input[name="farrowing_date"]');
                if (dateInput) {
                    dateInput.value = expectedDate;
                }

                // Update farrowing info
                const infoDiv = document.getElementById('farrowingInfo');
                if (infoDiv) {
                    infoDiv.innerHTML = `
                        <div class="text-sm">
                            <div class="font-medium text-gray-900 mb-2">Sow: ${femaleEarTag}</div>
                            <div class="text-gray-600">Expected Farrowing: ${new Date(expectedDate).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}</div>
                            <div class="text-green-600 mt-2">✓ Ready to record farrowing details</div>
                        </div>
                    `;
                }

                // Show modal
                const modal = document.getElementById('farrowingModal');
                if (modal) {
                    modal.classList.remove('hidden');
                }
            } catch (error) {
                console.error('Error opening farrowing modal:', error);
            }
        }

        function closeFarrowingModal() {
            try {
                const modal = document.getElementById('farrowingModal');
                if (modal) {
                    modal.classList.add('hidden');
                }

                const form = document.getElementById('farrowingForm');
                if (form) {
                    form.reset();
                }

                const validation = document.getElementById('genderValidation');
                if (validation) {
                    validation.innerHTML = '';
                }
            } catch (error) {
                console.error('Error closing farrowing modal:', error);
            }
        }

        function autoCalculateTotal() {
            try {
                const liveBirthsInput = document.getElementById('liveBirthsInput');
                const stillbirthsInput = document.getElementById('stillbirthsInput');
                const totalInput = document.getElementById('totalPigletsInput');

                if (!liveBirthsInput || !stillbirthsInput || !totalInput) {
                    return; // Elements not found, skip calculation
                }

                const liveBirths = parseInt(liveBirthsInput.value) || 0;
                const stillbirths = parseInt(stillbirthsInput.value) || 0;

                // Only auto-calculate if total is empty or if live+still doesn't match current total
                if (!totalInput.value || (liveBirths + stillbirths) !== parseInt(totalInput.value)) {
                    if (liveBirths > 0 || stillbirths > 0) {
                        totalInput.value = liveBirths + stillbirths;
                    }
                }
            } catch (error) {
                console.error('Error in auto-calculate total:', error);
            }
        }

        function updateTotals() {
            try {
                const totalBornInput = document.querySelector('input[name="total_piglets_born"]');
                const liveBirthsInput = document.querySelector('input[name="live_births"]');
                const stillbirthsInput = document.querySelector('input[name="stillbirths"]');
                const malesInput = document.querySelector('input[name="number_of_males"]');
                const femalesInput = document.querySelector('input[name="number_of_females"]');
                const validationDiv = document.getElementById('genderValidation');

                // Check if all required elements exist
                if (!totalBornInput || !liveBirthsInput || !stillbirthsInput || !malesInput || !femalesInput || !validationDiv) {
                    return; // Elements not found, skip validation
                }

                const totalBorn = parseInt(totalBornInput.value) || 0;
                const liveBirths = parseInt(liveBirthsInput.value) || 0;
                const stillbirths = parseInt(stillbirthsInput.value) || 0;
                const males = parseInt(malesInput.value) || 0;
                const females = parseInt(femalesInput.value) || 0;

                let hasErrors = false;
                let messages = [];

                // Check birth totals first
                if (totalBorn > 0 || liveBirths > 0 || stillbirths > 0) {
                    const calculatedTotal = liveBirths + stillbirths;

                    if (totalBorn > 0 && calculatedTotal !== totalBorn) {
                        messages.push('<span class="text-red-600">⚠️ Live births (' + liveBirths + ') + stillbirths (' + stillbirths + ') must equal total piglets born (' + totalBorn + ')</span>');
                        hasErrors = true;
                    }
                }

                // Check gender distribution if no birth total errors
                if (!hasErrors && totalBorn > 0) {
                const genderTotal = males + females;

                if (genderTotal > totalBorn) {
                   messages.push('<span class="text-red-600">⚠️ Gender total (' + genderTotal + ') exceeds total piglets born (' + totalBorn + ')</span>');
                 hasErrors = true;
                 } else if (genderTotal < totalBorn && genderTotal > 0) {
                   messages.push('<span class="text-yellow-600">⚠️ Gender total (' + genderTotal + ') is less than total piglets born (' + totalBorn + ') - some genders not specified</span>');
                 } else if (genderTotal === totalBorn) {
                    messages.push('<span class="text-green-600">✓ All validations passed: Birth totals and gender distribution are correct</span>');
                     }
                }          


                // Display messages
                validationDiv.innerHTML = messages.join('<br>');
            } catch (error) {
                console.error('Error in updateTotals:', error);
            }
        }



        function openStatusModal(matingId, femaleEarTag) {
            try {
                // Update form action
                const form = document.getElementById('statusForm');
                if (form) {
                    form.action = `/gestation/${matingId}/update-status`;
                }

                // Set default status date to today
                const dateInput = document.querySelector('input[name="status_date"]');
                if (dateInput) {
                    dateInput.value = new Date().toISOString().split('T')[0];
                }

                // Update status info
                const infoDiv = document.getElementById('statusInfo');
                if (infoDiv) {
                    infoDiv.innerHTML = `
                        <div class="text-sm">
                            <div class="font-medium text-gray-900 mb-2">Sow: ${femaleEarTag}</div>
                            <div class="text-gray-600">Update pregnancy status if there are any changes</div>
                        </div>
                    `;
                }

                // Show modal
                const modal = document.getElementById('statusModal');
                if (modal) {
                    modal.classList.remove('hidden');
                }
            } catch (error) {
                console.error('Error opening status modal:', error);
            }
        }

        function closeStatusModal() {
            try {
                const modal = document.getElementById('statusModal');
                if (modal) {
                    modal.classList.add('hidden');
                }

                const form = document.getElementById('statusForm');
                if (form) {
                    form.reset();
                }
            } catch (error) {
                console.error('Error closing status modal:', error);
            }
        }

        // Dropdown toggle function
        function toggleDropdown(matingId) {
            const dropdown = document.getElementById('dropdown-menu-' + matingId);
            const button = document.getElementById('actions-menu-' + matingId);
            
            if (dropdown) {
                const isHidden = dropdown.classList.contains('hidden');
                
                // Close other dropdowns first
                document.querySelectorAll('[id^="dropdown-menu-"]').forEach(menu => {
                    menu.classList.add('hidden');
                    menu.classList.remove('origin-top-right', 'origin-bottom-right');
                    menu.style.bottom = 'auto';
                    menu.style.top = '100%';
                });
                
                if (isHidden) {
                    // Show this dropdown
                    dropdown.classList.remove('hidden');
                    
                    // Check if there's enough space below
                    const rect = button.getBoundingClientRect();
                    const dropdownHeight = 200; // Approximate dropdown height
                    const spaceBelow = window.innerHeight - rect.bottom;
                    const spaceAbove = rect.top;
                    
                    if (spaceBelow < dropdownHeight && spaceAbove > dropdownHeight) {
                        // Open upward
                        dropdown.classList.remove('origin-top-right');
                        dropdown.classList.add('origin-bottom-right');
                        dropdown.style.bottom = '100%';
                        dropdown.style.top = 'auto';
                    } else {
                        // Open downward (default)
                        dropdown.classList.remove('origin-bottom-right');
                        dropdown.classList.add('origin-top-right');
                        dropdown.style.bottom = 'auto';
                        dropdown.style.top = '100%';
                    }
                }
            }
        }

        // Close dropdowns when clicking outside
        document.addEventListener('click', function(event) {
            if (!event.target.closest('.relative')) {
                document.querySelectorAll('[id^="dropdown-menu-"]').forEach(menu => {
                    menu.classList.add('hidden');
                });
            }
        });

        // Form submission validation
        function validateFarrowingForm() {
            try {
                const totalBornInput = document.querySelector('input[name="total_piglets_born"]');
                const liveBirthsInput = document.querySelector('input[name="live_births"]');
                const stillbirthsInput = document.querySelector('input[name="stillbirths"]');
                const malesInput = document.querySelector('input[name="number_of_males"]');
                const femalesInput = document.querySelector('input[name="number_of_females"]');

                // Check if all required elements exist
                if (!totalBornInput || !liveBirthsInput || !stillbirthsInput || !malesInput || !femalesInput) {
                    console.warn('Some form elements not found, allowing submission');
                    return true; // Allow submission if elements not found
                }

                const totalBorn = parseInt(totalBornInput.value) || 0;
                const liveBirths = parseInt(liveBirthsInput.value) || 0;
                const stillbirths = parseInt(stillbirthsInput.value) || 0;
                const males = parseInt(malesInput.value) || 0;
                const females = parseInt(femalesInput.value) || 0;

                // Check if birth totals are correct
                if (totalBorn > 0 && (liveBirths + stillbirths) !== totalBorn) {
                    alert('Error: Live births (' + liveBirths + ') + stillbirths (' + stillbirths + ') must equal total piglets born (' + totalBorn + ')');
                    return false;
                }

                // Check if gender distribution is correct (only if at least one gender field has a value > 0)
                if ((males > 0 || females > 0) && (males + females) !== liveBirths) {
                    alert('Error: Number of males (' + males + ') + females (' + females + ') must equal live births (' + liveBirths + ')');
                    return false;
                }

                // All validations passed
                return true;
            } catch (error) {
                console.error('Error in form validation:', error);
                return true; // Allow submission if validation fails due to error
            }
        }

        // Initialize event listeners when DOM is ready
        document.addEventListener('DOMContentLoaded', function() {
            // Close modals when clicking outside
            const farrowingModal = document.getElementById('farrowingModal');
            const statusModal = document.getElementById('statusModal');

            if (farrowingModal) {
                farrowingModal.addEventListener('click', function(e) {
                    if (e.target === this) {
                        closeFarrowingModal();
                    }
                });
            }

            if (statusModal) {
                statusModal.addEventListener('click', function(e) {
                    if (e.target === this) {
                        closeStatusModal();
                    }
                });
            }
        });
    </script>
</x-app-layout>
