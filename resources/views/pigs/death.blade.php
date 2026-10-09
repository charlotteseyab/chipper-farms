<x-app-layout>
    <div class="min-h-screen bg-gray-50">
        <!-- Header -->
        <div class="bg-white shadow-md">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-5">
                <div class="flex items-center space-x-4">
                    <a href="{{ url()->previous() }}" class="text-gray-500 hover:text-gray-700 transition">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                    </a>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">Record Pig Death</h1>
                        <p class="text-sm text-gray-500 mt-1">
                            Pig ID: <span class="font-semibold text-red-700">{{ $pig->code }}</span> 
                            &middot; Tag: {{ $pig->ear_tag_number }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <main class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <!-- Death Record Card -->
            <div class="bg-white rounded-xl shadow-xl border-t-4 border-red-600">
                
                <form method="POST" action="{{ route('pigs.record-death', $pig->id) }}" class="p-8 space-y-8">
                    @csrf
                    
                    <!-- SECTION 1: DEATH DETAILS -->
                    <div>
                        <h2 class="text-xl font-bold text-red-800 mb-6 border-b pb-2">1. Core Death Information</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            
                            <!-- Death Date -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2" for="death-date">Death Date <span class="text-red-500">*</span></label>
                                <input type="date" id="death-date" name="death_date" value="{{ old('death_date', now()->toDateString()) }}" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-red-500 focus:border-red-500" required>
                            </div>
                            
                            <!-- Death Cause -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2" for="death-cause">Death Cause <span class="text-red-500">*</span></label>
                                <select name="death_cause" id="death-cause" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-red-500 focus:border-red-500" required>
                                    <option value="" disabled selected>-- Select Cause --</option>
                                    <option value="disease" {{ old('death_cause') == 'disease' ? 'selected' : '' }}>Disease</option>
                                    <option value="accident" {{ old('death_cause') == 'accident' ? 'selected' : '' }}>Accident</option>
                                    <option value="natural" {{ old('death_cause') == 'natural' ? 'selected' : '' }}>Natural</option>
                                    <option value="stillbirth" {{ old('death_cause') == 'stillbirth' ? 'selected' : '' }}>Stillbirth</option>
                                    <option value="crushing" {{ old('death_cause') == 'crushing' ? 'selected' : '' }}>Crushing (Sow)</option>
                                    <option value="starvation" {{ old('death_cause') == 'starvation' ? 'selected' : '' }}>Starvation</option>
                                    <option value="hypothermia" {{ old('death_cause') == 'hypothermia' ? 'selected' : '' }}>Hypothermia</option>
                                    <option value="dehydration" {{ old('death_cause') == 'dehydration' ? 'selected' : '' }}>Dehydration</option>
                                    <option value="congenital" {{ old('death_cause') == 'congenital' ? 'selected' : '' }}>Congenital Defect</option>
                                    <option value="unknown" {{ old('death_cause') == 'unknown' ? 'selected' : '' }}>Unknown</option>
                                    <option value="other" {{ old('death_cause') == 'other' ? 'selected' : '' }}>Other</option>
                                </select>
                                <div id="death-cause-other" class="mt-2 {{ old('death_cause') == 'other' ? '' : 'hidden' }}">
                                    <input type="text" name="death_cause_other" placeholder="Please specify the death cause..." value="{{ old('death_cause_other') }}" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-red-500 focus:border-red-500">
                                </div>
                            </div>
                            
                            <!-- Age at Death (Read-only) -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Age at Death</label>
                                <input type="text" id="age-at-death" value="{{ $pig->age_in_days }} days ({{ $pig->age_display }})" class="w-full border-gray-300 rounded-lg shadow-inner bg-gray-100 text-gray-700 cursor-not-allowed" readonly>
                                <p class="text-xs text-gray-500 mt-1">Calculated based on birth date and selected death date.</p>
                            </div>
                            
                            <!-- Final Weight -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Final Weight (kg)</label>
                                <input type="number" step="0.01" name="final_weight" value="{{ old('final_weight') }}" placeholder="e.g., 85.50" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-red-500 focus:border-red-500">
                                <p class="text-xs text-gray-500 mt-1">Weight used for inventory and potential salvage value.</p>
                            </div>

                            <!-- Death Location -->
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Death Location (Pen/Unit)</label>
                                <input type="text" name="death_location" value="{{ old('death_location') }}" placeholder="e.g., Farrowing Pen 4" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-red-500 focus:border-red-500">
                            </div>
                        </div>
                    </div>

                    <!-- SECTION 2: DISPOSAL DETAILS -->
                    <div class="border-t border-gray-200 pt-8">
                        <h2 class="text-xl font-bold text-gray-800 mb-6 border-b pb-2">2. Disposal & Logistics</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                            <!-- Disposal Method -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2" for="disposal-method">Disposal Method <span class="text-red-500">*</span></label>
                                <select name="disposal_method" id="disposal-method" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-red-500 focus:border-red-500" required>
                                    <option value="" disabled selected>-- Select Method --</option>
                                    <option value="burial" {{ old('disposal_method', 'burial') == 'burial' ? 'selected' : '' }}>Burial</option>
                                    <option value="incineration" {{ old('disposal_method') == 'incineration' ? 'selected' : '' }}>Incineration</option>
                                    <!-- <option value="rendering" {{ old('disposal_method') == 'rendering' ? 'selected' : '' }}>Rendering</option>
                                    <option value="donation" {{ old('disposal_method') == 'donation' ? 'selected' : '' }}>Donation/Sale (Salvage)</option> -->
                                    <option value="other" {{ old('disposal_method') == 'other' ? 'selected' : '' }}>Other</option>
                                </select>
                                <div id="disposal-method-other" class="mt-2 {{ old('disposal_method') == 'other' ? '' : 'hidden' }}">
                                    <input type="text" name="disposal_method_other" placeholder="Please specify the disposal method..." value="{{ old('disposal_method_other') }}" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-red-500 focus:border-red-500">
                                </div>
                            </div>
                            
                            <!-- Disposal Date -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Disposal Date</label>
                                <input type="date" name="disposal_date" value="{{ old('disposal_date') }}" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-red-500 focus:border-red-500">
                            </div>
                        </div>
                    </div>

                    <div class="border-t border-gray-200 pt-8">
                        <h2 class="text-xl font-bold text-gray-800 mb-2">3. Physical ear tag</h2>
                        <p class="mb-5 text-sm text-gray-500">The animal record remains permanent. Tell the system what physically happened to this tag.</p>
                        <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                            <div>
                                <label for="tag-outcome" class="mb-2 block text-sm font-medium text-gray-700">Tag outcome <span class="text-red-500">*</span></label>
                                <select id="tag-outcome" name="tag_outcome" required class="w-full rounded-lg border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500">
                                    <option value="pending_recovery" @selected(old('tag_outcome', 'pending_recovery') === 'pending_recovery')>Not yet recovered</option>
                                    <option value="available" @selected(old('tag_outcome') === 'available')>Recovered and reusable</option>
                                    <option value="lost" @selected(old('tag_outcome') === 'lost')>Lost</option>
                                    <option value="damaged" @selected(old('tag_outcome') === 'damaged')>Damaged</option>
                                    <option value="retired" @selected(old('tag_outcome') === 'retired')>Permanently withdrawn</option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-medium text-gray-700">Tag condition notes</label>
                                <input name="tag_condition_notes" value="{{ old('tag_condition_notes') }}" placeholder="Where it was kept, damage, recovery details…" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500">
                            </div>
                        </div>
                    </div>

                    <!-- SECTION 3: NOTES -->
                    <div class="border-t border-gray-200 pt-8">
                        <h2 class="text-xl font-bold text-gray-800 mb-6 border-b pb-2">4. Notes and Analysis</h2>

                        <!-- Veterinary Notes -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Veterinary/Necropsy Notes</label>
                            <textarea name="veterinary_notes" rows="3" placeholder="Record any vet findings or post-mortem details here..." class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-red-500 focus:border-red-500">{{ old('veterinary_notes') }}</textarea>
                        </div>
                        
                        <!-- Additional Notes -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">General Notes</label>
                            <textarea name="death_notes" rows="3" placeholder="Any other relevant details..." class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-red-500 focus:border-red-500">{{ old('death_notes') }}</textarea>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="border-t border-gray-200 pt-6 flex justify-end space-x-4">
                        <a href="{{ url()->previous() }}" 
                           class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 font-medium hover:bg-gray-100 transition shadow-sm">
                            Cancel
                        </a>
                        <button type="submit" 
                                class="px-6 py-2 bg-red-600 text-white rounded-lg font-medium hover:bg-red-700 transition shadow-lg flex items-center">
                            <svg class="w-5 h-5 mr-2 -ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path></svg>
                            Record Death
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const deathDateInput = document.getElementById('death-date');
            const ageAtDeathInput = document.getElementById('age-at-death');
            
            // Pig's birth date must be parsed correctly from the PHP variable
            const birthDateStr = '{{ $pig->date_of_birth }}';
            const birthDate = new Date(birthDateStr);
            
            // Check for valid birth date
            if (isNaN(birthDate.getTime())) {
                console.error("Invalid birth date provided from server: " + birthDateStr);
                ageAtDeathInput.value = 'Error: Invalid birth date';
                return; // Exit if birth date is fundamentally broken
            }
            
            function calculateAgeAtDeath() {
                const deathDate = new Date(deathDateInput.value);
                
                // Check if death date is valid
                if (isNaN(deathDate.getTime())) {
                    ageAtDeathInput.value = 'Select a valid death date';
                    return;
                }
                
                // Calculate the difference in milliseconds
                const diffTime = deathDate.getTime() - birthDate.getTime();
                
                // Convert to days
                const diffDays = Math.floor(diffTime / (1000 * 60 * 60 * 24));
                
                if (diffDays >= 0) {
                    let ageDisplayParts = [];
                    let tempDays = diffDays;

                    // Calculate years
                    const years = Math.floor(tempDays / 365);
                    tempDays = tempDays % 365;
                    if (years > 0) ageDisplayParts.push(years + (years === 1 ? ' year' : ' years'));

                    // Calculate months (approx 30 days)
                    const months = Math.floor(tempDays / 30);
                    tempDays = tempDays % 30;
                    if (months > 0) ageDisplayParts.push(months + (months === 1 ? ' month' : ' months'));

                    // Remaining days
                    const days = tempDays;
                    if (days > 0 || ageDisplayParts.length === 0) { // Ensure at least days are shown if it's less than a month old
                         ageDisplayParts.push(days + (days === 1 ? ' day' : ' days'));
                    }
                    
                    // Update the age display
                    const ageDisplay = ageDisplayParts.slice(0, 2).join(', '); // Show up to two largest units (e.g., "1 year, 2 months")
                    ageAtDeathInput.value = diffDays + ' days (' + ageDisplay + ')';
                } else {
                    ageAtDeathInput.value = 'Invalid (before birth)';
                }
            }
            
            // Add event listener to death date input
            deathDateInput.addEventListener('change', calculateAgeAtDeath);
            deathDateInput.addEventListener('input', calculateAgeAtDeath);
            
            // Calculate initial age on load
            calculateAgeAtDeath();
            
            // Handle "other" dropdown functionality
            function handleOtherDropdown(selectId, otherDivId) {
                const select = document.getElementById(selectId);
                const otherDiv = document.getElementById(otherDivId);
                
                if (select && otherDiv) {
                    // Check initial state for old value support
                    if (select.value === 'other') {
                        otherDiv.classList.remove('hidden');
                    }

                    select.addEventListener('change', function() {
                        if (this.value === 'other') {
                            otherDiv.classList.remove('hidden');
                            const input = otherDiv.querySelector('input');
                            if (input) {
                                input.required = true;
                                input.focus();
                            }
                        } else {
                            otherDiv.classList.add('hidden');
                            const input = otherDiv.querySelector('input');
                            if (input) {
                                input.required = false;
                                input.value = ''; // Clear value when hidden
                            }
                        }
                    });
                }
            }
            
            // Initialize other dropdowns
            handleOtherDropdown('death-cause', 'death-cause-other');
            handleOtherDropdown('disposal-method', 'disposal-method-other');
        });
    </script>
</x-app-layout>
