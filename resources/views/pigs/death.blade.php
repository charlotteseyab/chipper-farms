<x-app-layout>
    <div class="max-w-3xl mx-auto py-8 px-4">
        <div class="bg-white shadow rounded-lg border border-gray-200">
            <div class="px-6 py-4 border-b border-gray-200">
                <h1 class="text-2xl font-bold text-gray-900">Record Pig Death</h1>
                <p class="text-gray-600 mt-1">{{ $pig->code }} ({{ $pig->ear_tag_number }})</p>
            </div>
            <form method="POST" action="{{ route('pigs.record-death', $pig->id) }}" class="p-6 space-y-6">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Death Date</label>
                        <input type="date" id="death-date" name="death_date" value="{{ old('death_date', now()->toDateString()) }}" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Death Cause</label>
                        <select name="death_cause" id="death-cause" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500" required>
                            <option value="" disabled selected>-- Select Cause --</option>
                            <option value="disease">Disease</option>
                            <option value="accident">Accident</option>
                            <option value="natural">Natural</option>
                            <option value="stillbirth">Stillbirth</option>
                            <option value="crushing">Crushing (Sow)</option>
                            <option value="starvation">Starvation</option>
                            <option value="hypothermia">Hypothermia</option>
                            <option value="dehydration">Dehydration</option>
                            <option value="congenital">Congenital Defect</option>
                            <option value="unknown">Unknown</option>
                            <option value="other">Other</option>
                        </select>
                        <div id="death-cause-other" class="mt-2 hidden">
                            <input type="text" name="death_cause_other" placeholder="Please specify the death cause..." class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Final Weight (kg)</label>
                        <input type="number" step="0.01" name="final_weight" value="{{ old('final_weight') }}" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Age at Death</label>
                        <input type="text" id="age-at-death" value="{{ $pig->age_in_days }} days ({{ $pig->age_display }})" class="w-full border-gray-300 rounded-lg shadow-sm bg-gray-50 text-gray-600" readonly>
                        <p class="text-xs text-gray-500 mt-1">Calculated automatically based on birth date and death date</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Death Location</label>
                        <input type="text" name="death_location" value="{{ old('death_location') }}" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Disposal Method</label>
                        <select name="disposal_method" id="disposal-method" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500" required>
                            <option value="" disabled selected>-- Select Method --</option>
                            <option value="burial" selected>Burial</option>
                            <option value="incineration">Incineration</option>
                            <option value="rendering">Rendering</option>
                            <option value="donation">Donation</option>
                            <option value="disposal">Disposal</option>
                            <option value="other">Other</option>
                        </select>
                        <div id="disposal-method-other" class="mt-2 hidden">
                            <input type="text" name="disposal_method_other" placeholder="Please specify the disposal method..." class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Disposal Date</label>
                        <input type="date" name="disposal_date" value="{{ old('disposal_date') }}" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Veterinary Notes</label>
                    <textarea name="veterinary_notes" rows="3" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">{{ old('veterinary_notes') }}</textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Additional Notes</label>
                    <textarea name="death_notes" rows="3" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">{{ old('death_notes') }}</textarea>
                </div>

                <div class="flex items-center justify-end">
                    <a href="{{ url()->previous() }}" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 mr-3">Cancel</a>
                    <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">Record Death</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const deathDateInput = document.getElementById('death-date');
            const ageAtDeathInput = document.getElementById('age-at-death');
            
            // Pig's birth date from the server
            const birthDate = new Date('{{ $pig->date_of_birth }}');
            
            // Debug logging
            console.log('Birth date from server:', '{{ $pig->date_of_birth }}');
            console.log('Parsed birth date:', birthDate);
            console.log('Birth date valid:', !isNaN(birthDate.getTime()));
            
            function calculateAgeAtDeath() {
                const deathDate = new Date(deathDateInput.value);
                
                // Check if dates are valid
                if (isNaN(birthDate.getTime())) {
                    ageAtDeathInput.value = 'Error: Invalid birth date';
                    return;
                }
                
                if (isNaN(deathDate.getTime())) {
                    ageAtDeathInput.value = 'Please select a valid death date';
                    return;
                }
                
                if (deathDate && birthDate) {
                    // Calculate the difference in milliseconds
                    const diffTime = deathDate - birthDate;
                    
                    // Convert to days
                    const diffDays = Math.floor(diffTime / (1000 * 60 * 60 * 24));
                    
                    if (diffDays >= 0) {
                        // Calculate years, months, and days
                        const years = Math.floor(diffDays / 365);
                        const remainingDays = diffDays % 365;
                        const months = Math.floor(remainingDays / 30);
                        const days = remainingDays % 30;
                        
                        // Format the age display
                        let ageDisplay = '';
                        if (years > 0) {
                            ageDisplay += years + ' year' + (years > 1 ? 's' : '');
                        }
                        if (months > 0) {
                            if (ageDisplay) ageDisplay += ' ';
                            ageDisplay += months + ' month' + (months > 1 ? 's' : '');
                        }
                        if (days > 0) {
                            if (ageDisplay) ageDisplay += ' ';
                            ageDisplay += days + ' day' + (days > 1 ? 's' : '');
                        }
                        
                        // If less than a month, show just days
                        if (diffDays < 30) {
                            ageDisplay = diffDays + ' day' + (diffDays > 1 ? 's' : '');
                        }
                        
                        // Update the age display
                        ageAtDeathInput.value = diffDays + ' days (' + ageDisplay + ')';
                    } else {
                        ageAtDeathInput.value = 'Invalid date (death date cannot be before birth date)';
                    }
                }
            }
            
            // Add event listener to death date input
            deathDateInput.addEventListener('change', calculateAgeAtDeath);
            deathDateInput.addEventListener('input', calculateAgeAtDeath);
            
            // Calculate initial age
            calculateAgeAtDeath();
            
            // Handle "other" dropdown functionality
            function handleOtherDropdown(selectId, otherDivId) {
                const select = document.getElementById(selectId);
                const otherDiv = document.getElementById(otherDivId);
                
                if (select && otherDiv) {
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
                                input.value = '';
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


