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

            <!-- Current Values Summary -->
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                <h3 class="text-sm font-medium text-blue-800 mb-3">📊 Current Values</h3>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-blue-600 mb-1">Weight Range</label>
                        <div class="text-sm font-semibold text-blue-900">{{ $weightStandard->weight_range_display }}</div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-blue-600 mb-1">Optimal Weight</label>
                        <div class="text-sm font-semibold text-blue-900">{{ number_format($weightStandard->optimal_weight, 1) }} kg</div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-blue-600 mb-1">Tolerance</label>
                        <div class="text-sm font-semibold text-blue-900">±{{ number_format(($weightStandard->expected_max_weight - $weightStandard->expected_min_weight) / 2, 1) }} kg</div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-blue-600 mb-1">Status</label>
                        <div class="text-sm font-semibold text-blue-900">
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium {{ $weightStandard->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                {{ $weightStandard->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </div>
                    </div>
                </div>
                @if($weightStandard->notes)
                    <div class="mt-3">
                        <label class="block text-xs font-medium text-blue-600 mb-1">Current Notes</label>
                        <div class="text-sm text-blue-900">{{ $weightStandard->notes }}</div>
                    </div>
                @endif
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Expected Min Weight -->
                <div>
                    <label for="expected_min_weight" class="block text-sm font-medium text-gray-700 mb-1">
                        Minimum Weight (kg) *
                        <span id="min-weight-indicator" class="ml-2 text-xs font-normal text-gray-500">(Current: {{ number_format($weightStandard->expected_min_weight, 1) }} kg)</span>
                    </label>
                    <input type="number" name="expected_min_weight" id="expected_min_weight" step="0.1" min="0.1" max="500" required
                           value="{{ old('expected_min_weight', $weightStandard->expected_min_weight) }}" placeholder="e.g., 15.0"
                           class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('expected_min_weight') border-red-300 @enderror">
                    @error('expected_min_weight')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Expected Max Weight -->
                <div>
                    <label for="expected_max_weight" class="block text-sm font-medium text-gray-700 mb-1">
                        Maximum Weight (kg) *
                        <span id="max-weight-indicator" class="ml-2 text-xs font-normal text-gray-500">(Current: {{ number_format($weightStandard->expected_max_weight, 1) }} kg)</span>
                    </label>
                    <input type="number" name="expected_max_weight" id="expected_max_weight" step="0.1" min="0.1" max="500" required
                           value="{{ old('expected_max_weight', $weightStandard->expected_max_weight) }}" placeholder="e.g., 20.0"
                           class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('expected_max_weight') border-red-300 @enderror">
                    @error('expected_max_weight')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Optimal Weight -->
                <div>
                    <label for="optimal_weight" class="block text-sm font-medium text-gray-700 mb-1">
                        Optimal Weight (kg) *
                        <span id="optimal-weight-indicator" class="ml-2 text-xs font-normal text-gray-500">(Current: {{ number_format($weightStandard->optimal_weight, 1) }} kg)</span>
                    </label>
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
                    <label for="is_active" class="block text-sm font-medium text-gray-700 mb-1">
                        Status
                        <span id="active-status-indicator" class="ml-2 text-xs font-normal text-gray-500">
                            (Current: {{ $weightStandard->is_active ? 'Active' : 'Inactive' }})
                        </span>
                    </label>
                    <div class="flex items-center mt-3">
                        <input type="checkbox" name="is_active" id="is_active" value="1" 
                               {{ old('is_active', $weightStandard->is_active) ? 'checked' : '' }}
                               class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                        <label for="is_active" class="ml-2 block text-sm text-gray-900">
                            Active (use this standard for weight checking)
                        </label>
                    </div>
                    <p class="mt-1 text-xs text-gray-500">
                        When unchecked, this standard will not be used for weight evaluations
                    </p>
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

            <!-- Changes Summary -->
            <div id="changes-summary" class="mt-6 p-4 bg-green-50 border border-green-200 rounded-lg hidden">
                <h3 class="text-sm font-medium text-green-800 mb-2">
                    <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Changes Summary
                </h3>
                <div id="changes-content" class="text-sm text-green-700">
                    <!-- Changes will be populated by JavaScript -->
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
<x-copyright-minimal />
<script>
    // Store original values for comparison
    const originalValues = {
        minWeight: {{ $weightStandard->expected_min_weight }},
        maxWeight: {{ $weightStandard->expected_max_weight }},
        optimalWeight: {{ $weightStandard->optimal_weight }},
        isActive: {{ $weightStandard->is_active ? 'true' : 'false' }},
        notes: '{{ $weightStandard->notes ?? '' }}'
    };

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
        
        // Update changes summary
        updateChangesSummary();
    }

    // Update changes summary
    function updateChangesSummary() {
        const minWeight = parseFloat(document.getElementById('expected_min_weight').value) || 0;
        const maxWeight = parseFloat(document.getElementById('expected_max_weight').value) || 0;
        const optimalWeight = parseFloat(document.getElementById('optimal_weight').value) || 0;
        const isActive = document.getElementById('is_active').checked;
        const notes = document.getElementById('notes').value;
        
        const changes = [];
        
        // Check for changes and update field indicators
        if (minWeight !== originalValues.minWeight) {
            changes.push(`Min weight: ${originalValues.minWeight} → ${minWeight.toFixed(1)} kg`);
            document.getElementById('min-weight-indicator').className = 'ml-2 text-xs font-normal text-orange-600 font-semibold';
            document.getElementById('min-weight-indicator').textContent = `(Changed from ${originalValues.minWeight} kg)`;
        } else {
            document.getElementById('min-weight-indicator').className = 'ml-2 text-xs font-normal text-gray-500';
            document.getElementById('min-weight-indicator').textContent = `(Current: ${originalValues.minWeight} kg)`;
        }
        
        if (maxWeight !== originalValues.maxWeight) {
            changes.push(`Max weight: ${originalValues.maxWeight} → ${maxWeight.toFixed(1)} kg`);
            document.getElementById('max-weight-indicator').className = 'ml-2 text-xs font-normal text-orange-600 font-semibold';
            document.getElementById('max-weight-indicator').textContent = `(Changed from ${originalValues.maxWeight} kg)`;
        } else {
            document.getElementById('max-weight-indicator').className = 'ml-2 text-xs font-normal text-gray-500';
            document.getElementById('max-weight-indicator').textContent = `(Current: ${originalValues.maxWeight} kg)`;
        }
        
        if (optimalWeight !== originalValues.optimalWeight) {
            changes.push(`Optimal weight: ${originalValues.optimalWeight} → ${optimalWeight.toFixed(1)} kg`);
            document.getElementById('optimal-weight-indicator').className = 'ml-2 text-xs font-normal text-orange-600 font-semibold';
            document.getElementById('optimal-weight-indicator').textContent = `(Changed from ${originalValues.optimalWeight} kg)`;
        } else {
            document.getElementById('optimal-weight-indicator').className = 'ml-2 text-xs font-normal text-gray-500';
            document.getElementById('optimal-weight-indicator').textContent = `(Current: ${originalValues.optimalWeight} kg)`;
        }
        
        if (isActive !== originalValues.isActive) {
            changes.push(`Status: ${originalValues.isActive ? 'Active' : 'Inactive'} → ${isActive ? 'Active' : 'Inactive'}`);
            document.getElementById('active-status-indicator').className = 'ml-2 text-xs font-normal text-orange-600 font-semibold';
            document.getElementById('active-status-indicator').textContent = `(Changed from ${originalValues.isActive ? 'Active' : 'Inactive'})`;
        } else {
            document.getElementById('active-status-indicator').className = 'ml-2 text-xs font-normal text-gray-500';
            document.getElementById('active-status-indicator').textContent = `(Current: ${originalValues.isActive ? 'Active' : 'Inactive'})`;
        }
        
        if (notes !== originalValues.notes) {
            changes.push(`Notes: ${originalValues.notes || 'None'} → ${notes || 'None'}`);
        }
        
        const changesSummary = document.getElementById('changes-summary');
        const changesContent = document.getElementById('changes-content');
        
        if (changes.length > 0) {
            changesSummary.classList.remove('hidden');
            changesContent.innerHTML = `
                <div class="space-y-1">
                    ${changes.map(change => `<div>• ${change}</div>`).join('')}
                </div>
            `;
        } else {
            changesSummary.classList.add('hidden');
        }
    }
    
    // Add event listeners
    document.getElementById('expected_min_weight').addEventListener('input', updateWeightPreview);
    document.getElementById('expected_max_weight').addEventListener('input', updateWeightPreview);
    document.getElementById('optimal_weight').addEventListener('input', updateWeightPreview);
    document.getElementById('is_active').addEventListener('change', updateChangesSummary);
    document.getElementById('notes').addEventListener('input', updateChangesSummary);
    
    // Initial preview update
    updateWeightPreview();
</script>
</x-app-layout>
