<x-app-layout>

<div class="container mx-auto px-4 py-2">
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">In Progress - Weaning</h1>
            <p class="text-gray-600 mt-2">Pigs that have been weaned and are growing toward maturity ({{ config('breeding.cycle.birth_to_maturity_days', 180) }} days)</p>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
        <div class="bg-white p-4 rounded-lg shadow border border-gray-200">
            <div class="flex items-center">
                <div class="p-2 bg-yellow-100 rounded-lg">
                    <svg class="h-5 w-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-xs font-medium text-gray-600">Total In Progress</p>
                    <p class="text-xl font-semibold text-gray-900">{{ $stats['total_in_progress'] }}</p>
                </div>
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
                    <p class="text-xs font-medium text-gray-600">Recently Weaned</p>
                    <p class="text-xl font-semibold text-gray-900">{{ $stats['recently_weaned'] }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white p-4 rounded-lg shadow border border-gray-200">
            <div class="flex items-center">
                <div class="p-2 bg-orange-100 rounded-lg">
                    <svg class="h-5 w-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-xs font-medium text-gray-600">Near Maturity</p>
                    <p class="text-xl font-semibold text-gray-900">{{ $stats['near_maturity'] }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white p-4 rounded-lg shadow border border-gray-200">
            <div class="flex items-center">
                <div class="p-2 bg-purple-100 rounded-lg">
                    <svg class="h-5 w-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-xs font-medium text-gray-600">Ready for Completion</p>
                    <p class="text-xl font-semibold text-gray-900">{{ $stats['ready_for_completion'] }}</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Color Legend Card -->
    <div class="bg-white p-4 rounded-lg shadow border border-gray-200 mb-6">
        <h3 class="text-sm font-semibold text-gray-800 mb-3">📊 Progress Color Guide</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="flex items-center space-x-3">
                <div class="h-4 w-4 rounded-full bg-yellow-200 border-2 border-yellow-300"></div>
                <div>
                    <span class="text-sm font-medium text-yellow-800">Growing</span>
                    <p class="text-xs text-gray-600">Under 173 days, completion button disabled</p>
                </div>
            </div>
            <div class="flex items-center space-x-3">
                <div class="h-4 w-4 rounded-full bg-orange-200 border-2 border-orange-300"></div>
                <div>
                    <span class="text-sm font-medium text-orange-800">Near Maturity</span>
                    <p class="text-xs text-gray-600">173-179 days, "Ready for Completion" button</p>
                </div>
            </div>
            <div class="flex items-center space-x-3">
                <div class="h-4 w-4 rounded-full bg-green-200 border-2 border-green-300"></div>
                <div>
                    <span class="text-sm font-medium text-green-800">Ready for Completion</span>
                    <p class="text-xs text-gray-600">180+ days, "Complete Maturity" button enabled</p>
                </div>
            </div>
        </div>
    </div>

    <!-- In Progress Pigs Table -->
    <div class="bg-white rounded-lg shadow-sm border">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-xl font-semibold text-gray-900">In Progress Pigs ({{ $inProgressPigs->total() }})</h2>
            <p class="text-sm text-gray-600 mt-1">Weaned pigs growing toward maturity (180 days from birth)</p>
        </div>

        @if($inProgressPigs->count() > 0)
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pig</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Weaning Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Days Since Weaning</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Weight (kg)</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Days to Maturity</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Progress</th>
                            <!-- <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Mother</th> -->
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($inProgressPigs as $pig)
                            @if($pig->gender === 'F')
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10">
                                            <div class="h-10 w-10 rounded-full bg-yellow-100 flex items-center justify-center">
                                                <span class="text-sm font-medium text-yellow-700">
                                                    {{ $pig->gender === 'M' ? '🐗' : '🐷' }}
                                                </span>
                                            </div>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-500">{{ $pig->ear_tag_number }}</div>
                                            <div class="text-sm  text-gray-900">{{ $pig->code }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    @if($pig->weaning)
                                        {{ $pig->weaning->weaning_date->format('M d, Y') }}
                                    @else
                                        <span class="text-gray-400">No record</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    @if(isset($pig->days_since_weaning))
                                        <span class="text-blue-600 font-semibold">{{ $pig->days_since_weaning }} days</span>
                                    @else
                                        <span class="text-gray-400">N/A</span>
                                    @endif
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
                                    @if(isset($pig->days_until_mature))
                                        @if($pig->days_until_mature <= 14)
                                            <span class="text-orange-600 font-semibold">{{ $pig->days_until_mature }} days</span>
                                        @else
                                            <span class="text-green-600">{{ $pig->days_until_mature }} days</span>
                                        @endif
                                    @else
                                        <span class="text-gray-400">N/A</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if(isset($pig->progress_percentage))
                                        <div class="flex items-center">
                                            <div class="w-16 bg-gray-200 rounded-full h-2 mr-2">
                                                <div class="bg-yellow-500 h-2 rounded-full" style="width: {{ min($pig->progress_percentage, 100) }}%"></div>
                                            </div>
                                            <span class="text-xs text-gray-600">{{ $pig->progress_percentage }}%</span>
                                        </div>
                                    @else
                                        <span class="text-gray-400">N/A</span>
                                    @endif
                                </td>
                                <!-- <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    @if($pig->mother)
                                        <a href="{{ route('pigs.show', $pig->mother->id) }}" class="text-blue-600 hover:text-blue-800">
                                            {{ $pig->mother->ear_tag_number }}
                                        </a>
                                    @else
                                        <span class="text-gray-400">N/A</span>
                                    @endif
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
                                                @if(isset($pig->days_until_mature) && $pig->days_until_mature <= 0)
                                                    <button onclick="openMaturityModal({{ $pig->id }}, '{{ $pig->ear_tag_number }}', {{ $pig->date_of_birth->diffInDays(now()) }})" class="flex items-center w-full px-4 py-2 text-sm text-green-600 hover:bg-green-50">
                                                        <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                        </svg>
                                                        Complete
                                                    </button>
                                                @elseif(isset($pig->days_until_mature) && $pig->days_until_mature <= 7)
                                                    <button onclick="openMaturityModal({{ $pig->id }}, '{{ $pig->ear_tag_number }}', {{ $pig->date_of_birth->diffInDays(now()) }})" class="flex items-center w-full px-4 py-2 text-sm text-orange-600 hover:bg-orange-50">
                                                        <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                        </svg>
                                                        Ready for Completion
                                                    </button>
                                                @else
                                                    <div class="flex items-center px-4 py-2 text-sm text-gray-400 cursor-not-allowed" title="Pig must reach {{ config('breeding.cycle.birth_to_maturity_days', 180) }} days old for maturity completion">
                                                        <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                        </svg>
                                                        Complete
                                                    </div>
                                                @endif
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
                        Showing {{ $inProgressPigs->firstItem() }} to {{ $inProgressPigs->lastItem() }} of {{ $inProgressPigs->total() }} results
                    </div>
                    <div class="flex items-center space-x-2">
                        <x-simple-pagination :paginator="$inProgressPigs" />
                    </div>
                </div>
            </div>
        @else
            <div class="px-6 py-12 text-center">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">No pigs in progress</h3>
                <p class="mt-1 text-sm text-gray-500">No weaned pigs are currently growing toward maturity.</p>
            </div>
        @endif
    </div>
</div>

<!-- Weight Update Modal -->
<div id="weightModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
    <div class="relative top-10 mx-auto p-5 border w-full max-w-2xl shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Update Growing Piglet Weight & Maturation Progress</h3>
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
                            <label class="block text-sm font-medium text-gray-700 mb-2">Current Weight (kg)</label>
                            <input type="number" name="weight" step="0.1" min="0" max="100" required
                                   class="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500">
                            <p class="text-xs text-gray-500 mt-1">Growing piglets typically weigh 15-50kg</p>
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
                            <option value="" disabled selected>-- Select growth stage --</option>
                            @for($week = 8; $week <= 20; $week++)
                                <option value="{{ $week }}">Week {{ $week }} ({{ 8 + ($week-8)*2 }}-{{ 10 + ($week-8)*2 }}kg expected)</option>
                            @endfor
                        </select>
                        <p class="text-xs text-gray-500 mt-1">Which week of growth does this piglet represent?</p>
                    </div>
                </div>

                <!-- Growth Progress Section -->
                <div class="bg-purple-50 border border-purple-200 rounded-lg p-4 mb-4">
                    <h4 class="text-md font-medium text-gray-900 mb-3">📈 Growth Progress</h4>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Growth Rate</label>
                            <select name="growth_rate" class="w-full border-gray-300 rounded-md shadow-sm focus:ring-purple-500 focus:border-purple-500">
                                <option value="" disabled selected>-- Select rate --</option>
                                <option value="slow">Slow (< 0.5kg/week)</option>
                                <option value="normal">Normal (0.5-1kg/week)</option>
                                <option value="fast">Fast (1-1.5kg/week)</option>
                                <option value="very_fast">Very Fast (> 1.5kg/week)</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Maturity Progress</label>
                            <select name="maturity_progress" class="w-full border-gray-300 rounded-md shadow-sm focus:ring-purple-500 focus:border-purple-500">
                                <option value="" disabled selected>-- Select progress --</option>
                                <option value="early_growth">Early Growth (< 25kg)</option>
                                <option value="mid_growth">Mid Growth (25-35kg)</option>
                                <option value="late_growth">Late Growth (35-45kg)</option>
                                <option value="approaching_mature">Approaching Mature (45kg+)</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Expected Maturity</label>
                            <input type="date" name="expected_maturity_date"
                                   class="w-full border-gray-300 rounded-md shadow-sm focus:ring-purple-500 focus:border-purple-500"
                                   min="{{ date('Y-m-d') }}">
                        </div>
                    </div>

                    <div class="mt-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Growth Progress Notes</label>
                        <textarea name="growth_notes" rows="2"
                                  class="w-full border-gray-300 rounded-md shadow-sm focus:ring-purple-500 focus:border-purple-500"
                                  placeholder="Notes about growth rate, development milestones, feeding improvements..."></textarea>
                    </div>
                </div>

                <!-- Feed & Behavior Section -->
                <!-- <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-4">
                    <h4 class="text-md font-medium text-gray-900 mb-3">🌾 Feed & Behavior</h4>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Feed Type</label>
                            <select name="feed_type" class="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500">
                                <option value="" disabled selected>-- Select feed --</option>
                                <option value="starter">Starter Feed</option>
                                <option value="grower">Grower Feed</option>
                                <option value="developer">Developer Feed</option>
                                <option value="mixed">Mixed Feed</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Appetite</label>
                            <select name="appetite" class="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500">
                                <option value="" disabled selected>-- Select appetite --</option>
                                <option value="poor">Poor</option>
                                <option value="fair">Fair</option>
                                <option value="good">Good</option>
                                <option value="excellent">Excellent</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Social Behavior</label>
                            <select name="social_behavior" class="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500">
                                <option value="" disabled selected>-- Select behavior --</option>
                                <option value="shy">Shy</option>
                                <option value="normal">Normal</option>
                                <option value="social">Social</option>
                                <option value="dominant">Dominant</option>
                            </select>
                        </div>
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
                                  placeholder="Any notes about the weighing, piglet condition, behavior, growth observations..."></textarea>
                    </div>
                </div>

                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeWeightModal()"
                            class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                        Cancel
                    </button>
                    <button type="submit"
                            class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                        Update Weight & Progress
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Maturity Completion Modal -->
<div id="maturityModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-10 mx-auto p-6 border max-w-2xl shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex items-center justify-between mb-4">
                <h3 id="maturityModalTitle" class="text-lg font-semibold text-gray-900">Complete Weaning</h3>
                <button onclick="closeMaturityModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <form id="maturityForm" method="POST">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Left Column -->
                    <div class="space-y-4">
                        <h4 class="text-md font-semibold text-gray-800 border-b pb-2">Completion Details</h4>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Completion Date</label>
                            <input type="date" name="completion_date" required
                                   max="{{ now()->toDateString() }}"
                                   value="{{ now()->toDateString() }}"
                                   class="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500 text-sm">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Final Weight (kg)</label>
                            <input type="number" name="final_weight" step="0.1" min="0" max="500"
                                   class="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500 text-sm">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Health Status</label>
                            <select name="health_status" required
                                    class="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500 text-sm">
                                    <option value="" disabled selected>-- Select --</option>   
                                <option value="excellent">Excellent</option>
                                <option value="good">Good</option>
                                <option value="fair">Fair</option>
                                <option value="poor">Poor</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Body Condition Score (1-5)</label>
                            <select name="body_condition_score"
                                    class="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500 text-sm">
                                    <option value="" disabled selected>-- Select --</option>    
                                <option value="">Not assessed</option>
                                <option value="1">1 - Very thin</option>
                                <option value="2">2 - Thin</option>
                                <option value="3">3 - Ideal</option>
                                <option value="4">4 - Fat</option>
                                <option value="5">5 - Very fat</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Alive Male Piglets (optional)</label>
                            <input type="number" name="alive_males" min="0"
                                   class="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500 text-sm">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Alive Female Piglets (optional)</label>
                            <input type="number" name="alive_females" min="0"
                                   class="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500 text-sm">
                        </div>
                    </div>

                    <!-- Right Column -->
                    <div class="space-y-4">
                        <h4 class="text-md font-semibold text-gray-800 border-b pb-2">Next Steps</h4>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Next Destination</label>
                            <select name="next_destination" required
                                    class="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500 text-sm">
                                    <option value="" disabled selected>-- Select --</option> 
                                <option value="breeding">Breeding Stock</option>
                                <option value="market">Market Ready</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Growth Performance</label>
                            <select name="growth_performance"
                                    class="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500 text-sm">
                                    <option value="" disabled selected>-- Select --</option> 
                                <option value="excellent">Excellent</option>
                                <option value="good">Good</option>
                                <option value="average">Average</option>
                                <option value="poor">Poor</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Completion Notes</label>
                            <textarea name="completion_notes" rows="4"
                                      placeholder="Any observations about the pig's development, health, or recommendations..."
                                      class="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500 text-sm"></textarea>
                        </div>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="flex justify-between items-center pt-6 border-t mt-6">
                    <div class="text-sm text-gray-500">
                        This will complete the weaning process
                    </div>
                    <div class="flex space-x-3">
                        <button type="button" onclick="closeMaturityModal()"
                                class="px-6 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="submit"
                                class="px-6 py-2 bg-green-600 text-white rounded-md text-sm font-medium hover:bg-green-700">
                            Complete Weaning
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openMaturityModal(pigId, pigEarTag, ageInDays) {
    document.getElementById('maturityModalTitle').textContent = `Complete Weaning - ${pigEarTag}`;
    document.getElementById('maturityForm').action = `/weaning/${pigId}/complete-maturity`;
    document.getElementById('maturityModal').classList.remove('hidden');

    // Add pig information to the modal
    const form = document.getElementById('maturityForm');
    let pigInfo = form.querySelector('.pig-info');
    if (!pigInfo) {
        pigInfo = document.createElement('div');
        pigInfo.className = 'pig-info mb-4 p-3 bg-green-50 rounded-md border border-green-200';
        form.insertBefore(pigInfo, form.firstChild);
    }
    pigInfo.innerHTML = `
        <div class="text-sm text-green-800">
            <strong> WEANING COMPLETION</strong><br>
            <strong>Ear Tag:</strong> ${pigEarTag}<br>
            <strong>Current Age:</strong> ${Math.floor(ageInDays)} days<br>
            <strong>Target Weaning:</strong> {{ config('breeding.cycle.birth_to_weaning_days', 45) }} days<br>
            <strong>Status:</strong> Ready for completion
        </div>
    `;
}

function closeMaturityModal() {
    document.getElementById('maturityModal').classList.add('hidden');
    document.getElementById('maturityForm').reset();

    // Remove pig info
    const pigInfo = document.querySelector('.pig-info');
    if (pigInfo) {
        pigInfo.remove();
    }
}

// Close modal when clicking outside
document.getElementById('maturityModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeMaturityModal();
    }
});

// Weight Modal Functions
function openWeightModal(pigId, pigEarTag) {
    document.getElementById('weightForm').action = `/weaning/${pigId}/update-weight`;
    document.getElementById('weightInfo').innerHTML = `
        <div class="text-sm">
            <div class="font-medium text-gray-900 mb-2">In-Progress Piglet: ${pigEarTag}</div>
            <div class="text-gray-600">Update the current weight for this growing piglet</div>
            <div class="text-yellow-600 mt-1">📈 Track growth progress during maturation period</div>
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