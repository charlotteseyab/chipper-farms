<x-app-layout>
<div class="container mx-auto px-4 py-6">
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Edit Weight Standard</h1>
            <p class="text-gray-600 mt-1">{{ $weightStandard->breed_display }} {{ $weightStandard->gender_display }} - Week {{ $weightStandard->week_number }}</p>
        </div>
        <div class="flex space-x-3">
            <a href="{{ route('weight-standards.show', $weightStandard) }}" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition">
                <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                </svg>
                View Standard
            </a>
            <a href="{{ route('weight-standards.index') }}" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition">
                <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Back to Standards
            </a>
        </div>
    </div>

    <!-- Form -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <form method="POST" action="{{ route('weight-standards.update', $weightStandard) }}">
            @csrf
            @method('PUT')
            
            <!-- Read-only Information -->
            <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 mb-6">
                <h3 class="text-sm font-medium text-gray-700 mb-3">Standard Identification (Cannot be changed)</h3>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Breed</label>
                        <div class="text-sm font-medium text-gray-900">{{ $weightStandard->breed_display }}</div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Gender</label>
                        <div class="text-sm font-medium text-gray-900">{{ $weightStandard->gender_display }}</div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Stock Type</label>
                        <div class="text-sm font-medium text-gray-900">{{ $weightStandard->stock_type_display }}</div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Week Number</label>
                        <div class="text-sm font-medium text-gray-900">Week {{ $weightStandard->week_number }}</div>
                    </div>
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Expected Min Weight -->
                <div>
                    <label for="expected_min_weight" class="block text-sm font-medium text-gray-700 mb-1">Minimum Weight (kg) *</label>
                    <input type="number" name="expected_min_weight" id="expected_min_weight" step="0.1" min="0.1" max="500" required
                           value="{{ old('expected_min_weight', $weightStandard->expected_min_weight) }}" placeholder="e.g., 15.0"
                           class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('expected_min_weight') border-red-300 @enderror">
                    @error('expected_min_weight')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Expected Max Weight -->
                <div>
                    <label for="expected_max_weight" class="block text-sm font-medium text-gray-700 mb-1">Maximum Weight (kg) *</label>
                    <input type="number" name="expected_max_weight" id="expected_max_weight" step="0.1" min="0.1" max="500" required
                           value="{{ old('expected_max_weight', $weightStandard->expected_max_weight) }}" placeholder="e.g., 20.0"
                           class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('expected_max_weight') border-red-300 @enderror">
                    @error('expected_max_weight')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Optimal Weight -->
                <div>
                    <label for="optimal_weight" class="block text-sm font-medium text-gray-700 mb-1">Optimal Weight (kg) *</label>
                    <input type="number" name="optimal_weight" id="optimal_weight" step="0.1" min="0.1" max="500" required
                           value="{{ old('optimal_weight', $weightStandard->optimal_weight) }}" placeholder="e.g., 17.5"
                           class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('optimal_weight') border-red-300 @enderror">
                    <p class="mt-1 text-xs text-gray-500">Target weight for best performance</p>
                    @error('optimal_weight')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Active Status -->
                <div>
                    <label for="is_active" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <div class="flex items-center mt-3">
                        <input type="checkbox" name="is_active" id="is_active" value="1" 
                               {{ old('is_active', $weightStandard->is_active) ? 'checked' : '' }}
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
                          class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('notes') border-red-300 @enderror">{{ old('notes', $weightStandard->notes) }}</textarea>
                @error('notes')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <!-- Weight Range Preview -->
            <div class="mt-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                <h3 class="text-sm font-medium text-blue-800 mb-2">Weight Range Preview</h3>
                <div id="weight-preview" class="text-sm text-blue-700">
                    Current range: {{ $weightStandard->weight_range_display }}, Optimal: {{ number_format($weightStandard->optimal_weight, 1) }}kg
                </div>
            </div>

            <!-- Change Summary -->
            <div class="mt-6 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                <h3 class="text-sm font-medium text-yellow-800 mb-2">
                    <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                    </svg>
                    Important Note
                </h3>
                <p class="text-sm text-yellow-700">
                    Changing this weight standard will affect future weight evaluations for {{ $weightStandard->breed_display }} {{ $weightStandard->gender_display }} pigs at {{ $weightStandard->week_number }} weeks old. 
                    Existing weight alerts will not be recalculated.
                </p>
            </div>

            <!-- Form Actions -->
            <div class="mt-8 flex justify-end space-x-3">
                <a href="{{ route('weight-standards.show', $weightStandard) }}" class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition">
                    Cancel
                </a>
                <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                    <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Update Weight Standard
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
                        <div><strong>New Range:</strong> ${min.toFixed(1)} - ${max.toFixed(1)} kg</div>
                        <div><strong>New Optimal:</strong> ${optimal.toFixed(1)} kg</div>
                        <div><strong>Tolerance:</strong> ±${((max - min) / 2).toFixed(1)} kg</div>
                    </div>
                `;
                preview.className = 'text-sm text-green-700';
            } else {
                preview.innerHTML = 'Invalid weight values. Please check: Min < Optimal < Max';
                preview.className = 'text-sm text-red-700';
            }
        } else {
            preview.innerHTML = 'Current range: {{ $weightStandard->weight_range_display }}, Optimal: {{ number_format($weightStandard->optimal_weight, 1) }}kg';
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
