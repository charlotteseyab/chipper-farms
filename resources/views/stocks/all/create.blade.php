
<x-app-layout>
    <main class="flex-1 pl-8 pr-4 pt-2 pb-8">
        <div class="max-w-7xl mx-auto">
            <!-- Book Title -->
            <!-- <div class="text-center mb-8">
                <h2 class="text-3xl font-bold text-gray-800 mb-2">📖 Pig Registration</h2>
                <p class="text-gray-600">Fill out both pages to register your new pig</p>
            </div> -->

            <!-- Book Container -->
            <div class="p-8 rounded-2xl shadow-2xl">
                <div class="flex gap-8">
                    <!-- Left Page (Basic Information) -->
                    <div class="flex-1 bg-white rounded-lg shadow-lg p-6 border-r-2 border-gray-200">
                        <div class="text-center mb-6">
                            <h3 class="text-xl font-bold text-gray-800 mb-2">📝Basic Information</h3>
                            <p class="text-sm text-gray-600">Essential details about your pig</p>
                        </div>

                        <form method="POST" action="{{ route('breeding-stocks.store') }}" id="pigForm">
                            @csrf

                            <!-- Display validation errors -->
                            @if ($errors->any())
                                <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
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

                            <!-- Stock Type Selection -->
                            <div class="mb-6">
  <div class="text-sm font-semibold text-gray-700 mb-3">🐷 Stock Type</div>

  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

	<!-- Standard -->
	<label class="relative block cursor-pointer">
  	<input
    	type="radio"
    	name="stock_type"
    	value="standard"
    	class="peer absolute inset-0 opacity-0 appearance-none"
    	{{ old('stock_type') == 'standard' ? 'checked' : '' }}
    	required
  	>
  	<div class="stock-card rounded-xl border border-gray-200 p-5 text-center transition
              	hover:border-blue-400 peer-checked:border-blue-600
              	peer-checked:bg-blue-50 peer-checked:shadow">
    	<div class="text-2xl mb-2">🐖</div>
    	<div class="font-semibold text-gray-800">Standard Stock</div>
    	<div class="text-xs text-gray-600 mt-1">For fattening & Sale</div>
    	<span class="hidden peer-checked:inline-block absolute top-2 right-2
                 	text-[10px] px-2 py-0.5 rounded-full bg-blue-600 text-white">
      	Selected
    	</span>
  	</div>
	</label>

	<label class="relative block cursor-pointer">
  	<input
    	type="radio"
    	name="stock_type"
    	value="breeding"
    	class="peer absolute inset-0 opacity-0 appearance-none"
    	{{ old('stock_type') == 'breeding' ? 'checked' : '' }}
    	required
  	>
  	<div class="stock-card rounded-xl border border-gray-200 p-5 text-center transition
              	hover:border-green-400 peer-checked:border-green-600
              	peer-checked:bg-green-50 peer-checked:shadow">
    	<div class="text-2xl mb-2">👑</div>
    	<div class="font-semibold text-gray-800">Breeding Stock</div>
    	<div class="text-xs text-gray-600 mt-1">For reproduction</div>
    	<span class="hidden peer-checked:inline-block absolute top-2 right-2
                 	text-[10px] px-2 py-0.5 rounded-full bg-green-600 text-white">
      	Selected
    	</span>
  	</div>
	</label>

  </div>
