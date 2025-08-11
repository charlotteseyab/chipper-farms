<x-app-layout>
    <main class="flex-1 p-8">
        <div class="max-w-4xl mx-auto">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-3xl font-bold text-gray-800">🐷💕 Mating Planner</h2>
                <div class="text-sm text-gray-600">
                    Plan safe and genetically diverse breeding pairs
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Mating Planner Form -->
                <div class="bg-white shadow-xl rounded-lg p-6">
                    <h3 class="text-xl font-semibold mb-4 text-gray-800">Plan a Mating</h3>
                    
                    <form method="POST" action="{{ route('mating.plan') }}" class="space-y-6">
                        @csrf
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Select Male</label>
                            <select name="male_id" id="male_id" required class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-pink-500 focus:border-pink-500">
                                <option value="">-- Choose Male --</option>
                                @foreach($allMales as $male)
                                    @php $availability = $male->availability_status; @endphp
                                    <option value="{{ $male->id }}" {{ old('male_id') == $male->id ? 'selected' : '' }}
                                            @if(!$availability['available']) disabled class="text-gray-400" @endif>
                                        {{ $availability['icon'] }} {{ $male->code }} - {{ $male->ear_tag_number }}
                                        ({{ $male->breed }}, {{ $male->current_age_in_days }} days)
                                        @if(!$availability['available'])
                                            - {{ $availability['reason'] }}
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Select Female</label>
                            <select name="female_id" id="female_id" required class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-pink-500 focus:border-pink-500">
                                <option value="">-- Choose Female --</option>
                                @foreach($allFemales as $female)
                                    @php $availability = $female->availability_status; @endphp
                                    <option value="{{ $female->id }}" {{ old('female_id') == $female->id ? 'selected' : '' }}
                                            @if(!$availability['available']) disabled class="text-gray-400" @endif>
                                        {{ $availability['icon'] }} {{ $female->code }} - {{ $female->ear_tag_number }}
                                        ({{ $female->breed }}, {{ $female->current_age_in_days }} days)
                                        @if(!$availability['available'])
                                            - {{ $availability['reason'] }}
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Inbreeding Alert Component -->
                        <x-inbreeding-alert id="mating-alert" />

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Planned Mating Date</label>
                            <input type="date" name="planned_date" value="{{ old('planned_date', now()->addDays(7)->toDateString()) }}" 
                                   min="{{ now()->toDateString() }}" required 
                                   class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-pink-500 focus:border-pink-500">
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Notes (optional)</label>
                            <textarea name="notes" rows="3" placeholder="Any special considerations or breeding goals..." 
                                      class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-pink-500 focus:border-pink-500">{{ old('notes') }}</textarea>
                        </div>

                        <div class="pt-4">
                            <button type="submit" id="submit-button" class="w-full py-3 px-6 bg-pink-600 text-white font-bold rounded-lg shadow hover:bg-pink-700 transition">
                                Plan This Mating
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Information Panel -->
                <div class="space-y-6">
                    <!-- Selected Pair Info -->
                    <div id="pair-info" class="bg-gray-50 rounded-lg p-6 hidden">
                        <h3 class="text-lg font-semibold mb-4 text-gray-800">Selected Breeding Pair</h3>
                        <div class="grid grid-cols-2 gap-4">
                            <div id="male-info" class="bg-blue-100 p-4 rounded-lg">
                                <h4 class="font-semibold text-blue-800 mb-2">♂ Male</h4>
                                <div id="male-details" class="text-sm text-blue-700"></div>
                            </div>
                            <div id="female-info" class="bg-pink-100 p-4 rounded-lg">
                                <h4 class="font-semibold text-pink-800 mb-2">♀ Female</h4>
                                <div id="female-details" class="text-sm text-pink-700"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Breeding Timeline Calculator -->
                    <!-- <div id="breeding-timeline" class="bg-white shadow-lg rounded-lg p-6 hidden">
                        <h3 class="text-lg font-semibold mb-4 text-gray-800">📅 Breeding Timeline</h3>
                        <div class="space-y-3 text-sm">
                            <div class="flex justify-between items-center py-2 border-b border-gray-100">
                                <span class="text-gray-600">Mating Date:</span>
                                <span id="timeline-mating" class="font-medium text-gray-900">-</span>
                            </div>
                            <div class="flex justify-between items-center py-2 border-b border-gray-100">
                                <span class="text-gray-600">Pregnancy Check:</span>
                                <span id="timeline-pregnancy" class="font-medium text-blue-600">-</span>
                            </div>
                            <div class="flex justify-between items-center py-2 border-b border-gray-100">
                                <span class="text-gray-600">Expected Farrowing:</span>
                                <span id="timeline-farrowing" class="font-medium text-green-600">-</span>
                            </div>
                            <div class="flex justify-between items-center py-2 border-b border-gray-100">
                                <span class="text-gray-600">Expected Weaning:</span>
                                <span id="timeline-weaning" class="font-medium text-purple-600">-</span>
                            </div>
                            <div class="flex justify-between items-center py-2">
                                <span class="text-gray-600">Next Mating Available:</span>
                                <span id="timeline-next" class="font-medium text-orange-600">-</span>
                            </div>
                            <div class="mt-4 p-3 bg-blue-50 rounded-lg">
                                <div class="flex justify-between items-center">
                                    <span class="text-sm font-medium text-blue-800">Total Cycle Length:</span>
                                    <span id="timeline-cycle" class="text-sm font-bold text-blue-900">-</span>
                                </div>
                            </div>
                        </div>
                    </div> -->

                    <!-- Breeding Guidelines -->
                    <div class="bg-white shadow-lg rounded-lg p-6">
                        <h3 class="text-lg font-semibold mb-4 text-gray-800">🧬 Breeding Guidelines</h3>
                        <div class="space-y-3 text-sm text-gray-600">
                            <div class="flex items-start gap-2">
                                <span class="text-green-500 font-bold">✓</span>
                                <span><strong>Safe:</strong> No close relationships - promotes genetic diversity</span>
                            </div>
                            <div class="flex items-start gap-2">
                                <span class="text-blue-500 font-bold">ℹ</span>
                                <span><strong>Caution:</strong> Distant relatives - acceptable with monitoring</span>
                            </div>
                            <div class="flex items-start gap-2">
                                <span class="text-yellow-500 font-bold">⚠</span>
                                <span><strong>Warning:</strong> Close relatives - requires careful consideration</span>
                            </div>
                            <div class="flex items-start gap-2">
                                <span class="text-red-500 font-bold">🚫</span>
                                <span><strong>Critical:</strong> Direct relatives - not recommended</span>
                            </div>
                        </div>
                    </div>

                    <!-- Breeding Cycle Info -->
                    <div class="bg-white shadow-lg rounded-lg p-6">
                        <h3 class="text-lg font-semibold mb-4 text-gray-800">📊 Breeding Cycle Info</h3>
                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Gestation:</span>
                                <span class="font-medium">{{ config('breeding.cycle.gestation_days') }} days</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Pregnancy Check:</span>
                                <span class="font-medium">{{ config('breeding.cycle.pregnancy_check_days') }} days</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Weaning Age:</span>
                                <span class="font-medium">{{ config('breeding.cycle.birth_to_weaning_days') }} days</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Breeding Age:</span>
                                <span class="font-medium">{{ config('breeding.cycle.birth_to_maturity_days') }} days</span>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Stats -->
                    <div class="bg-white shadow-lg rounded-lg p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold text-gray-800">📊 Available Stock</h3>
                            <div class="group relative">
                                <svg class="w-4 h-4 text-gray-400 cursor-help" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <div class="absolute right-0 top-6 w-64 p-3 bg-gray-900 text-white text-xs rounded-lg opacity-0 group-hover:opacity-100 transition-opacity z-10">
                                    <strong>Available for Breeding:</strong><br>
                                    • Meets minimum age requirements<br>
                                    • Has adequate weight (if recorded)<br>
                                    • Not currently in planned mating<br>
                                    • Not pregnant or awaiting pregnancy check
                                </div>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4 text-center">
                            <div class="bg-blue-50 p-4 rounded-lg">
                                <div class="text-2xl font-bold text-blue-600">{{ count($availableMales) }}</div>
                                <div class="text-sm text-blue-700">Males Available</div>
                                <div class="text-xs text-blue-600">{{ count($allMales) }} total</div>
                            </div>
                            <div class="bg-pink-50 p-4 rounded-lg">
                                <div class="text-2xl font-bold text-pink-600">{{ count($availableFemales) }}</div>
                                <div class="text-sm text-pink-700">Females Available</div>
                                <div class="text-xs text-pink-600">{{ count($allFemales) }} total</div>
                            </div>
                        </div>

                        <!-- Availability Breakdown -->
                        <div class="mt-4 pt-4 border-t border-gray-200">
                            <h4 class="text-sm font-medium text-gray-700 mb-2">Availability Status</h4>
                            <div class="grid grid-cols-2 gap-2 text-xs">
                                <div class="space-y-1">
                                    <div class="text-gray-600">Males:</div>
                                    @php
                                        $maleStatuses = $allMales->groupBy(function($pig) {
                                            return $pig->availability_status['status'];
                                        });
                                    @endphp
                                    @foreach($maleStatuses as $status => $pigs)
                                        <div class="flex justify-between">
                                            <span class="capitalize">{{ str_replace('_', ' ', $status) }}:</span>
                                            <span class="font-medium">{{ count($pigs) }}</span>
                                        </div>
                                    @endforeach
                                </div>
                                <div class="space-y-1">
                                    <div class="text-gray-600">Females:</div>
                                    @php
                                        $femaleStatuses = $allFemales->groupBy(function($pig) {
                                            return $pig->availability_status['status'];
                                        });
                                    @endphp
                                    @foreach($femaleStatuses as $status => $pigs)
                                        <div class="flex justify-between">
                                            <span class="capitalize">{{ str_replace('_', ' ', $status) }}:</span>
                                            <span class="font-medium">{{ count($pigs) }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const maleSelect = document.getElementById('male_id');
            const femaleSelect = document.getElementById('female_id');
            const alert = new InbreedingAlert('mating-alert');
            const pairInfo = document.getElementById('pair-info');
            const maleDetails = document.getElementById('male-details');
            const femaleDetails = document.getElementById('female-details');
            const breedingTimeline = document.getElementById('breeding-timeline');
            const plannedDateInput = document.querySelector('input[name="planned_date"]');
            let checkTimeout;

            // Store pig data for quick access
            const males = @json($males);
            const females = @json($females);

            // Helper functions for breeding readiness badges
            function getMaleReadinessBadge(readiness) {
                const status = readiness.age_status;
                let badgeClass = '';
                let icon = '';
                let text = '';

                switch(status) {
                    case 'optimal':
                        badgeClass = 'bg-green-100 text-green-800';
                        icon = '✓';
                        text = 'Optimal';
                        break;
                    case 'minimum_met':
                        badgeClass = 'bg-yellow-100 text-yellow-800';
                        icon = '⚠';
                        text = 'Ready';
                        break;
                    case 'too_young':
                        badgeClass = 'bg-red-100 text-red-800';
                        icon = '✗';
                        text = 'Too Young';
                        break;
                    case 'too_old':
                        badgeClass = 'bg-gray-100 text-gray-800';
                        icon = '⚠';
                        text = 'Too Old';
                        break;
                    default:
                        badgeClass = 'bg-gray-100 text-gray-800';
                        icon = '?';
                        text = 'Unknown';
                }

                return `<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ${badgeClass}">${icon} ${text}</span>`;
            }

            function getFemaleReadinessBadge(readiness) {
                return getMaleReadinessBadge(readiness); // Same logic for now
            }

            // Breeding cycle configuration
            const breedingConfig = {
                gestationDays: {{ config('breeding.cycle.gestation_days') }},
                pregnancyCheckDays: {{ config('breeding.cycle.pregnancy_check_days') }},
                weaningDays: {{ config('breeding.cycle.birth_to_weaning_days') }},
                restDays: {{ config('breeding.parameters.weaning_to_mating_rest') }}
            };

            function updatePairInfo() {
                const selectedMale = males.find(m => m.id == maleSelect.value);
                const selectedFemale = females.find(f => f.id == femaleSelect.value);

                if (selectedMale && selectedFemale) {
                    // Male breeding readiness badge
                    const maleReadinessBadge = getMaleReadinessBadge(selectedMale.breeding_readiness);
                    const femaleReadinessBadge = getFemaleReadinessBadge(selectedFemale.breeding_readiness);

                    maleDetails.innerHTML = `
                        <div class="flex items-center justify-between mb-2">
                            <div><strong>${selectedMale.code}</strong> - ${selectedMale.ear_tag_number}</div>
                            ${maleReadinessBadge}
                        </div>
                        <div class="text-sm text-gray-600">Breed: ${selectedMale.breed}</div>
                        <div class="text-sm text-gray-600">Age: ${Math.floor(selectedMale.current_age_in_days / 30)} months (${selectedMale.current_age_in_days} days)</div>
                        <div class="text-sm text-gray-600">Type: ${selectedMale.stock_type}</div>
                        ${selectedMale.breeding_readiness.age_status !== 'optimal' ? `<div class="text-xs text-gray-500 mt-2">${selectedMale.readiness_message}</div>` : ''}
                    `;

                    femaleDetails.innerHTML = `
                        <div class="flex items-center justify-between mb-2">
                            <div><strong>${selectedFemale.code}</strong> - ${selectedFemale.ear_tag_number}</div>
                            ${femaleReadinessBadge}
                        </div>
                        <div class="text-sm text-gray-600">Breed: ${selectedFemale.breed}</div>
                        <div class="text-sm text-gray-600">Age: ${Math.floor(selectedFemale.current_age_in_days / 30)} months (${selectedFemale.current_age_in_days} days)</div>
                        <div class="text-sm text-gray-600">Type: ${selectedFemale.stock_type}</div>
                        ${selectedFemale.breeding_readiness.age_status !== 'optimal' ? `<div class="text-xs text-gray-500 mt-2">${selectedFemale.readiness_message}</div>` : ''}
                    `;
                    
                    pairInfo.classList.remove('hidden');
                } else {
                    pairInfo.classList.add('hidden');
                }
            }

            function updateBreedingTimeline() {
                const plannedDate = plannedDateInput.value;

                if (!plannedDate) {
                    breedingTimeline.classList.add('hidden');
                    return;
                }

                // Calculate dates
                const matingDate = new Date(plannedDate);
                const pregnancyCheckDate = new Date(matingDate);
                pregnancyCheckDate.setDate(matingDate.getDate() + breedingConfig.pregnancyCheckDays);

                const farrowingDate = new Date(matingDate);
                farrowingDate.setDate(matingDate.getDate() + breedingConfig.gestationDays);

                const weaningDate = new Date(farrowingDate);
                weaningDate.setDate(farrowingDate.getDate() + breedingConfig.weaningDays);

                const nextMatingDate = new Date(weaningDate);
                nextMatingDate.setDate(weaningDate.getDate() + breedingConfig.restDays);

                const cycleLengthDays = Math.ceil((nextMatingDate - matingDate) / (1000 * 60 * 60 * 24));

                // Format dates
                const formatDate = (date) => {
                    return date.toLocaleDateString('en-US', {
                        month: 'short',
                        day: 'numeric',
                        year: 'numeric'
                    });
                };

                // Update timeline display
                document.getElementById('timeline-mating').textContent = formatDate(matingDate);
                document.getElementById('timeline-pregnancy').textContent = formatDate(pregnancyCheckDate);
                document.getElementById('timeline-farrowing').textContent = formatDate(farrowingDate);
                document.getElementById('timeline-weaning').textContent = formatDate(weaningDate);
                document.getElementById('timeline-next').textContent = formatDate(nextMatingDate);
                document.getElementById('timeline-cycle').textContent = `${cycleLengthDays} days`;

                breedingTimeline.classList.remove('hidden');
            }

            function checkMatingCompatibility() {
                const maleId = maleSelect.value;
                const femaleId = femaleSelect.value;

                clearTimeout(checkTimeout);

                if (!maleId || !femaleId) {
                    alert.hide();
                    return;
                }

                checkTimeout = setTimeout(() => {
                    fetch('{{ route("check-relationship") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({
                            father_id: maleId,
                            mother_id: femaleId
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.risk_level && data.risk_level !== 'none') {
                            alert.show(data);
                        } else {
                            alert.show({
                                risk_level: 'success',
                                message: 'Excellent breeding pair! No close relationships detected. This mating will promote genetic diversity.'
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Error checking compatibility:', error);
                        alert.hide();
                    });
                }, 300);
            }

            // Add event listeners
            maleSelect.addEventListener('change', function() {
                updatePairInfo();
                checkMatingCompatibility();
            });

            femaleSelect.addEventListener('change', function() {
                updatePairInfo();
                checkMatingCompatibility();
            });

            plannedDateInput.addEventListener('change', updateBreedingTimeline);

            // Check on page load if values are pre-selected
            if (maleSelect.value && femaleSelect.value) {
                updatePairInfo();
                checkMatingCompatibility();
            }

            // Update timeline on page load if date is set
            if (plannedDateInput.value) {
                updateBreedingTimeline();
            }
        });
    </script>
</x-app-layout>
