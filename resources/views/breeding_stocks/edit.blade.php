<x-app-layout>
    <main class="flex-1 pl-8 pr-4 pt-8 pb-8">
        <div class="max-w-2xl mx-auto bg-white shadow-xl rounded-lg p-8">
            <h2 class="text-2xl font-bold mb-6 text-center text-[#1C6C51]">Edit Breeding Stock</h2>
            <form method="POST" action="{{ route('breeding-stocks.update', $breedingStock) }}" class="space-y-6">
                @csrf
                @method('PUT')

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Stock Type</label>
                    <select name="stock_type" required class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-[#1C6C51] focus:border-[#1C6C51]">
                        <option value="breeding" @if(old('stock_type', $breedingStock->stock_type) == 'breeding') selected @endif>Breeding Stock</option>
                        <option value="standard" @if(old('stock_type', $breedingStock->stock_type) == 'standard') selected @endif>Standard Stock</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Breed</label>
                    <select name="breed" required class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-[#1C6C51] focus:border-[#1C6C51]">
                        <option value="LW" @if(old('breed', $breedingStock->breed) == 'LW') selected @endif>Large White</option>
                        <option value="DR" @if(old('breed', $breedingStock->breed) == 'DR') selected @endif>Duroc</option>
                        <option value="LWDR" @if(old('breed', $breedingStock->breed) == 'LWDR') selected @endif>Large White Duroc</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Gender</label>
                    <select name="gender" required class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-[#1C6C51] focus:border-[#1C6C51]">
                        <option value="M" @if(old('gender', $breedingStock->gender) == 'M') selected @endif>Male</option>
                        <option value="F" @if(old('gender', $breedingStock->gender) == 'F') selected @endif>Female</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Ear Tag Number</label>
                    <input type="text" name="ear_tag_number" required value="{{ old('ear_tag_number', $breedingStock->ear_tag_number) }}"
                           placeholder="e.g., LWM 1, DRF 2, LWDRM 3"
                           class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-[#1C6C51] focus:border-[#1C6C51]">
                    <p class="text-xs text-gray-500 mt-1">Enter a unique ear tag number for this pig. Format: [Breed][Gender] [Number]</p>
                    @error('ear_tag_number')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Date of Birth</label>
                    <input type="date" name="date_of_birth" required max="{{ now()->toDateString() }}" value="{{ old('date_of_birth', $breedingStock->date_of_birth->format('Y-m-d')) }}" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-[#1C6C51] focus:border-[#1C6C51]">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Room (optional)</label>
                    <select name="room_id" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-[#1C6C51] focus:border-[#1C6C51]">
                        <option value="" selected>-- No Room --</option>
                        @isset($rooms)
                            @foreach ($rooms as $room)
                                @php
                                    $label = ($room->structure?->name ? $room->structure->name . ' / ' : '') . $room->name;
                                    if ($room->capacity) {
                                        $label .= " (" . ($room->pigs()->count()) . "/" . $room->capacity . ")";
                                    }
                                @endphp
                                <option value="{{ $room->id }}" @if(old('room_id', $breedingStock->room_id) == $room->id) selected @endif>{{ $label }}</option>
                            @endforeach
                        @endisset
                    </select>
                    <p class="text-xs text-gray-500 mt-1">Select a room to assign location. Capacity limits are enforced.</p>
                    @error('room_id')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Father (optional)</label>
                    <select name="father_id" id="father_id" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-[#1C6C51] focus:border-[#1C6C51]">
                        <option value="" disabled selected>-- None --</option>
                        @foreach ($males as $male)
                            <option value="{{ $male->id }}" @if(old('father_id', $breedingStock->father_id) == $male->id) selected @endif>{{ $male->ear_tag_number }} - {{ $male->code }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Mother (optional)</label>
                    <select name="mother_id" id="mother_id" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-[#1C6C51] focus:border-[#1C6C51]">
                        <option value="" disabled selected>-- None --</option>
                        @foreach ($females as $female)
                            <option value="{{ $female->id }}" @if(old('mother_id', $breedingStock->mother_id) == $female->id) selected @endif>{{ $female->ear_tag_number }} - {{ $female->code }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="pt-4">
                    <button type="submit" id="submit-button" class="w-full py-3 px-6 bg-[#1C6C51] text-white font-bold rounded-lg shadow hover:bg-[#185941] transition">Update</button>
                </div>

                <div class="text-center mt-4">
                    <a href="{{ route('pigs.show', $breedingStock->id) }}" class="text-gray-600 hover:underline">← Back to Pig Details</a>
                </div>
            </form>
        </div>
    </main>

    <script>
        // Form enhancement and validation
        document.addEventListener('DOMContentLoaded', function() {
            const fatherSelect = document.getElementById('father_id');
            const motherSelect = document.getElementById('mother_id');
            const submitButton = document.getElementById('submit-button');
            const stockTypeSelect = document.querySelector('select[name="stock_type"]');
            const breedSelect = document.querySelector('select[name="breed"]');
            const genderSelect = document.querySelector('select[name="gender"]');
            const earTagInput = document.querySelector('input[name="ear_tag_number"]');

            let earTagCheckTimeout;
            const originalEarTag = earTagInput.value; // Store original value for comparison

            function checkEarTagAvailability() {
                const earTagValue = earTagInput.value.trim();

                // Remove existing ear tag warnings
                const existingEarTagWarning = document.querySelector('.ear-tag-warning');
                if (existingEarTagWarning) {
                    existingEarTagWarning.remove();
                }

                // Reset input styling
                earTagInput.classList.remove('border-red-500', 'border-green-500');
                earTagInput.classList.add('border-gray-300');

                // If ear tag is the same as original, skip validation
                if (earTagValue === originalEarTag) {
                    updateSubmitButtonState();
                    return;
                }

                if (earTagValue.length === 0) {
                    updateSubmitButtonState();
                    return;
                }

                // Show loading state
                const loadingIndicator = document.createElement('div');
                loadingIndicator.className = 'ear-tag-warning text-gray-500 text-xs mt-1';
                loadingIndicator.innerHTML = '🔍 Checking availability...';
                earTagInput.parentNode.appendChild(loadingIndicator);

                // Check if ear tag exists
                fetch('/api/check-ear-tag', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({ ear_tag_number: earTagValue })
                })
                .then(response => response.json())
                .then(data => {
                    // Remove loading indicator
                    const loadingIndicator = document.querySelector('.ear-tag-warning');
                    if (loadingIndicator) {
                        loadingIndicator.remove();
                    }

                    if (data.exists) {
                        // Ear tag already exists
                        const warning = document.createElement('div');
                        warning.className = 'ear-tag-warning text-red-600 text-xs mt-1 flex items-center';
                        warning.innerHTML = '❌ This ear tag number already exists. Please choose a different one.';
                        earTagInput.parentNode.appendChild(warning);
                        earTagInput.classList.remove('border-gray-300', 'border-green-500');
                        earTagInput.classList.add('border-red-500');
                    } else {
                        // Ear tag is available
                        const success = document.createElement('div');
                        success.className = 'ear-tag-warning text-green-600 text-xs mt-1 flex items-center';
                        success.innerHTML = '✅ This ear tag number is available.';
                        earTagInput.parentNode.appendChild(success);
                        earTagInput.classList.remove('border-gray-300', 'border-red-500');
                        earTagInput.classList.add('border-green-500');
                    }

                    updateSubmitButtonState();
                })
                .catch(error => {
                    console.error('Error checking ear tag:', error);
                    // Remove loading indicator on error
                    const loadingIndicator = document.querySelector('.ear-tag-warning');
                    if (loadingIndicator) {
                        loadingIndicator.remove();
                    }
                    updateSubmitButtonState();
                });
            }

            function checkInbreeding() {
                const fatherId = fatherSelect.value;
                const motherId = motherSelect.value;

                // Remove existing warnings
                const existingWarning = document.querySelector('.inbreeding-warning');
                if (existingWarning) {
                    existingWarning.remove();
                }

                if (fatherId && motherId && fatherId === motherId) {
                    // Same pig selected as both parents
                    const warning = document.createElement('div');
                    warning.className = 'inbreeding-warning bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mt-4';
                    warning.innerHTML = '<strong>Warning:</strong> Same pig cannot be both father and mother.';
                    motherSelect.parentNode.appendChild(warning);
                }

                updateSubmitButtonState();
            }

            function updateSubmitButtonState() {
                const hasInbreedingIssue = document.querySelector('.inbreeding-warning') !== null;
                const hasEarTagIssue = document.querySelector('.ear-tag-warning.text-red-600') !== null;
                const earTagValue = earTagInput.value.trim();

                if (hasInbreedingIssue || hasEarTagIssue || earTagValue.length === 0) {
                    submitButton.disabled = true;
                    submitButton.classList.add('opacity-50', 'cursor-not-allowed');
                } else {
                    submitButton.disabled = false;
                    submitButton.classList.remove('opacity-50', 'cursor-not-allowed');
                }
            }

            function updateFormTheme() {
                const stockType = stockTypeSelect.value;
                const form = document.querySelector('form');
                const title = document.querySelector('h2');

                if (stockType === 'breeding') {
                    title.textContent = 'Edit Breeding Stock';
                    title.className = 'text-2xl font-bold mb-6 text-center text-[#1C6C51]';
                    submitButton.className = 'w-full py-3 px-6 bg-[#1C6C51] text-white font-bold rounded-lg shadow hover:bg-[#185941] transition';
                } else if (stockType === 'standard') {
                    title.textContent = 'Edit Standard Stock';
                    title.className = 'text-2xl font-bold mb-6 text-center text-blue-600';
                    submitButton.className = 'w-full py-3 px-6 bg-blue-600 text-white font-bold rounded-lg shadow hover:bg-blue-700 transition';
                }
            }

            // Event listeners
            fatherSelect.addEventListener('change', checkInbreeding);
            motherSelect.addEventListener('change', checkInbreeding);
            stockTypeSelect.addEventListener('change', updateFormTheme);

            // Ear tag validation with debouncing
            earTagInput.addEventListener('input', function() {
                clearTimeout(earTagCheckTimeout);
                earTagCheckTimeout = setTimeout(checkEarTagAvailability, 500); // Wait 500ms after user stops typing
            });

            // Initial checks
            checkInbreeding();
            updateFormTheme();
            updateSubmitButtonState();
        });
    </script>
</x-app-layout>