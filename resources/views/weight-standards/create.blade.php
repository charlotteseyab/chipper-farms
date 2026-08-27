<x-app-layout>
<div class="container mx-auto px-4 py-6">
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Create Weight Standard</h1>
            <p class="text-gray-600 mt-1">Set expected weight range for a specific breed, gender, and week</p>
        </div>
        <a href="{{ route('weight-standards.index') }}" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition">
            <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Back to Standards
        </a>
    </div>

    <!-- Form -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <form method="POST" action="{{ route('weight-standards.store') }}">
            @csrf
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Breed -->
                <div>
                    <label for="breed" class="block text-sm font-medium text-gray-700 mb-1">Breed *</label>
                    <select name="breed" id="breed" required data-searchable-select
                            class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('breed') border-red-300 @enderror">
                        <option value="">Select Breed</option>
                        @foreach($animalBreeds as $code => $name)
                            <option value="{{ $code }}" @selected(old('breed') === $code)>{{ $name }} ({{ $code }})</option>
                        @endforeach
                    </select>
                    @error('breed')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Gender -->
                <div>
                    <label for="gender" class="block text-sm font-medium text-gray-700 mb-1">Gender *</label>
                    <select name="gender" id="gender" required 
                            class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('gender') border-red-300 @enderror">
                        <option value="">Select Gender</option>
                        <option value="M" {{ old('gender') === 'M' ? 'selected' : '' }}>Male</option>
                        <option value="F" {{ old('gender') === 'F' ? 'selected' : '' }}>Female</option>
                    </select>
                    @error('gender')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Stock Type -->
                <div>
                    <label for="stock_type" class="block text-sm font-medium text-gray-700 mb-1">Stock Type *</label>
                    <select name="stock_type" id="stock_type" required 
                            class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('stock_type') border-red-300 @enderror">
                        <option value="">Select Stock Type</option>
                        <option value="standard" {{ old('stock_type') === 'standard' ? 'selected' : '' }}>Standard</option>
                        <option value="breeding" {{ old('stock_type') === 'breeding' ? 'selected' : '' }}>Breeding</option>
                    </select>
                    @error('stock_type')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Week Number -->
                <div>
                    <label for="week_number" class="block text-sm font-medium text-gray-700 mb-1">Week Number *</label>
                    <input type="number" name="week_number" id="week_number" min="1" max="200" required
                           value="{{ old('week_number') }}" placeholder="e.g., 8"
                           class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('week_number') border-red-300 @enderror">
                    <p class="mt-1 text-xs text-gray-500">Age in weeks (1-200)</p>
                    @error('week_number')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Expected Min Weight -->
                <div>
                    <label for="expected_min_weight" class="block text-sm font-medium text-gray-700 mb-1">Minimum Weight (kg) *</label>
                    <input type="number" name="expected_min_weight" id="expected_min_weight" step="0.1" min="0.1" max="500" required
                           value="{{ old('expected_min_weight') }}" placeholder="e.g., 15.0"
                           class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('expected_min_weight') border-red-300 @enderror">
                    @error('expected_min_weight')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Expected Max Weight -->
                <div>
                    <label for="expected_max_weight" class="block text-sm font-medium text-gray-700 mb-1">Maximum Weight (kg) *</label>
                    <input type="number" name="expected_max_weight" id="expected_max_weight" step="0.1" min="0.1" max="500" required
                           value="{{ old('expected_max_weight') }}" placeholder="e.g., 20.0"
                           class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('expected_max_weight') border-red-300 @enderror">
                    @error('expected_max_weight')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Optimal Weight -->
                <div>
                    <label for="optimal_weight" class="block text-sm font-medium text-gray-700 mb-1">Optimal Weight (kg) *</label>
                    <input type="number" name="optimal_weight" id="optimal_weight" step="0.1" min="0.1" max="500" required
                           value="{{ old('optimal_weight') }}" placeholder="e.g., 17.5"
                           class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('optimal_weight') border-red-300 @enderror">
                    <p class="mt-1 text-xs text-gray-500">Target weight for best performance</p>
                    @error('optimal_weight')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Active Status -->
                <div>
                    <label for="is_active" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <div class="flex items-center">
                        <input type="checkbox" name="is_active" id="is_active" value="1" 
                               {{ old('is_active', true) ? 'checked' : '' }}
                               class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                        <label for="is_active" class="ml-2 block text-sm text-gray-900">
                            Active (use this standard for weight checking)
                        </label>
                    </div>
                </div>
            </div>

            <!-- Notes -->
            <div class="mt-6">
                <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                <textarea name="notes" id="notes" rows="3" placeholder="Optional notes about this weight standard..."
                          class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('notes') border-red-300 @enderror">{{ old('notes') }}</textarea>
                @error('notes')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <!-- Weight Range Preview -->
            <div class="mt-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                <h3 class="text-sm font-medium text-blue-800 mb-2">Weight Range Preview</h3>
                <div id="weight-preview" class="text-sm text-blue-700">
                    Enter weight values to see the range preview
                </div>
            </div>

            <!-- Form Actions -->
            <div class="mt-8 flex justify-end space-x-3">
                <a href="{{ route('weight-standards.index') }}" class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition">
                    Cancel
                </a>
                <button type="submit" class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                    <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Create Weight Standard
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    // Weight range preview
    function updateWeightPreview() {
        const minWeight = document.getElementById('expected_min_weight').value;
        const maxWeight = document.getElementById('expected_max_weight').value;
        const optimalWeight = document.getElementById('optimal_weight').value;
        const preview = document.getElementById('weight-preview');
        
        if (minWeight && maxWeight && optimalWeight) {
            const min = parseFloat(minWeight);
            const max = parseFloat(maxWeight);
            const optimal = parseFloat(optimalWeight);
            
            if (min > 0 && max > min && optimal >= min && optimal <= max) {
                preview.innerHTML = `
                    <div class="flex items-center space-x-4">
                        <div><strong>Range:</strong> ${min.toFixed(1)} - ${max.toFixed(1)} kg</div>
                        <div><strong>Optimal:</strong> ${optimal.toFixed(1)} kg</div>
                        <div><strong>Tolerance:</strong> ±${((max - min) / 2).toFixed(1)} kg</div>
                    </div>
                `;
                preview.className = 'text-sm text-green-700';
            } else {
                preview.innerHTML = 'Invalid weight values. Please check: Min < Optimal < Max';
                preview.className = 'text-sm text-red-700';
            }
        } else {
            preview.innerHTML = 'Enter weight values to see the range preview';
            preview.className = 'text-sm text-blue-700';
        }
    }
    
    // Add event listeners
    document.getElementById('expected_min_weight').addEventListener('input', updateWeightPreview);
    document.getElementById('expected_max_weight').addEventListener('input', updateWeightPreview);
    document.getElementById('optimal_weight').addEventListener('input', updateWeightPreview);
    
    // Initial preview update
    updateWeightPreview();
</script>
</x-app-layout>
