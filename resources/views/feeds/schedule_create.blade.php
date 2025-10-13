<x-app-layout>
    <div class="min-h-screen py-2">
        <div class="max-w-6xl mx-auto px-4">
            
            <div class="bg-white rounded-xl shadow-xl border border-gray-100 overflow-hidden">
                
                <div class="px-6 py-4 border-b border-gray-200 bg-white flex items-center justify-between">
                    <h1 class="text-xl font-bold text-gray-900">Create Granular Feed Allocation </h1>
                    <a href="{{ route('feeds.schedule') }}" 
                       class="inline-flex items-center text-sm font-medium text-indigo-600 hover:text-indigo-800 transition">
                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                        Back to Schedule
                    </a>
                </div>

                <form id="allocationForm" method="POST" action="{{ route('feeds.allocation.store') }}" class="p-6 space-y-6">
                    @csrf
                    
                    <div class="space-y-4 border-b pb-4">
                        <h2 class="text-base font-semibold text-gray-800">1. Select Target Pig Location</h2>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            
                            <!-- 1. Structure Dropdown -->
                            <div>
                                <label for="structure_id" class="block text-sm font-medium text-gray-700 mb-1">Structure <span class="text-red-500">*</span></label>
                                <select id="structure_id" name="structure_id" class="w-full border-gray-300 rounded-lg py-2 shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500 transition" required>
                                    <option value="" disabled selected>-- Select Structure --</option>
                                    @foreach($structures as $s)
                                        <option value="{{ $s->id }}">{{ $s->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            
                            <!-- 2. Room Dropdown (Dynamic) -->
                            <div>
                                <label for="room_id" class="block text-sm font-medium text-gray-700 mb-1">Room <span class="text-red-500">*</span></label>
                                <select id="room_id" name="room_id" class="w-full border-gray-300 rounded-lg py-2 shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500 transition" required disabled>
                                    <option value="" disabled selected>-- Select Room --</option>
                                    <!-- Rooms will be loaded here via JavaScript -->
                                </select>
                            </div>

                            <!-- 3. Pig Dropdown (Dynamic) -->
                            <div>
                                <label for="pig_id" class="block text-sm font-medium text-gray-700 mb-1">Pig Ear Tag Number <span class="text-red-500">*</span></label>
                                <select id="pig_id" name="pig_id" class="w-full border-gray-300 rounded-lg py-2 shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500 transition" required disabled>
                                    <option value="" disabled selected>-- Select Pig --</option>
                                    <!-- Pigs will be loaded here via JavaScript -->
                                </select>
                            </div>
                        </div>
                    </div>

                    @if(isset($onHandMap) && $onHandMap->count())
                        <div class="text-xs bg-indigo-50 p-3 rounded-lg border border-indigo-200">
                            <h3 class="font-bold text-indigo-700 mb-2">Warehouse Stock Summary:</h3>
                            <div class="flex flex-wrap gap-2">
                                @foreach($feedTypes as $ft)
                                    @php($oh = $onHandMap[$ft->id] ?? null)
                                    <span class="px-2 py-0.5 bg-white border border-indigo-300 text-indigo-800 rounded-full text-xs font-medium shadow-sm">
                                        {{ $ft->name }}: 
                                        <span class="font-bold">{{ $oh ? number_format($oh->quantity, 2) : '0.00' }}</span> 
                                        <span class="text-gray-500">{{ $oh ? $oh->unit : '' }}</span>
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <div class="space-y-4 border-b pb-4">
                         <h2 class="text-base font-semibold text-gray-800">2. Define Feed Details</h2>
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            
                            <!-- Feed Type Dropdown -->
                            <div>
                                <label for="feed_type_id" class="block text-sm font-medium text-gray-700 mb-1">Feed Type <span class="text-red-500">*</span></label>
                                <select id="feed_type_id" name="feed_type_id" class="w-full border-gray-300 rounded-lg py-2 shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500 transition" required>
                                    <option value="" disabled selected>-- Select Feed Type --</option>
                                    @foreach($feedTypes as $ft)
                                        <option value="{{ $ft->id }}">{{ $ft->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- Allocated Amount Input -->
                            <div>
                                <label for="allocated_amount" class="block text-sm font-medium text-gray-700 mb-1">Allocated Amount <span class="text-red-500">*</span></label>
                                <input type="number" id="allocated_amount" name="feed_amount" step="0.01" min="0.1" placeholder="e.g., 2.50" class="w-full border-gray-300 rounded-lg py-2 shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500 transition" required>
                            </div>
                            
                            <!-- Unit Input -->
                            <div>
                                <label for="unit" class="block text-sm font-medium text-gray-700 mb-1">Unit</label>
                                <input type="text" id="unit" name="unit" value="kg" placeholder="kg" class="w-full border-gray-300 rounded-lg py-2 shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500 transition" required>
                            </div>
                            
                            <!-- Date Input -->
                            <div>
                                <label for="fed_at" class="block text-sm font-medium text-gray-700 mb-1">Feeding Date <span class="text-red-500">*</span></label>
                                <input type="date" id="fed_at" name="fed_at" value="{{ now()->toDateString() }}" class="w-full border-gray-300 rounded-lg py-2 shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500 transition" required>
                            </div>
                        </div>
                    </div>

                    <div>
                        <h2 class="base font-semibold text-gray-800 mb-3">3. Add Notes (Optional)</h2>
                        <label for="notes" class="sr-only">Notes</label>
                        <textarea id="notes" name="notes" rows="3" placeholder="e.g., Pig showed great appetite." class="w-full border-gray-300 rounded-lg shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500 transition"></textarea>
                    </div>

                    <div class="flex justify-end pt-2">
                        <button type="submit" class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-base rounded-lg shadow-lg transition duration-300 ease-in-out">
                            Allocate Feed to Pig
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <x-copyright-minimal />

    <script>
        const structureSelect = document.getElementById('structure_id');
        const roomSelect = document.getElementById('room_id');
        const pigSelect = document.getElementById('pig_id');

        // Store the default texts for each dropdown
        const defaultTexts = {
            room: '-- Select Room --',
            pig: '-- Select Pig --'
        };

        /**
         * Generic helper to fetch data from API
         * @param {string} url - The API endpoint URL.
         * @returns {Promise<Array>} - The array of data.
         */
        async function fetchData(url) {
            try {
                const response = await fetch(url);
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return await response.json();
            } catch (error) {
                console.error("Error fetching data:", error);
                return [];
            }
        }

        /**
         * Clears a dropdown and disables it, resetting to the default option.
         * @param {HTMLElement} selectElement - The select element to clear.
         * @param {string} defaultText - The final option text to show (e.g., '-- Select Pig --').
         */
        function resetDropdown(selectElement, defaultText) {
            selectElement.innerHTML = `<option value="" disabled selected>${defaultText}</option>`;
            selectElement.disabled = true;
        }

        /**
         * Populates a dropdown with options from the fetched data.
         */
        function populateDropdown(selectElement, data, valueKey, textKey, defaultText) {
            resetDropdown(selectElement, data.length > 0 ? defaultText : '-- No Items Found --');
            
            if (data.length > 0) {
                data.forEach(item => {
                    const option = document.createElement('option');
                    option.value = item[valueKey];
                    // IMPORTANT: Using ear_tag_number for display
                    option.textContent = item[textKey]; 
                    selectElement.appendChild(option);
                });
                selectElement.disabled = false;
            }
        }

        // --- Event Listeners ---

        // 1. Load Rooms when Structure changes
        structureSelect.addEventListener('change', async function() {
            const structureId = this.value;

            // Start Loading State
            roomSelect.innerHTML = `<option value="" disabled selected>-- Loading Rooms... --</option>`;
            roomSelect.disabled = true;
            resetDropdown(pigSelect, defaultTexts.pig); // Reset downstream

            if (structureId) {
                // Assuming API route: /api/structures/{structureId}/rooms
                const rooms = await fetchData(`/api/structures/${structureId}/rooms`);
                populateDropdown(roomSelect, rooms, 'id', 'name', defaultTexts.room);
            } else {
                resetDropdown(roomSelect, defaultTexts.room); // Reset to default text if structure is cleared
            }
        });

        // 2. Load Pigs when Room changes
        roomSelect.addEventListener('change', async function() {
            const roomId = this.value;

            // Start Loading State
            pigSelect.innerHTML = `<option value="" disabled selected>-- Loading Pigs... --</option>`;
            pigSelect.disabled = true;

            if (roomId) {
                // Assuming API route: /api/rooms/{roomId}/pigs
                const pigs = await fetchData(`/api/rooms/${roomId}/pigs`);
                
                // Use 'ear_tag_number' for display and 'id' for the value
                populateDropdown(pigSelect, pigs, 'id', 'ear_tag_number', defaultTexts.pig);
            } else {
                resetDropdown(pigSelect, defaultTexts.pig); // Back to default
            }
        });

        // Initialize state
        document.addEventListener('DOMContentLoaded', () => {
             // Disable Room and Pig dropdowns initially
             roomSelect.disabled = true;
             pigSelect.disabled = true;
        });
    </script>
</x-app-layout>
