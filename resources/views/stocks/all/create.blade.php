<x-app-layout>
    <main class="flex-1 pl-8 pr-4 pt-8 pb-8">
        <div class="max-w-2xl mx-auto bg-white shadow-xl rounded-lg p-8">
            <h2 class="text-2xl font-bold mb-6 text-center text-gray-700">Add New Stock</h2>

            <form method="POST" action="{{ route('breeding-stocks.store') }}" class="space-y-6">
                @csrf

                <!-- Display validation errors -->
                @if ($errors->any())
                    <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                                </svg>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-red-800">Please correct the following errors:</h3>
                                <div class="mt-2 text-sm text-red-700">
                                    <ul class="list-disc list-inside space-y-1">
                                        @foreach ($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 mb-6">
                    <div class="flex items-center">
                        <svg class="h-5 w-5 text-gray-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <div>
                            <h4 class="text-sm font-medium text-gray-800">Unified Pig Registration</h4>
                            <p class="text-sm text-gray-700 mt-1">Register new pigs as either Standard Stock or Breeding Stock. You can change the stock type later if needed.</p>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Stock Type</label>
                    <select name="stock_type" id="stock_type" required class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-gray-600 focus:border-gray-600">
                        <option value="" disabled selected>-- Select Stock Type --</option>
                        <option value="standard" {{ old('stock_type') == 'standard' ? 'selected' : '' }}>Standard Stock</option>
                        <option value="breeding" {{ old('stock_type') == 'breeding' ? 'selected' : '' }}>Breeding Stock</option>
                    </select>
                    <p class="text-xs text-gray-500 mt-1">Standard Stock: Regular pigs for fattening. Breeding Stock: Pigs used for reproduction.</p>
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Breed</label>
                    <select name="breed" required class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-gray-600 focus:border-gray-600">
                        <option value="" disabled selected>-- Select Breed --</option>
                        <option value="LW" {{ old('breed') == 'LW' ? 'selected' : '' }}>Large White</option>
                        <option value="DR" {{ old('breed') == 'DR' ? 'selected' : '' }}>Duroc</option>
                        <option value="LWDR" {{ old('breed') == 'LWDR' ? 'selected' : '' }}>Large White Duroc</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Gender</label>
                    <select name="gender" required class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-gray-600 focus:border-gray-600">
                        <option value="" disabled selected>-- Select Gender --</option>
                        <option value="M" {{ old('gender') == 'M' ? 'selected' : '' }}>Male</option>
                        <option value="F" {{ old('gender') == 'F' ? 'selected' : '' }}>Female</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Ear Tag Number</label>
                    <input type="text" name="ear_tag_number" required value="{{ old('ear_tag_number') }}"
                           placeholder="e.g., LWM , DRF , LWDRM "
                           class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-gray-600 focus:border-gray-600">
                    <p class="text-xs text-gray-500 mt-1">Enter a unique ear tag number for this pig. Format: [Breed][Gender] [Number]</p>
                    @error('ear_tag_number')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Date of Birth</label>
                    <input type="date" name="date_of_birth" required max="{{ now()->toDateString() }}" value="{{ old('date_of_birth') }}" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-gray-600 focus:border-gray-600">
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Father (optional)</label>
                    <select name="father_id" id="father_id" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-gray-600 focus:border-gray-600">
                        <option value="" disabled selected>-- None --</option>
                        @foreach ($males as $male)
                            <option value="{{ $male->id }}" {{ old('father_id') == $male->id ? 'selected' : '' }}>
                                {{ $male->code }} - {{ $male->ear_tag_number }} ({{ $male->stock_type === 'breeding' ? 'Breeding' : 'Standard' }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Mother (optional)</label>
                    <select name="mother_id" id="mother_id" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-gray-600 focus:border-gray-600">
                        <option value="" disabled selected>-- None --</option>
                        @foreach ($females as $female)
                            <option value="{{ $female->id }}" {{ old('mother_id') == $female->id ? 'selected' : '' }}>
                                {{ $female->code }} - {{ $female->ear_tag_number }} ({{ $female->stock_type === 'breeding' ? 'Breeding' : 'Standard' }})
                            </option>
                        @endforeach
                    </select>
                </div>


                
                <div class="bg-gray-50 p-4 rounded-lg">
                    <h3 class="text-sm font-semibold text-gray-800 mb-2">Auto-Generated Fields</h3>
                    <p class="text-sm text-gray-700">The following will be automatically generated:</p>
                    <ul class="text-sm text-gray-600 mt-1 list-disc list-inside">
                        <li><strong>Code:</strong> Unique pig identifier (e.g., CFPG-2508-1)</li>
                        <li><strong>Age:</strong> Calculated from date of birth</li>
                    </ul>
                    <!-- <p class="text-sm text-gray-700 mt-3"><strong>Manual Entry:</strong></p>
                    <ul class="text-sm text-gray-600 mt-1 list-disc list-inside">
                        <li><strong>Ear Tag:</strong> You can now enter a custom ear tag number</li>
                    </ul> -->
                </div>
                
                <div class="pt-4">
                    <button type="submit" id="submit-button" class="w-full py-3 px-6 bg-gray-700 text-white font-bold rounded-lg shadow hover:bg-gray-800 transition">Create Stock</button>
                </div>

                <div class="text-center">
                    <a href="{{ \App\Helpers\NavigationHelper::getContextualBackUrl('all-stocks') }}" class="text-gray-600 hover:underline">← Back</a>
                </div>
            </form>
        </div>
    </main>

    <script>
        // Dynamic form behavior based on stock type selection
        document.addEventListener('DOMContentLoaded', function() {
            const stockTypeSelect = document.getElementById('stock_type');
            const fatherSelect = document.getElementById('father_id');
            const motherSelect = document.getElementById('mother_id');
            const submitButton = document.getElementById('submit-button');
            const infoBox = document.querySelector('.bg-gray-50.border');
            const earTagInput = document.querySelector('input[name="ear_tag_number"]');

            let earTagCheckTimeout;

            function updateFormBasedOnStockType() {
                const stockType = stockTypeSelect.value;

                // Update info box content
                if (stockType === 'standard') {
                    infoBox.innerHTML = `
                        <div class="flex items-center">
                            <svg class="h-5 w-5 text-blue-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <div>
                                <h4 class="text-sm font-medium text-blue-800">Standard Stock Registration</h4>
                                <p class="text-sm text-blue-700 mt-1">Registering as Standard Stock - suitable for fattening and general production.</p>
                            </div>
                        </div>
                    `;
                    infoBox.className = 'bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6';
                    submitButton.textContent = 'Create Standard Stock';
                    submitButton.className = 'w-full py-3 px-6 bg-blue-600 text-white font-bold rounded-lg shadow hover:bg-blue-700 transition';
                } else if (stockType === 'breeding') {
                    infoBox.innerHTML = `
                        <div class="flex items-center">
                            <svg class="h-5 w-5 text-green-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <div>
                                <h4 class="text-sm font-medium text-green-800">Breeding Stock Registration</h4>
                                <p class="text-sm text-green-700 mt-1">Registering as Breeding Stock - for reproduction and genetic improvement.</p>
                            </div>
                        </div>
                    `;
                    infoBox.className = 'bg-green-50 border border-green-200 rounded-lg p-4 mb-6';
                    submitButton.textContent = 'Create Breeding Stock';
                    submitButton.className = 'w-full py-3 px-6 bg-green-600 text-white font-bold rounded-lg shadow hover:bg-green-700 transition';
                } else {
                    infoBox.innerHTML = `
                        <div class="flex items-center">
                            <svg class="h-5 w-5 text-gray-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <div>
                                <h4 class="text-sm font-medium text-gray-800">Unified Pig Registration</h4>
                                <p class="text-sm text-gray-700 mt-1">Register new pigs as either Standard Stock or Breeding Stock. You can change the stock type later if needed.</p>
                            </div>
                        </div>
                    `;
                    infoBox.className = 'bg-gray-50 border border-gray-200 rounded-lg p-4 mb-6';
                    submitButton.textContent = 'Create Stock';
                    submitButton.className = 'w-full py-3 px-6 bg-gray-700 text-white font-bold rounded-lg shadow hover:bg-gray-800 transition';
                }
            }

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
                } else {
                    // No inbreeding issue
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

            // Event listeners
            stockTypeSelect.addEventListener('change', updateFormBasedOnStockType);
            fatherSelect.addEventListener('change', checkInbreeding);
            motherSelect.addEventListener('change', checkInbreeding);

            // Ear tag validation with debouncing
            earTagInput.addEventListener('input', function() {
                clearTimeout(earTagCheckTimeout);
                earTagCheckTimeout = setTimeout(checkEarTagAvailability, 500); // Wait 500ms after user stops typing
            });

            // Initialize form state
            updateFormBasedOnStockType();
            checkInbreeding();
            updateSubmitButtonState();
        });
    </script>
</x-app-layout>
