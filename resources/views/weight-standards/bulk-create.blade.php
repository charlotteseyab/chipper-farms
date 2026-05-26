<x-app-layout>
<div class="container mx-auto px-4 py-6">
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Bulk Create Weight Standards</h1>
            <p class="text-gray-600 mt-1">Create multiple weight standards for a range of weeks with automatic growth calculations</p>
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
        <form method="POST" action="{{ route('weight-standards.bulk-store') }}">
            @csrf
            
            <!-- Pig Type Selection -->
            <div class="mb-8">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Pig Type Selection</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- Breed -->
                    <div>
                        <label for="breed" class="block text-sm font-medium text-gray-700 mb-1">Breed *</label>
                        <select name="breed" id="breed" required 
                                class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('breed') border-red-300 @enderror">
                            <option value="">Select Breed</option>
                            <option value="LW" {{ old('breed') === 'LW' ? 'selected' : '' }}>Large White</option>
                            <option value="DR" {{ old('breed') === 'DR' ? 'selected' : '' }}>Duroc</option>
                            <option value="LWDR" {{ old('breed') === 'LWDR' ? 'selected' : '' }}>Large White Duroc</option>
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
                </div>
            </div>

            <!-- Week Range -->
            <div class="mb-8">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Week Range</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="week_from" class="block text-sm font-medium text-gray-700 mb-1">From Week *</label>
                        <input type="number" name="week_from" id="week_from" min="1" max="104" required
                               value="{{ old('week_from', 1) }}" placeholder="1"
                               class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('week_from') border-red-300 @enderror">
                        @error('week_from')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="week_to" class="block text-sm font-medium text-gray-700 mb-1">To Week *</label>
                        <input type="number" name="week_to" id="week_to" min="1" max="104" required
                               value="{{ old('week_to', 20) }}" placeholder="20"
                               class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('week_to') border-red-300 @enderror">
                        <p class="mt-1 text-xs text-gray-500">Standards will be created for each week in this range</p>
                        @error('week_to')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            <!-- Starting Weights -->
            <div class="mb-8">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Starting Weights (for first week)</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label for="min_weight_start" class="block text-sm font-medium text-gray-700 mb-1">Starting Min Weight (kg) *</label>
                        <input type="number" name="min_weight_start" id="min_weight_start" step="0.1" min="0.1" max="500" required
                               value="{{ old('min_weight_start', 2.0) }}" placeholder="2.0"
                               class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('min_weight_start') border-red-300 @enderror">
                        @error('min_weight_start')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="max_weight_start" class="block text-sm font-medium text-gray-700 mb-1">Starting Max Weight (kg) *</label>
                        <input type="number" name="max_weight_start" id="max_weight_start" step="0.1" min="0.1" max="500" required
                               value="{{ old('max_weight_start', 3.0) }}" placeholder="3.0"
                               class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('max_weight_start') border-red-300 @enderror">
                        @error('max_weight_start')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="optimal_weight_start" class="block text-sm font-medium text-gray-700 mb-1">Starting Optimal Weight (kg) *</label>
                        <input type="number" name="optimal_weight_start" id="optimal_weight_start" step="0.1" min="0.1" max="500" required
                               value="{{ old('optimal_weight_start', 2.5) }}" placeholder="2.5"
                               class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('optimal_weight_start') border-red-300 @enderror">
                        @error('optimal_weight_start')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            <!-- Growth Rate -->
            <div class="mb-8">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Growth Rate</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="weekly_growth_rate" class="block text-sm font-medium text-gray-700 mb-1">Weekly Growth Rate (kg/week) *</label>
                        <input type="number" name="weekly_growth_rate" id="weekly_growth_rate" step="0.1" min="0" max="10" required
                               value="{{ old('weekly_growth_rate', 0.5) }}" placeholder="0.5"
                               class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('weekly_growth_rate') border-red-300 @enderror">
                        <p class="mt-1 text-xs text-gray-500">Average weight gain per week (e.g., 0.5kg per week)</p>
                        @error('weekly_growth_rate')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex items-end">
                        <div class="w-full">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Growth Pattern</label>
                            <div class="text-sm text-gray-600 bg-gray-50 border border-gray-200 rounded-lg p-3">
                                <div id="growth-preview">Enter values to see growth pattern</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Notes -->
            <div class="mb-8">
                <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                <textarea name="notes" id="notes" rows="3" placeholder="Optional notes for all standards in this batch..."
                          class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('notes') border-red-300 @enderror">{{ old('notes') }}</textarea>
                @error('notes')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <!-- Preview -->
            <div class="mb-8 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                <h3 class="text-sm font-medium text-blue-800 mb-3">Bulk Creation Preview</h3>
                <div id="bulk-preview" class="text-sm text-blue-700">
                    Configure the settings above to see a preview of the standards that will be created
                </div>
            </div>

            <!-- Form Actions -->
            <div class="flex justify-end space-x-3">
                <a href="{{ route('weight-standards.index') }}" class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition">
                    Cancel
                </a>
                <button type="submit" class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                    <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                    </svg>
                    Create Weight Standards
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function updatePreview() {
        const weekFrom = parseInt(document.getElementById('week_from').value) || 1;
        const weekTo = parseInt(document.getElementById('week_to').value) || 1;
        const minStart = parseFloat(document.getElementById('min_weight_start').value) || 0;
        const maxStart = parseFloat(document.getElementById('max_weight_start').value) || 0;
        const optimalStart = parseFloat(document.getElementById('optimal_weight_start').value) || 0;
        const growthRate = parseFloat(document.getElementById('weekly_growth_rate').value) || 0;
        
        const growthPreview = document.getElementById('growth-preview');
        const bulkPreview = document.getElementById('bulk-preview');
        
        if (weekFrom <= weekTo && minStart > 0 && maxStart > minStart && optimalStart >= minStart && optimalStart <= maxStart && growthRate >= 0) {
            const totalWeeks = weekTo - weekFrom + 1;
            const finalOptimal = optimalStart + (growthRate * (totalWeeks - 1));
            
            growthPreview.innerHTML = `
                <div><strong>Week ${weekFrom}:</strong> ${optimalStart.toFixed(1)}kg (optimal)</div>
                <div><strong>Week ${weekTo}:</strong> ${finalOptimal.toFixed(1)}kg (optimal)</div>
                <div><strong>Total Growth:</strong> ${(finalOptimal - optimalStart).toFixed(1)}kg over ${totalWeeks} weeks</div>
            `;
            
            bulkPreview.innerHTML = `
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <strong>Standards to create:</strong> ${totalWeeks} standards<br>
                        <strong>Week range:</strong> ${weekFrom} to ${weekTo}<br>
                        <strong>Growth rate:</strong> ${growthRate}kg/week
                    </div>
                    <div>
                        <strong>Starting range:</strong> ${minStart.toFixed(1)} - ${maxStart.toFixed(1)}kg<br>
                        <strong>Final range:</strong> ${(minStart + growthRate * (totalWeeks - 1)).toFixed(1)} - ${(maxStart + growthRate * (totalWeeks - 1)).toFixed(1)}kg<br>
                        <strong>Total weight gain:</strong> ${(growthRate * (totalWeeks - 1)).toFixed(1)}kg
                    </div>
                </div>
            `;
        } else {
            growthPreview.innerHTML = 'Enter valid values to see growth pattern';
            bulkPreview.innerHTML = 'Configure the settings above to see a preview of the standards that will be created';
        }
    }
    
    // Add event listeners
    ['week_from', 'week_to', 'min_weight_start', 'max_weight_start', 'optimal_weight_start', 'weekly_growth_rate'].forEach(id => {
        document.getElementById(id).addEventListener('input', updatePreview);
    });
    
    // Initial preview
    updatePreview();
</script>
</x-app-layout>