</div>


                            <!-- Basic Details -->
                            <div class="space-y-4">
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-1">🐄 Breed</label>
                                        <select name="breed" required class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                            <option value="" disabled selected>Select Breed</option>
                                            <option value="LW" {{ old('breed') == 'LW' ? 'selected' : '' }}>Large White</option>
                                            <option value="DR" {{ old('breed') == 'DR' ? 'selected' : '' }}>Duroc</option>
                                            <option value="LWDR" {{ old('breed') == 'LWDR' ? 'selected' : '' }}>Large White Duroc</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-1">⚧ Gender</label>
                                        <select name="gender" required class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                            <option value="" disabled selected>Select Gender</option>
                                            <option value="M" {{ old('gender') == 'M' ? 'selected' : '' }}>Male</option>
                                            <option value="F" {{ old('gender') == 'F' ? 'selected' : '' }}>Female</option>
                                        </select>
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-1">🏷️ Ear Tag Number</label>
                                    <input type="text" name="ear_tag_number" required value="{{ old('ear_tag_number') }}"
                                           placeholder="e.g., LWM001, DRF002"
                                           class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                    <p class="text-xs text-gray-500 mt-1">Unique identifier for this pig</p>
                                </div>

                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-1">📅 Date of Birth</label>
                                    <input type="date" name="date_of_birth" required max="{{ now()->toDateString() }}" 
                                           value="{{ old('date_of_birth') }}" 
                                           class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                </div>
                            </div>

                            <!-- Auto-generated info -->
                            <div class="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
                                <h4 class="text-sm font-semibold text-blue-800 mb-2">✨ Auto-Generated</h4>
                                <ul class="text-sm text-blue-700 space-y-1">
                                    <li>• <strong>Code:</strong> Unique pig identifier</li>
                                    <li>• <strong>Age:</strong> Calculated from birth date</li>
                                </ul>
                            </div>
                    </div>

                    <!-- Right Page (Advanced Options) -->
                    <div class="flex-1 bg-white rounded-lg shadow-lg p-6 border-l-2 border-gray-200">
                        <div class="text-center mb-6">
                            <h3 class="text-xl font-bold text-gray-800 mb-2">🏠Location & Family</h3>
                            <p class="text-sm text-gray-600">Housing and parent information</p>
                        </div>

                        <!-- Housing Information -->
                        <div class="mb-6">
                            <h4 class="text-sm font-semibold text-gray-700 mb-3">🏢 Housing Assignment</h4>
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Structure</label>
                                    <select name="structure_id" id="structure_id" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                        <option value="" disabled selected>Select Structure (Optional)</option>
                                        @isset($structures)
                                            @foreach ($structures as $structure)
                                                <option value="{{ $structure->id }}" {{ old('structure_id') == $structure->id ? 'selected' : '' }}>
                                                    {{ $structure->name }} 
                                                    @if($structure->capacity)
                                                        ({{ $structure->current_room_count }}/{{ $structure->capacity }} rooms)
                                                    @endif
                                                </option>
                                            @endforeach
                                        @endisset
                                    </select>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Room</label>
                                    <select name="room_id" id="room_id" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500" disabled>
                                        <option value="" disabled selected>Select Structure First</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Parent Information -->
                        <div class="mb-6">
                            <h4 class="text-sm font-semibold text-gray-700 mb-3">👨‍👩‍👧‍👦 Parent Information</h4>
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">👨 Father (Optional)</label>
                                    <select name="father_id" id="father_id" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                        <option value="" disabled selected>Select Father</option>
                                        @foreach ($males as $male)
                                            <option value="{{ $male->id }}" {{ old('father_id') == $male->id ? 'selected' : '' }}>
                                                {{ $male->code }} - {{ $male->ear_tag_number }} 
                                                <span class="text-xs text-gray-500">({{ $male->stock_type === 'breeding' ? 'Breeding' : 'Standard' }})</span>
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">👩 Mother (Optional)</label>
                                    <select name="mother_id" id="mother_id" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                        <option value="" disabled selected>Select Mother</option>
                                        @foreach ($females as $female)
                                            <option value="{{ $female->id }}" {{ old('mother_id') == $female->id ? 'selected' : '' }}>
                                                {{ $female->code }} - {{ $female->ear_tag_number }}
                                                <span class="text-xs text-gray-500">({{ $female->stock_type === 'breeding' ? 'Breeding' : 'Standard' }})</span>
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <div class="mt-8">
                            <button type="submit" form="pigForm" id="submit-button" 
                                    class="w-full py-4 px-6 bg-gradient-to-r from-blue-600 to-purple-600 text-white font-bold rounded-lg shadow-lg hover:from-blue-700 hover:to-purple-700 transition-all transform hover:scale-105">
                                📖 Complete Registration
                            </button>
                        </div>

                        <!-- Back Link -->
                        <div class="text-center mt-4">
                            <a href="{{ \App\Helpers\NavigationHelper::getContextualBackUrl('all-stocks') }}" 
                               class="text-gray-600 hover:text-gray-800 hover:underline">
                                ← Back to Stock List
                            </a>
                        </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Enhanced form behavior with book-like interactions
        document.addEventListener('DOMContentLoaded', function() {
            const stockTypeRadios = document.querySelectorAll('input[name="stock_type"]');
            const stockTypeOptions = document.querySelectorAll('.stock-type-option');
            const fatherSelect = document.getElementById('father_id');
            const motherSelect = document.getElementById('mother_id');
            const submitButton = document.getElementById('submit-button');
            const earTagInput = document.querySelector('input[name="ear_tag_number"]');

            let earTagCheckTimeout;

            // Stock type selection with visual feedback
            function updateStockTypeSelection() {
                stockTypeOptions.forEach(option => {
                    option.classList.remove('border-blue-500', 'border-green-500', 'bg-blue-50', 'bg-green-50');
                    option.classList.add('border-gray-200');
                });

                const selectedType = document.querySelector('input[name="stock_type"]:checked');
                if (selectedType) {
                    const selectedOption = document.querySelector(`[data-type="${selectedType.value}"]`);
                    if (selectedType.value === 'standard') {
                        selectedOption.classList.add('border-blue-500', 'bg-blue-50');
                        submitButton.className = 'w-full py-4 px-6 bg-gradient-to-r from-blue-600 to-blue-700 text-white font-bold rounded-lg shadow-lg hover:from-blue-700 hover:to-blue-800 transition-all transform hover:scale-105';
                        submitButton.textContent = '🐖 Register Standard Stock';
                    } else if (selectedType.value === 'breeding') {
                        selectedOption.classList.add('border-green-500', 'bg-green-50');
                        submitButton.className = 'w-full py-4 px-6 bg-gradient-to-r from-green-600 to-green-700 text-white font-bold rounded-lg shadow-lg hover:from-green-700 hover:to-green-800 transition-all transform hover:scale-105';
                        submitButton.textContent = '👑 Register Breeding Stock';
                    }
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
                    warning.innerHTML = '<strong>⚠️ Warning:</strong> Same pig cannot be both father and mother.';
                    motherSelect.parentNode.appendChild(warning);
                }

                updateSubmitButtonState();
            }

            function updateSubmitButtonState() {
                const hasInbreedingIssue = document.querySelector('.inbreeding-warning') !== null;
                const hasEarTagIssue = document.querySelector('.ear-tag-warning.text-red-600') !== null;
                const earTagValue = earTagInput.value.trim();
                const stockTypeSelected = document.querySelector('input[name="stock_type"]:checked') !== null;

                if (hasInbreedingIssue || hasEarTagIssue || earTagValue.length === 0 || !stockTypeSelected) {
                    submitButton.disabled = true;
                    submitButton.classList.add('opacity-50', 'cursor-not-allowed');
                } else {
                    submitButton.disabled = false;
                    submitButton.classList.remove('opacity-50', 'cursor-not-allowed');
                }
            }

            // Event listeners
            stockTypeRadios.forEach(radio => {
                radio.addEventListener('change', updateStockTypeSelection);
            });

            fatherSelect.addEventListener('change', checkInbreeding);
            motherSelect.addEventListener('change', checkInbreeding);
            
            // Structure and Room dropdown functionality
            const structureSelect = document.getElementById('structure_id');
            const roomSelect = document.getElementById('room_id');
            
            structureSelect.addEventListener('change', function() {
                const structureId = this.value;
                roomSelect.disabled = !structureId;
                roomSelect.innerHTML = '<option value="" disabled selected>-- Loading Rooms --</option>';
                
                if (structureId) {
                    fetch(`/api/structures/${structureId}/rooms`)
                        .then(response => response.json())
                        .then(rooms => {
                            roomSelect.innerHTML = '<option value="" disabled selected>-- Select Room --</option>';
                            rooms.forEach(room => {
                                const option = document.createElement('option');
                                option.value = room.id;
                                option.textContent = `${room.name} (${room.code}) - ${room.type}`;
                                if (room.capacity) {
                                    option.textContent += ` - ${room.capacity} capacity`;
                                }
                                roomSelect.appendChild(option);
                            });
                        })
                        .catch(error => {
                            console.error('Error loading rooms:', error);
                            roomSelect.innerHTML = '<option value="" disabled selected>-- Error Loading Rooms --</option>';
                        });
                } else {
                    roomSelect.innerHTML = '<option value="" disabled selected>-- Select Structure First --</option>';
                }
            });

            // Ear tag validation with debouncing
            earTagInput.addEventListener('input', function() {
                clearTimeout(earTagCheckTimeout);
                earTagCheckTimeout = setTimeout(checkEarTagAvailability, 500);
            });

            // Initialize form state
            updateStockTypeSelection();
            checkInbreeding();
            updateSubmitButtonState();
        });
    </script>
</x-app-layout>
