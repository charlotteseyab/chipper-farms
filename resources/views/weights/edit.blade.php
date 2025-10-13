<x-app-layout>
    <main class="flex-1 pl-8 pr-4 pt-8 pb-8">
        <div class="max-w-2xl mx-auto bg-white shadow-xl rounded-lg p-8">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-bold text-blue-600">Edit Weight Record</h2>
                <div class="flex gap-2">
                    <a href="{{ route('weights.index') }}" class="px-3 py-1 bg-gray-600 text-white rounded text-sm hover:bg-gray-700">
                        ← Back to Records
                    </a>
                </div>
            </div>

            <!-- Weight Edit Form -->
            <div class="bg-white">
                <form method="POST" action="{{ route('weights.update', $weight) }}" class="space-y-6">
                    @csrf
                    @method('PUT')

                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                        <div class="flex items-center">
                            <svg class="h-5 w-5 text-blue-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                            </svg>
                            <div>
                                <h4 class="text-sm font-medium text-blue-800">Edit Weight Record</h4>
                                <p class="text-sm text-blue-700 mt-1">Update weight information for {{ $weight->pig->ear_tag_number }} - {{ $weight->pig->code }}.</p>
                            </div>
                        </div>
                    </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Select Pig</label>
                    <select name="pig_id" id="pig_id" required class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-600 focus:border-blue-600">
                        <option value="" disabled>-- Select Pig --</option>
                        @foreach($pigs as $pig)
                            <option value="{{ $pig->id }}" 
                                    data-ear-tag="{{ $pig->ear_tag_number }}"
                                    data-code="{{ $pig->code }}"
                                    data-breed="{{ $pig->breed }}"
                                    data-gender="{{ $pig->gender }}"
                                    data-age="{{ $pig->current_age_in_days }}"
                                    data-birth-date="{{ $pig->date_of_birth ? $pig->date_of_birth->toDateString() : '' }}"
                                    data-stock-type="{{ $pig->stock_type }}"
                                    {{ ($selectedPig && $selectedPig->id == $pig->id) || old('pig_id', $weight->pig_id) == $pig->id ? 'selected' : '' }}>
                                {{ $pig->ear_tag_number }} - {{ $pig->code }} ({{ $pig->breed }}, {{ $pig->gender === 'M' ? 'Male' : 'Female' }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Enhanced Pig Info Display -->
                <div id="pig-info" class="hidden bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <h4 class="text-sm font-medium text-blue-800 mb-3">📋 Selected Pig Information</h4>
                    <div class="grid grid-cols-2 gap-4 text-sm mb-4">
                        <div>
                            <span class="text-blue-600 font-medium">Ear Tag:</span>
                            <span id="info-ear-tag" class="text-blue-800 font-semibold"></span>
                        </div>
                        <div>
                            <span class="text-blue-600 font-medium">Code:</span>
                            <span id="info-code" class="text-blue-800"></span>
                        </div>
                        <div>
                            <span class="text-blue-600 font-medium">Breed:</span>
                            <span id="info-breed" class="text-blue-800"></span>
                        </div>
                        <div>
                            <span class="text-blue-600 font-medium">Gender:</span>
                            <span id="info-gender" class="text-blue-800"></span>
                        </div>
                        <div>
                            <span class="text-blue-600 font-medium">Age:</span>
                            <span id="info-age" class="text-blue-800"></span> days
                            (<span id="info-age-weeks" class="text-blue-800 font-semibold"></span> weeks)
                        </div>
                        <div>
                            <span class="text-blue-600 font-medium">Stock Type:</span>
                            <span id="info-stock-type" class="text-blue-800"></span>
                        </div>
                    </div>

                    <!-- Weight Standards & History -->
                    <div class="border-t border-blue-200 pt-3">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- Expected Weight -->
                            <div id="expected-weight-info" class="bg-green-50 border border-green-200 rounded p-3">
                                <h5 class="text-sm font-medium text-green-800 mb-2">📊 Expected Weight (This Week)</h5>
                                <div id="expected-weight-display" class="text-sm text-green-700">
                                    <div>Range: <span id="expected-range" class="font-semibold">-</span></div>
                                    <div>Optimal: <span id="expected-optimal" class="font-semibold">-</span></div>
                                </div>
                                <div id="no-standard-message" class="text-sm text-gray-500 hidden">
                                    No weight standard set for this pig type/age
                                </div>
                                <div id="standard-loading" class="text-sm text-green-600 hidden">
                                    <svg class="animate-spin -ml-1 mr-2 h-4 w-4 inline" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    Loading standards...
                                </div>
                            </div>

                            <!-- Previous Weight -->
                            <div id="previous-weight-info" class="bg-yellow-50 border border-yellow-200 rounded p-3">
                                <h5 class="text-sm font-medium text-yellow-800 mb-2">📈 Previous Weight</h5>
                                <div id="previous-weight-display" class="text-sm text-yellow-700">
                                    <div>Last: <span id="previous-weight" class="font-semibold">-</span></div>
                                    <div>Date: <span id="previous-date" class="font-semibold">-</span></div>
                                    <div>Days Ago: <span id="days-since" class="font-semibold">-</span></div>
                                </div>
                                <div id="no-previous-message" class="text-sm text-gray-500 hidden">
                                    No previous weight recorded
                                </div>
                                <div id="previous-loading" class="text-sm text-yellow-600 hidden">
                                    <svg class="animate-spin -ml-1 mr-2 h-4 w-4 inline" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    Loading history...
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Weight Input with Smart Guidance -->
                <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                    <h4 class="text-sm font-medium text-gray-800 mb-3">⚖️ Weight Entry</h4>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Weight (kg)</label>
                            <div class="relative">
                                <input type="number" name="weight_kg" id="weight_kg" step="0.1" min="0.1" max="500" required
                                       value="{{ old('weight_kg', $weight->weight) }}"
                                       class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-600 focus:border-blue-600 pr-16"
                                       placeholder="Enter weight">
                                <span class="absolute right-3 top-2 text-gray-500 text-sm">kg</span>
                            </div>

                            <!-- Real-time Weight Feedback -->
                            <div id="weight-feedback" class="mt-2 text-sm hidden">
                                <div id="weight-status" class="flex items-center gap-2">
                                    <span id="weight-icon"></span>
                                    <span id="weight-message"></span>
                                </div>
                                <div id="weight-comparison" class="text-xs text-gray-600 mt-1"></div>
                            </div>

                            <p class="text-xs text-gray-500 mt-1">
                                <span id="weight-lbs-display"></span>
                                <span class="text-gray-400">• Weekly updates recommended</span>
                            </p>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Purpose</label>
                            <select name="purpose" required class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-600 focus:border-blue-600">
                                <option value="" disabled>-- Select Purpose --</option>
                                <option value="routine" {{ old('purpose', $weight->purpose) == 'routine' ? 'selected' : '' }} >📅 Weekly Routine</option>
                                <option value="breeding" {{ old('purpose', $weight->purpose) == 'breeding' ? 'selected' : '' }}>💕 Breeding Assessment</option>
                                <option value="health" {{ old('purpose', $weight->purpose) == 'health' ? 'selected' : '' }}>🏥 Health Check</option>
                                <option value="sale" {{ old('purpose', $weight->purpose) == 'sale' ? 'selected' : '' }}>💰 Pre-Sale</option>
                                <option value="birth" {{ old('purpose', $weight->purpose) == 'birth' ? 'selected' : '' }}>🐷 Birth Weight</option>
                                <option value="weaning" {{ old('purpose', $weight->purpose) == 'weaning' ? 'selected' : '' }}>🍼 Weaning Weight</option>
                                <option value="maturity" {{ old('purpose', $weight->purpose) == 'maturity' ? 'selected' : '' }}>🎯 Maturity Weight</option>
                                <option value="other" {{ old('purpose', $weight->purpose) == 'other' ? 'selected' : '' }}>📝 Other</option>
                            </select>
                            <p class="text-xs text-gray-500 mt-1">Most common: Weekly Routine for growth tracking</p>
                        </div>
                    </div>
                </div>

                <!-- Week Selection and Date -->
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                    <h4 class="text-sm font-medium text-yellow-800 mb-3">📅 Weekly Tracking</h4>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Target Week</label>
                            <select name="target_week" id="target_week" required class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-600 focus:border-blue-600">
                                <option value="" disabled>-- Select Week --</option>
                                @for($week = 1; $week <= 104; $week++)
                                    <option value="{{ $week }}" {{ old('target_week', $weight->target_week) == $week ? 'selected' : '' }}>
                                        Week {{ $week }} ({{ $week * 7 }} days)
                                    </option>
                                @endfor
                            </select>
                            <p class="text-xs text-yellow-600 mt-1">Which week are you recording for?</p>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Weighing Date</label>
                            <input type="date" name="weigh_date" id="weigh_date" required max="{{ now()->toDateString() }}"
                                   value="{{ old('weigh_date', $weight->weigh_date->toDateString()) }}"
                                   class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-600 focus:border-blue-600">
                            <p class="text-xs text-gray-500 mt-1">Actual date weighed</p>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Weighing Time (optional)</label>
                            <div class="flex gap-2">
                                <input type="time" name="weigh_time" id="weigh_time"
                                       value="{{ old('weigh_time', $weight->weigh_time ?? now()->format('H:i')) }}"
                                       class="flex-1 border-gray-300 rounded-lg shadow-sm focus:ring-blue-600 focus:border-blue-600">
                                <!-- <button type="button" onclick="setCurrentTime()" 
                                        class="px-3 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm hover:bg-gray-200 border border-gray-300">
                                    🕐 Now
                                </button> -->
                            </div>
                            <p class="text-xs text-gray-500 mt-1">Time of weighing (auto-filled with current time)</p>
                        </div>
                    </div>

                    <!-- Week Comparison Info -->
                    <div id="week-comparison" class="mt-4 p-3 bg-white border border-yellow-200 rounded hidden">
                        <div class="flex items-center justify-between text-sm">
                            <div>
                                <span class="text-yellow-700 font-medium">Target Week:</span>
                                <span id="target-week-display" class="text-yellow-800">-</span>
                            </div>
                            <div>
                                <span class="text-yellow-700 font-medium">Pig's Actual Age:</span>
                                <span id="actual-age-display" class="text-yellow-800">-</span>
                            </div>
                            <div id="week-variance" class="text-xs">
                                <!-- Shows if pig is early/late for target week -->
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Body Condition Score (optional)</label>
                        <select name="body_condition_score" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-600 focus:border-blue-600">
                            <option value="" disabled>-- Not Assessed --</option>
                            <option value="1" {{ old('body_condition_score', $weight->body_condition_score) == '1' ? 'selected' : '' }}>1 - Very Thin</option>
                            <option value="2" {{ old('body_condition_score', $weight->body_condition_score) == '2' ? 'selected' : '' }}>2 - Thin</option>
                            <option value="3" {{ old('body_condition_score', $weight->body_condition_score) == '3' ? 'selected' : '' }}>3 - Ideal</option>
                            <option value="4" {{ old('body_condition_score', $weight->body_condition_score) == '4' ? 'selected' : '' }}>4 - Fat</option>
                            <option value="5" {{ old('body_condition_score', $weight->body_condition_score) == '5' ? 'selected' : '' }}>5 - Very Fat</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Health Status (optional)</label>
                        <select name="health_status" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-600 focus:border-blue-600">
                            <option value="" disabled>-- Not Assessed --</option>
                            <option value="excellent" {{ old('health_status', $weight->health_status) == 'excellent' ? 'selected' : '' }}>Excellent</option>
                            <option value="good" {{ old('health_status', $weight->health_status) == 'good' ? 'selected' : '' }}>Good</option>
                            <option value="fair" {{ old('health_status', $weight->health_status) == 'fair' ? 'selected' : '' }}>Fair</option>
                            <option value="poor" {{ old('health_status', $weight->health_status) == 'poor' ? 'selected' : '' }}>Poor</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Notes (optional)</label>
                    <textarea name="notes" rows="3" 
                              class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-600 focus:border-blue-600"
                              placeholder="Any additional observations or notes about the weighing...">{{ old('notes', $weight->notes) }}</textarea>
                </div>

                <div class="pt-4">
                    <button type="submit" class="w-full py-3 px-6 bg-blue-600 text-white font-bold rounded-lg shadow hover:bg-blue-700 transition">Update Weight Record</button>
                </div>

                <div class="text-center">
                    <a href="{{ route('weights.index') }}" class="text-gray-600 hover:underline">← Back to Weight Records</a>
                </div>
            </form>
            </div>
        </div>
        <x-copyright-minimal />
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const pigSelect = document.getElementById('pig_id');
            const pigInfo = document.getElementById('pig-info');
            const weightInput = document.getElementById('weight_kg');
            const weightFeedback = document.getElementById('weight-feedback');
            const targetWeekSelect = document.getElementById('target_week');
            const weighDateInput = document.getElementById('weigh_date');
            const weekComparison = document.getElementById('week-comparison');

            function updatePigInfo() {
                const selectedOption = pigSelect.options[pigSelect.selectedIndex];

                if (selectedOption.value) {
                    // Show pig info
                    pigInfo.classList.remove('hidden');

                    // Update basic info fields
                    document.getElementById('info-ear-tag').textContent = selectedOption.dataset.earTag;
                    document.getElementById('info-code').textContent = selectedOption.dataset.code;
                    document.getElementById('info-breed').textContent = selectedOption.dataset.breed;
                    document.getElementById('info-gender').textContent = selectedOption.dataset.gender === 'M' ? 'Male' : 'Female';
                    document.getElementById('info-age').textContent = selectedOption.dataset.age + ' days';
                    document.getElementById('info-age-weeks').textContent = Math.floor(selectedOption.dataset.age / 7);
                    document.getElementById('info-stock-type').textContent = selectedOption.dataset.stockType.charAt(0).toUpperCase() + selectedOption.dataset.stockType.slice(1);

                    // Load expected weight standards and previous weight
                    loadWeightStandards(selectedOption.value);
                    loadPreviousWeight(selectedOption.value);
                    
                    // Clear any previous weight feedback
                    weightFeedback.classList.add('hidden');
                    window.currentWeightStandard = null;
                    window.previousWeight = null;
                } else {
                    // Hide pig info
                    pigInfo.classList.add('hidden');
                    weightFeedback.classList.add('hidden');
                }
            }

            function loadWeightStandards(pigId) {
                // Show loading state
                document.getElementById('expected-range').textContent = 'Loading...';
                document.getElementById('expected-optimal').textContent = 'Loading...';
                document.getElementById('no-standard-message').classList.add('hidden');
                document.getElementById('standard-loading').classList.remove('hidden');

                // Make AJAX call to get expected weight standards
                fetch(`/api/pigs/${pigId}/expected-weight`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.data) {
                            const standard = data.data;
                            document.getElementById('expected-range').textContent = 
                                `${standard.min_weight} - ${standard.max_weight} kg`;
                            document.getElementById('expected-optimal').textContent = 
                                `${standard.optimal_weight} kg`;
                            
                            // Store the standard data for weight feedback
                            window.currentWeightStandard = standard;
                        } else {
                            document.getElementById('expected-range').textContent = 'N/A';
                            document.getElementById('expected-optimal').textContent = 'N/A';
                            document.getElementById('no-standard-message').classList.remove('hidden');
                            window.currentWeightStandard = null;
                        }
                        document.getElementById('standard-loading').classList.add('hidden');
                    })
                    .catch(error => {
                        console.error('Error loading weight standards:', error);
                        document.getElementById('expected-range').textContent = 'Error';
                        document.getElementById('expected-optimal').textContent = 'Error';
                        window.currentWeightStandard = null;
                        document.getElementById('standard-loading').classList.add('hidden');
                    });
            }

            function loadPreviousWeight(pigId) {
                // Show loading state
                document.getElementById('previous-weight').textContent = 'Loading...';
                document.getElementById('previous-date').textContent = 'Loading...';
                document.getElementById('days-since').textContent = 'Loading...';
                document.getElementById('no-previous-message').classList.add('hidden');
                document.getElementById('previous-loading').classList.remove('hidden');

                // Make AJAX call to get previous weight
                fetch(`/api/pigs/${pigId}/previous-weight`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.data) {
                            const previous = data.data;
                            document.getElementById('previous-weight').textContent = 
                                `${previous.weight} kg`;
                            document.getElementById('previous-date').textContent = 
                                previous.date;
                            document.getElementById('days-since').textContent = 
                                Math.floor(previous.days_since); // Always rounds down

                            
                            // Store the previous weight data for comparison
                            window.previousWeight = previous;
                        } else {
                            document.getElementById('previous-weight').textContent = 'N/A';
                            document.getElementById('previous-date').textContent = 'N/A';
                            document.getElementById('days-since').textContent = 'N/A';
                            document.getElementById('no-previous-message').classList.remove('hidden');
                            window.previousWeight = null;
                        }
                        document.getElementById('previous-loading').classList.add('hidden');
                    })
                    .catch(error => {
                        console.error('Error loading previous weight:', error);
                        document.getElementById('previous-weight').textContent = 'Error';
                        document.getElementById('previous-date').textContent = 'Error';
                        document.getElementById('days-since').textContent = 'Error';
                        window.previousWeight = null;
                        document.getElementById('previous-loading').classList.add('hidden');
                    });
            }

            function updateWeekComparison() {
                const selectedOption = pigSelect.options[pigSelect.selectedIndex];
                const targetWeek = parseInt(targetWeekSelect.value);
                const weighDate = new Date(weighDateInput.value);

                if (!selectedOption.value || !targetWeek || !weighDate) {
                    weekComparison.classList.add('hidden');
                    return;
                }

                // Calculate pig's actual age on the specific weigh date
                const birthDateStr = selectedOption.dataset.birthDate;
                let actualWeeks = 0;
                let ageInDays = 0;

                if (birthDateStr) {
                    const birthDate = new Date(birthDateStr);
                    ageInDays = Math.floor((weighDate - birthDate) / (1000 * 60 * 60 * 24));
                    actualWeeks = Math.floor(ageInDays / 7);
                } else {
                    // Fallback to current age if no birth date
                    ageInDays = parseInt(selectedOption.dataset.age) || 0;
                    actualWeeks = Math.floor(ageInDays / 7);
                }

                // Show comparison
                weekComparison.classList.remove('hidden');
                document.getElementById('target-week-display').textContent = `Week ${targetWeek}`;
                document.getElementById('actual-age-display').textContent = `Week ${actualWeeks} (${ageInDays} days)`;

                // Show variance
                const weekVariance = document.getElementById('week-variance');
                const weekDiff = actualWeeks - targetWeek;

                if (weekDiff === 0) {
                    weekVariance.innerHTML = '<span class="text-green-600">✅ Perfect timing</span>';
                } else if (weekDiff > 0) {
                    weekVariance.innerHTML = `<span class="text-orange-600">⚠️ ${weekDiff} week(s) late</span>`;
                } else {
                    weekVariance.innerHTML = `<span class="text-blue-600">📅 ${Math.abs(weekDiff)} week(s) early</span>`;
                }

                // Auto-suggest target week based on actual age
                if (Math.abs(weekDiff) > 1) {
                    weekVariance.innerHTML += `<br><button type="button" onclick="setTargetWeek(${actualWeeks})" class="text-xs text-blue-600 hover:underline">Use Week ${actualWeeks} instead?</button>`;
                }
            }

            function setTargetWeek(week) {
                targetWeekSelect.value = week;
                updateWeekComparison();
                updateWeightFeedback();
            }

            function updateWeightFeedback() {
                const weight = parseFloat(weightInput.value);
                const targetWeek = parseInt(targetWeekSelect.value);

                if (!weight || !pigSelect.value || !targetWeek) {
                    weightFeedback.classList.add('hidden');
                    return;
                }

                // Show weight in pounds
                const weightLbs = (weight * 2.20462).toFixed(1);
                document.getElementById('weight-lbs-display').textContent = `≈ ${weightLbs} lbs`;

                // Show feedback based on weight standards
                weightFeedback.classList.remove('hidden');

                // Use the loaded weight standard data
                if (window.currentWeightStandard) {
                    const standard = window.currentWeightStandard;
                    
                    if (weight < standard.min_weight) {
                        document.getElementById('weight-icon').textContent = '⬇️';
                        document.getElementById('weight-message').textContent = `Under expected weight for Week ${standard.week_number}`;
                        document.getElementById('weight-message').className = 'text-blue-600 font-medium';
                        const deviation = ((standard.min_weight - weight) / standard.min_weight * 100).toFixed(1);
                        document.getElementById('weight-comparison').textContent = `${deviation}% below minimum (${standard.min_weight} kg)`;
                    } else if (weight > standard.max_weight) {
                        document.getElementById('weight-icon').textContent = '⬆️';
                        document.getElementById('weight-message').textContent = `Over expected weight for Week ${standard.week_number}`;
                        document.getElementById('weight-message').className = 'text-orange-600 font-medium';
                        const deviation = ((weight - standard.max_weight) / standard.max_weight * 100).toFixed(1);
                        document.getElementById('weight-comparison').textContent = `${deviation}% above maximum (${standard.max_weight} kg)`;
                    } else {
                        document.getElementById('weight-icon').textContent = '✅';
                        document.getElementById('weight-message').textContent = `Good weight for Week ${standard.week_number}`;
                        document.getElementById('weight-message').className = 'text-green-600 font-medium';
                        document.getElementById('weight-comparison').textContent = `Within expected range (${standard.min_weight}-${standard.max_weight} kg)`;
                    }

                    // Add note if using closest standard
                    if (standard.note && standard.note.includes('closest')) {
                        document.getElementById('weight-comparison').textContent += ` - ${standard.note}`;
                    }
                } else {
                    // Fallback if no standard data available
                    document.getElementById('weight-icon').textContent = '❓';
                    document.getElementById('weight-message').textContent = 'No weight standard available';
                    document.getElementById('weight-message').className = 'text-gray-600 font-medium';
                    document.getElementById('weight-comparison').textContent = 'Weight standard not set for this pig type/age';
                }

                // Compare with previous weight if available
                if (window.previousWeight) {
                    const previous = window.previousWeight;
                    const weightChange = weight - previous.weight;
                    const changePercent = ((weightChange / previous.weight) * 100).toFixed(1);
                    
                    let changeText = '';
                    if (weightChange > 0) {
                        changeText = `📈 +${weightChange.toFixed(1)} kg (+${changePercent}%) since last weigh`;
                    } else if (weightChange < 0) {
                        changeText = `📉 ${weightChange.toFixed(1)} kg (${changePercent}%) since last weigh`;
                    } else {
                        changeText = `➡️ No change since last weigh`;
                    }
                    
                    document.getElementById('weight-comparison').textContent += ` | ${changeText}`;
                }
            }

            // Set current time when form loads
            function setCurrentTime() {
                const now = new Date();
                const hours = String(now.getHours()).padStart(2, '0');
                const minutes = String(now.getMinutes()).padStart(2, '0');
                const currentTime = `${hours}:${minutes}`;
                
                const weighTimeInput = document.getElementById('weigh_time');
                if (weighTimeInput && !weighTimeInput.value) {
                    weighTimeInput.value = currentTime;
                }
            }

            // Event listeners
            pigSelect.addEventListener('change', function() {
                updatePigInfo();
                autoSuggestWeek();
                updateWeekComparison();
            });
            weightInput.addEventListener('input', updateWeightFeedback);
            targetWeekSelect.addEventListener('change', function() {
                updateWeekComparison();
                updateWeightFeedback();
            });
            weighDateInput.addEventListener('change', function() {
                autoSuggestWeek();
                updateWeekComparison();
            });

            // Auto-suggest target week based on pig's age on weigh date
            function autoSuggestWeek() {
                const selectedOption = pigSelect.options[pigSelect.selectedIndex];
                const weighDate = new Date(weighDateInput.value);

                if (selectedOption.value && !targetWeekSelect.value && weighDate) {
                    const birthDateStr = selectedOption.dataset.birthDate;
                    let suggestedWeek = 0;

                    if (birthDateStr) {
                        const birthDate = new Date(birthDateStr);
                        const ageInDays = Math.floor((weighDate - birthDate) / (1000 * 60 * 60 * 24));
                        suggestedWeek = Math.floor(ageInDays / 7);
                    } else {
                        // Fallback to current age
                        const ageInDays = parseInt(selectedOption.dataset.age);
                        suggestedWeek = Math.floor(ageInDays / 7);
                    }

                    if (suggestedWeek > 0 && suggestedWeek <= 104) {
                        targetWeekSelect.value = suggestedWeek;
                        updateWeekComparison();
                    }
                }
            }

            // Update on page load if pig is pre-selected
            if (pigSelect.value) {
                updatePigInfo();
                autoSuggestWeek();
            }

            // Set time on page load
            setCurrentTime();
        });
    </script>
</x-app-layout>
