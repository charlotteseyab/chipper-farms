<x-app-layout>

<div class="container mx-auto px-4 py-2">

    <!-- Alert Banner -->
    <!-- @if($stats['total_overdue'] > 0)
        <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
            <div class="flex items-center">
                <div class="p-2 bg-red-100 rounded-lg">
                    <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div class="ml-4">
                    <h3 class="text-lg font-semibold text-red-800">Urgent Action Required</h3>
                    <p class="text-sm text-red-600">
                        {{ $stats['total_overdue'] }} pig{{ $stats['total_overdue'] > 1 ? 's are' : ' is' }} overdue for weaning.
                        @if($stats['critically_overdue'] > 0)
                            {{ $stats['critically_overdue'] }} critically overdue (14+ days).
                        @endif
                    </p>
                </div>
            </div>
        </div>
    @endif -->

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-8">
        <div class="bg-white p-4 rounded-lg shadow border border-gray-200">
            <div class="flex items-center">
                <div class="p-2 bg-red-100 rounded-lg">
                    <svg class="h-5 w-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-xs font-medium text-gray-600">Total Overdue</p>
                    <p class="text-xl font-semibold text-gray-900">{{ $stats['total_overdue'] }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white p-4 rounded-lg shadow border border-gray-200">
            <div class="flex items-center">
                <div class="p-2 bg-red-200 rounded-lg">
                    <svg class="h-5 w-5 text-red-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-xs font-medium text-gray-600">Critically Overdue (14+ days)</p>
                    <p class="text-xl font-semibold text-gray-900">{{ $stats['critically_overdue'] }}</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Color Legend Card -->
    <div class="bg-white p-4 rounded-lg shadow border border-gray-200 mb-6">
        <h3 class="text-sm font-semibold text-gray-800 mb-3">⚠️ Urgency Color Guide</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="flex items-center space-x-3">
                <div class="h-4 w-4 rounded-full bg-orange-200 border-2 border-orange-300"></div>
                <div>
                    <span class="text-sm font-medium text-orange-800">High Priority</span>
                    <p class="text-xs text-gray-600">52-58 days old, 7-13 days overdue</p>
                </div>
            </div>
            <div class="flex items-center space-x-3">
                <div class="h-4 w-4 rounded-full bg-red-200 border-2 border-red-300"></div>
                <div>
                    <span class="text-sm font-medium text-red-800">Critical</span>
                    <p class="text-xs text-gray-600">59+ days old, 14+ days overdue - urgent!</p>
                </div>
            </div>
        </div>
        <div class="mt-3 p-2 bg-red-50 rounded-md border border-red-200">
            <p class="text-xs text-red-700">
                <strong>⚡ Action Required:</strong> All pigs on this page need immediate weaning attention.
            </p>
        </div>
    </div>

    <!-- Overdue Pigs Table -->
    <div class="bg-white rounded-lg shadow-sm border">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-xl font-semibold text-gray-900">Overdue Pigs ({{ $overduePigs->total() }})</h2>
            <p class="text-sm text-gray-600 mt-1">Pigs that are 7+ days past their target weaning date</p>
        </div>

        @if($overduePigs->count() > 0)
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pig</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Birth Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Current Age</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Weight (kg)</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Days Overdue</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Mother</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Priority</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($overduePigs as $pig)
                            @if($pig->gender === 'F')
                            @php
                                $currentAge = (int) $pig->date_of_birth->diffInDays(now());
                                $weaningAge = config('breeding.cycle.birth_to_weaning_days', 45);
                                $daysOverdue = $currentAge - $weaningAge;
                                $isCritical = $daysOverdue >= 14;
                            @endphp
                            <tr class="hover:bg-gray-50 @if($isCritical) bg-red-50 @endif">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10">
                                            <div class="h-10 w-10 rounded-full @if($isCritical) bg-red-200 @else bg-red-100 @endif flex items-center justify-center">
                                                <span class="text-sm font-medium @if($isCritical) text-red-800 @else text-red-700 @endif">
                                                    {{ $pig->gender === 'M' ? '🐗' : '🐷' }}
                                                </span>
                                            </div>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm text-gray-500">{{ $pig->ear_tag_number }}</div>
                                            <div class="text-sm font-medium text-gray-900">{{ $pig->code }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ $pig->date_of_birth->format('M d, Y') }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <span class="@if($isCritical) text-red-700 font-bold @else text-red-600 font-semibold @endif">
                                        {{ $currentAge }} days
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <div class="text-sm font-medium text-gray-900">
                                        @php
                                            $latestWeight = $pig->weights()->latest('weigh_date')->first();
                                        @endphp
                                        {{ $latestWeight ? $latestWeight->weight : 'N/A' }}
                                    </div>
                                    @if($latestWeight)
                                        <div class="text-xs text-gray-500">{{ $latestWeight->weigh_date->format('M d') }}</div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <span class="@if($isCritical) text-red-700 font-bold @else text-red-600 font-semibold @endif">
                                        {{ $daysOverdue }} days
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    @if($pig->mother)
                                        <a href="{{ route('pigs.show', $pig->mother->id) }}" class="text-blue-600 hover:text-blue-800">
                                            {{ $pig->mother->ear_tag_number }}
                                        </a>
                                    @else
                                        <span class="text-gray-400">N/A</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($isCritical)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-200 text-red-800">
                                            Critical
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                            High
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="relative inline-block text-left" x-data="{ open: false, place: 'down', toggle(){ this.open = !this.open; if(!this.open){ return; } this.$nextTick(() => { const menu = this.$refs.menu; const trigger = this.$refs.trigger; if(!menu || !trigger) return; const mr = menu.getBoundingClientRect(); const tr = trigger.getBoundingClientRect(); const below = window.innerHeight - tr.bottom; const above = tr.top; this.place = (below < mr.height + 16 && above > mr.height) ? 'up' : 'down'; }); } }">
                                        <button x-ref="trigger" @click="toggle()" class="flex items-center text-gray-600 hover:text-gray-900">
                                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"/>
                                            </svg>
                                        </button>
                                        <div x-ref="menu" x-show="open" @click.away="open = false" x-transition :class="place === 'down' ? 'origin-top-right mt-2 top-full' : 'origin-bottom-right mb-2 bottom-full'" class="absolute right-0 w-48 bg-white rounded-md shadow-lg z-50 border">
                                            <div class="py-1">
                                                <button onclick="openWeaningModal({{ $pig->id }}, '{{ $pig->ear_tag_number }}', {{ $currentAge }})" class="flex items-center w-full px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                                                    <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                                    </svg>
                                                    Wean Now
                                                </button>
                                                <button onclick="openWeightModal({{ $pig->id }}, '{{ $pig->ear_tag_number }}')" class="flex items-center w-full px-4 py-2 text-sm text-blue-600 hover:bg-blue-50">
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
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="px-6 py-4 border-t border-gray-200">
                <div class="flex items-center justify-between">
                    <div class="text-sm text-gray-700">
                        Showing {{ $overduePigs->firstItem() }} to {{ $overduePigs->lastItem() }} of {{ $overduePigs->total() }} results
                    </div>
                    <div class="flex items-center space-x-2">
                        <x-simple-pagination :paginator="$overduePigs" />
                    </div>
                </div>
            </div>
        @else
            <div class="px-6 py-12 text-center">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">No overdue pigs</h3>
                <p class="mt-1 text-sm text-gray-500">All pigs are being weaned on schedule.</p>
            </div>
        @endif
    </div>
</div>
<x-copyright-minimal />
<!-- Include the weaning modal -->
@include('weaning.partials.weaning-modal')

<!-- Weight Update Modal -->
<div id="weightModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
    <div class="relative top-10 mx-auto p-5 border w-full max-w-2xl shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Update Piglet Weight & Weekly Tracking</h3>
            <form id="weightForm" method="POST">
                @csrf
                <div id="weightInfo" class="bg-gray-50 p-4 rounded-lg mb-4">
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
                            <option value="" disabled selected>Select target week...</option>
                            @for($week = 1; $week <= 52; $week++)
                                <option value="{{ $week }}">Week {{ $week }}</option>
                            @endfor
                        </select>
                        <p class="text-xs text-gray-500 mt-1">Which week of growth does this weight represent?</p>
                    </div>
                </div>

                <!-- Weekly Weight Tracking Section -->
                <!-- <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
                    <h4 class="text-md font-medium text-gray-900 mb-3">📊 Weekly Weight Tracking (Optional)</h4>
                    <p class="text-sm text-gray-600 mb-4">Record additional weekly weights to track growth patterns</p>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Week 1 Weight (kg)</label>
                            <input type="number" name="week_1_weight" step="0.1" min="0" max="100"
                                   class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                   placeholder="Optional">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Week 2 Weight (kg)</label>
                            <input type="number" name="week_2_weight" step="0.1" min="0" max="100"
                                   class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                   placeholder="Optional">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Week 3 Weight (kg)</label>
                            <input type="number" name="week_3_weight" step="0.1" min="0" max="100"
                                   class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                   placeholder="Optional">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Week 4 Weight (kg)</label>
                            <input type="number" name="week_4_weight" step="0.1" min="0" max="100"
                                   class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                   placeholder="Optional">
                        </div>
                    </div>

                    <div class="mt-3">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Weekly Notes</label>
                        <textarea name="weekly_notes" rows="2"
                                  class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                  placeholder="Notes about weekly growth patterns, feeding changes, etc..."></textarea>
                    </div>
                </div> -->

                <!-- Additional Information Section -->
                <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 mb-4">
                    <h4 class="text-md font-medium text-gray-900 mb-3">📋 Additional Information</h4>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Body Condition Score</label>
                            <select name="body_condition_score" class="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500">
                                <option value="" disabled selected>Select condition...</option>
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
                                <option value="" disabled selected>Select status...</option>
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
                                  placeholder="Any notes about the weighing or piglet condition..."></textarea>
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
function openWeaningModal(pigId, pigEarTag, ageInDays) {
    document.getElementById('modalTitle').textContent = `Record Weaning - ${pigEarTag}`;
    document.getElementById('weaningForm').action = `/weaning/${pigId}/wean`;
    document.getElementById('weaningModal').classList.remove('hidden');
    
    // Ensure age is displayed as integer
    const ageInteger = Math.floor(ageInDays);
    
    // Add age information to the modal
    const form = document.getElementById('weaningForm');
    let ageInfo = form.querySelector('.age-info');
    if (!ageInfo) {
        ageInfo = document.createElement('div');
        ageInfo.className = 'age-info mb-4 p-3 bg-red-50 rounded-md border border-red-200';
        form.insertBefore(ageInfo, form.firstChild);
    }
    ageInfo.innerHTML = `
        <div class="text-sm text-red-800">
            <strong>⚠️ OVERDUE WEANING</strong><br>
            <strong>Pig:</strong> ${pigEarTag}<br>
            <strong>Current Age:</strong> ${ageInteger} days<br>
            <strong>Target Weaning Age:</strong> {{ config('breeding.cycle.birth_to_weaning_days', 45) }} days<br>
            <strong>Days Overdue:</strong> ${ageInteger - {{ config('breeding.cycle.birth_to_weaning_days', 45) }}} days
        </div>
    `;
}

function closeWeaningModal() {
    document.getElementById('weaningModal').classList.add('hidden');
    document.getElementById('weaningForm').reset();
    
    // Remove age info
    const ageInfo = document.querySelector('.age-info');
    if (ageInfo) {
        ageInfo.remove();
    }
}

// Close modal when clicking outside
document.getElementById('weaningModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeWeaningModal();
    }
});

// Weight Modal Functions
function openWeightModal(pigId, pigEarTag) {
    document.getElementById('weightForm').action = `/weaning/${pigId}/update-weight`;
    document.getElementById('weightInfo').innerHTML = `
        <div class="text-sm">
            <div class="font-medium text-gray-900 mb-2">Piglet (Ear Tag): ${pigEarTag}</div>
            <div class="text-gray-600">Update the current weight for this weaned piglet</div>
            <div class="text-blue-600 mt-1">💡 Regular weight monitoring helps track growth progress</div>
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
