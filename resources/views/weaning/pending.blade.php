<x-app-layout>

<div class="container mx-auto px-4 py-2">
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Pending Weaning</h1>
            <!-- <p class="text-gray-600 mt-2">Pigs not yet ready for weaning (under {{ config('breeding.cycle.birth_to_weaning_days', 45) }} days old)</p> -->
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
        <div class="bg-white p-4 rounded-lg shadow border border-gray-200">
            <div class="flex items-center">
                <div class="p-2 bg-blue-100 rounded-lg">
                    <svg class="h-5 w-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-xs font-medium text-gray-600">Total Pending</p>
                    <p class="text-xl font-semibold text-gray-900">{{ $stats['total_pending'] }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white p-4 rounded-lg shadow border border-gray-200 group relative">
            <div class="flex items-center">
                <div class="p-2 bg-yellow-100 rounded-lg">
                    <svg class="h-5 w-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div class="ml-3 flex-1">
                    <p class="text-xs font-medium text-gray-600">Ready Soon (7 days)</p>
                    <p class="text-xl font-semibold text-gray-900">{{ $stats['ready_soon'] }}</p>
                </div>
                <div class="opacity-0 group-hover:opacity-100 transition-opacity">
                    <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
            </div>
            <!-- Tooltip -->
            <div class="absolute left-0 top-full mt-2 w-80 p-3 bg-gray-900 text-white text-xs rounded-lg opacity-0 group-hover:opacity-100 transition-opacity z-10 pointer-events-none">
                <strong>Ready Soon - Planning Alert:</strong><br>
                • Pigs that will reach weaning age ({{ config('breeding.cycle.birth_to_weaning_days', 45) }} days) within the next 7 days<br>
                • Helps you prepare weaning facilities and schedule staff<br>
                • Plan separation areas and monitor mother-piglet behavior<br>
                • Ensure adequate nutrition for the transition period<br>
                • These pigs will move to "In Progress" status when they reach weaning age
            </div>
        </div>

        <div class="bg-white p-4 rounded-lg shadow border border-gray-200">
            <div class="flex items-center">
                <div class="p-2 bg-green-100 rounded-lg">
                    <svg class="h-5 w-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-xs font-medium text-gray-600">Ready for Weaning</p>
                    <p class="text-xl font-semibold text-gray-900">{{ $stats['ready_for_weaning'] }}</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Color Legend Card -->
    <div class="bg-white p-4 rounded-lg shadow border border-gray-200 mb-6">
        <h3 class="text-sm font-semibold text-gray-800 mb-3">📊 Status Color Guide</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="flex items-center space-x-3">
                <div class="h-4 w-4 rounded-full bg-blue-200 border-2 border-blue-300"></div>
                <div>
                    <span class="text-sm font-medium text-blue-800">Growing</span>
                    <p class="text-xs text-gray-600">0-37 days old, "Wean Now" button disabled</p>
                </div>
            </div>
            <div class="flex items-center space-x-3">
                <div class="h-4 w-4 rounded-full bg-yellow-200 border-2 border-yellow-300"></div>
                <div>
                    <span class="text-sm font-medium text-yellow-800">Ready Soon</span>
                    <p class="text-xs text-gray-600">38-44 days old, "Wean Now" button disabled</p>
                </div>
            </div>
            <div class="flex items-center space-x-3">
                <div class="h-4 w-4 rounded-full bg-green-200 border-2 border-green-300"></div>
                <div>
                    <span class="text-sm font-medium text-green-800">Ready to Wean</span>
                    <p class="text-xs text-gray-600">45+ days old, "Wean Now" button enabled</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Pending Pigs Table -->
    <div class="bg-white rounded-lg shadow-sm border">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-xl font-semibold text-gray-900">Pending Weaning ({{ $pendingPigs->total() }})</h2>
            <!-- <p class="text-sm text-gray-600 mt-1">Pigs that haven't reached weaning age yet</p> -->
        </div>

        @if($pendingPigs->count() > 0)
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pig</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Birth Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Current Age</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Weight (kg)</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Days Until Weaning</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Mother</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($pendingPigs as $pig)
                            @if($pig->gender === 'F')
                                @php
                                    $currentAge = (int) $pig->date_of_birth->diffInDays(now());
                                    $weaningAge = config('breeding.cycle.birth_to_weaning_days', 45);
                                    $daysUntilWeaning = $weaningAge - $currentAge;
                                    $isReadySoon = $daysUntilWeaning <= 7 && $daysUntilWeaning > 0;
                                    $isReadyForWeaning = $currentAge >= $weaningAge;
                                @endphp
                                <tr class="hover:bg-gray-50 @if($isReadyForWeaning) bg-green-50 @elseif($isReadySoon) bg-yellow-50 @endif">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-10 w-10">
                                                <div class="h-10 w-10 rounded-full @if($isReadyForWeaning) bg-green-200 @elseif($isReadySoon) bg-yellow-200 @else bg-blue-100 @endif flex items-center justify-center">
                                                    <span class="text-sm font-medium @if($isReadyForWeaning) text-green-800 @elseif($isReadySoon) text-yellow-800 @else text-blue-700 @endif">
                                                        {{ $pig->gender === 'M' ? '🐗' : '🐷' }}
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900">{{ $pig->ear_tag_number }}</div>
                                                <div class="text-xs text-gray-500">{{ $pig->code }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ $pig->date_of_birth->format('M d, Y') }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <span class="@if($isReadyForWeaning) text-green-600 font-bold @elseif($isReadySoon) text-yellow-600 font-semibold @endif">
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
                                        @if($isReadyForWeaning)
                                            <span class="text-green-600 font-bold">Ready now</span>
                                        @elseif($daysUntilWeaning > 0)
                                            <span class="@if($isReadySoon) text-yellow-600 font-semibold @endif">
                                                {{ $daysUntilWeaning }} days
                                            </span>
                                        @else
                                            <span class="text-green-600 font-bold">Ready now</span>
                                        @endif
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
                                        @if($isReadyForWeaning)
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                Ready to Wean
                                            </span>
                                        @elseif($isReadySoon)
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                                Ready Soon
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                Growing
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
                                                    @if($isReadyForWeaning)
                                                        <button onclick="openWeaningModal({{ $pig->id }}, '{{ $pig->code }}', {{ $currentAge }})" class="flex items-center w-full px-4 py-2 text-sm text-green-600 hover:bg-green-50">
                                                            <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                                            </svg>
                                                            Wean Now
                                                        </button>
                                                    @else
                                                        <div class="flex items-center px-4 py-2 text-sm text-gray-400 cursor-not-allowed" title="Pig must be at least {{ config('breeding.cycle.birth_to_weaning_days', 45) }} days old to wean">
                                                            <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                                            </svg>
                                                            Wean Now
                                                        </div>
                                                    @endif
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
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="px-6 py-4 border-t border-gray-200">
                <div class="flex items-center justify-between">
                    <div class="text-sm text-gray-700">
                        Showing {{ $pendingPigs->firstItem() }} to {{ $pendingPigs->lastItem() }} of {{ $pendingPigs->total() }} results
                    </div>
                    <div class="flex items-center space-x-2">
                        <x-simple-pagination :paginator="$pendingPigs" />
                    </div>
                </div>
            </div>
        @else
            <div class="px-6 py-12 text-center">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">No pending pigs</h3>
                <p class="mt-1 text-sm text-gray-500">All pigs have reached weaning age or have been weaned.</p>
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
            <h3 class="text-lg font-medium text-gray-900 mb-4">Update Pre-Weaning Piglet Weight & Development</h3>
            <form id="weightForm" method="POST">
                @csrf
                <div id="weightInfo" class="bg-blue-50 p-4 rounded-lg mb-4">
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
                            <p class="text-xs text-gray-500 mt-1">Piglets pending weaning typically weigh 5-15kg</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Weigh Date</label>
                            <input type="date" name="weigh_date" required
                                   class="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500"
                                   value="{{ date('Y-m-d') }}">
                        </div>
                    </div>

                    <div class="mt-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Age Week</label>
                        <select name="age_week" required class="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500">
                            <option value="" disabled selected>Select age week...</option>
                            @for($week = 1; $week <= 12; $week++)
                                <option value="{{ $week }}">Week {{ $week }} ({{ 1 + ($week-1)*0.5 }}-{{ 2 + ($week-1)*0.5 }}kg expected)</option>
                            @endfor
                        </select>
                        <p class="text-xs text-gray-500 mt-1">Which week of age does this piglet represent?</p>
                    </div>
                </div>

                <!-- Pre-Weaning Development Section -->
                <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-4">
                    <h4 class="text-md font-medium text-gray-900 mb-3">🌱 Pre-Weaning Development</h4>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Weaning Readiness</label>
                            <select name="weaning_readiness" class="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500">
                                <option value="" disabled selected>Select readiness...</option>
                                <option value="not_ready">Not Ready (< 6kg)</option>
                                <option value="developing">Developing (6-8kg)</option>
                                <option value="nearly_ready">Nearly Ready (8-10kg)</option>
                                <option value="ready">Ready (10kg+)</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Feeding Behavior</label>
                            <select name="feeding_behavior" class="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500">
                                <option value="" disabled selected>Select behavior...</option>
                                <option value="milk_only">Milk Only</option>
                                <option value="starting_solids">Starting Solids</option>
                                <option value="eating_well">Eating Well</option>
                                <option value="independent">Independent</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Activity Level</label>
                            <select name="activity_level" class="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500">
                                <option value="" disabled selected>Select activity...</option>
                                <option value="low">Low</option>
                                <option value="moderate">Moderate</option>
                                <option value="active">Active</option>
                                <option value="very_active">Very Active</option>
                            </select>
                        </div>
                    </div>

                    <div class="mt-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Development Notes</label>
                        <textarea name="development_notes" rows="2"
                                  class="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500"
                                  placeholder="Notes about feeding behavior, activity, social interaction with littermates..."></textarea>
                    </div>
                </div>

                <!-- Health & Condition Section -->
                <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 mb-4">
                    <h4 class="text-md font-medium text-gray-900 mb-3">🏥 Health & Condition</h4>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Body Condition</label>
                            <select name="body_condition" class="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500">
                                <option value="" disabled selected>Select condition...</option>
                                <option value="thin">Thin</option>
                                <option value="ideal">Ideal</option>
                                <option value="chubby">Chubby</option>
                                <option value="overweight">Overweight</option>
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
                                  placeholder="Any notes about the weighing, piglet condition, behavior, health observations..."></textarea>
                    </div>
                </div>

                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeWeightModal()"
                            class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                        Cancel
                    </button>
                    <button type="submit"
                            class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                        Update Weight & Development
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openWeaningModal(pigId, pigCode, ageInDays) {
    document.getElementById('modalTitle').textContent = `Record Weaning - ${pigCode}`;
    document.getElementById('weaningForm').action = `/weaning/${pigId}/wean`;
    document.getElementById('weaningModal').classList.remove('hidden');

    // Ensure age is displayed as integer
    const ageInteger = Math.floor(ageInDays);

    // Add age information to the modal
    const form = document.getElementById('weaningForm');
    let ageInfo = form.querySelector('.age-info');
    if (!ageInfo) {
        ageInfo = document.createElement('div');
        ageInfo.className = 'age-info mb-4 p-3 bg-green-50 rounded-md border border-green-200';
        form.insertBefore(ageInfo, form.firstChild);
    }
    ageInfo.innerHTML = `
        <div class="text-sm text-green-800">
            <strong>✅ READY FOR WEANING</strong><br>
            <strong>Pig:</strong> ${pigCode}<br>
            <strong>Current Age:</strong> ${ageInteger} days<br>
            <strong>Minimum Weaning Age:</strong> {{ config('breeding.cycle.birth_to_weaning_days', 45) }} days<br>
            <strong>Status:</strong> Ready for weaning (on time)
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
function openWeightModal(pigId, pigCode) {
    document.getElementById('weightForm').action = `/weaning/${pigId}/update-weight`;
    document.getElementById('weightInfo').innerHTML = `
        <div class="text-sm">
            <div class="font-medium text-gray-900 mb-2">Piglet: ${pigCode}</div>
            <div class="text-gray-600">Update the current weight for this piglet pending weaning</div>
            <div class="text-blue-600 mt-1">💡 Track weight gain before weaning for better health assessment</div>
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
