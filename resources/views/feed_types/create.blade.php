<x-app-layout>
    <div class="max-w-6xl mx-auto py-2 px-4 sm:px-6 lg:px-8">
        
        <form method="POST" action="{{ route('feed-types.store') }}" class="bg-white p-8 rounded-xl shadow-2xl border border-gray-100 space-y-6">
            @csrf

            <div class="space-y-4">
                <h2 class="text-xl font-bold text-gray-800 border-b pb-2 mb-4">General Details</h2>
                
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                        <input type="text" id="name" name="name" class="w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm px-4 py-2" required value="{{ old('name') }}">
                    </div>

                    <div>
                        <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                        <input type="text" id="description" name="description" class="w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm px-4 py-2" value="{{ old('description') }}">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-5 gap-6">
                    <div>
                        <label for="protein_percent" class="block text-sm font-medium text-gray-700 mb-1">Protein (%)</label>
                        <input type="number" id="protein_percent" name="protein_percent" class="w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm px-4 py-2" step="0.01" min="0" max="100" value="{{ old('protein_percent') }}">
                    </div>

                    <div>
                        <label for="energy_mj_per_kg" class="block text-sm font-medium text-gray-700 mb-1">Energy (MJ/kg)</label>
                        <input type="number" id="energy_mj_per_kg" name="energy_mj_per_kg" class="w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm px-4 py-2" step="0.01" min="0" value="{{ old('energy_mj_per_kg') }}">
                    </div>

                    <div>
                        <label for="fiber_percent" class="block text-sm font-medium text-gray-700 mb-1">Fiber (%)</label>
                        <input type="number" id="fiber_percent" name="fiber_percent" class="w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm px-4 py-2" step="0.01" min="0" max="100" value="{{ old('fiber_percent') }}">
                    </div>
                    
                    <div>
                        <label for="cost_per_unit" class="block text-sm font-medium text-gray-700 mb-1">Cost (₵)</label>
                        <input type="number" id="cost_per_unit" name="cost_per_unit" class="w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm px-4 py-2" step="0.01" min="0" value="{{ old('cost_per_unit') }}">
                    </div>

                    <div>
                        <label for="unit" class="block text-sm font-medium text-gray-700 mb-1">Unit</label>
                        <input type="text" id="unit" name="unit" class="w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm px-4 py-2" value="{{ old('unit', 'kg') }}" required>
                    </div>
                </div>
            </div>

            <div class="pt-6 border-t border-gray-200">
                <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center justify-between">
                    Recipe Ingredients
                    <button type="button" id="add-item" 
                            class="inline-flex items-center px-3 py-1 border border-transparent text-sm font-medium rounded-full shadow-sm text-indigo-700 bg-indigo-100 hover:bg-indigo-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                        Add Ingredient
                    </button>
                </h2>
                
                <div id="items" class="space-y-3">
                    <div class="grid grid-cols-12 gap-3 p-2 bg-gray-50 rounded-lg ingredient-row">
                        <div class="col-span-8">
                            <select name="items[0][ingredient_id]" class="w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm text-sm px-3 py-2" required>
                                <option value="">-- Select ingredient --</option>
                                @foreach($ingredients as $ing)
                                    <!-- Use the new display properties for clarity -->
                                    <option value="{{ $ing->id }}">
                                        {{ $ing->name }} (Stock: {{ $ing->current_stock_display ?? 0 }} {{ $ing->current_stock_unit ?? 'units' }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-span-3">
                            <input type="number" step="0.01" min="0" name="items[0][quantity]" class="w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm text-sm px-3 py-2" placeholder="Qty (kg)" required>
                        </div>
                        <div class="col-span-1 text-center flex items-center justify-center text-sm font-medium text-gray-500">
                            kg
                        </div>
                    </div>
                </div>
            </div>

            <div class="pt-6 border-t border-gray-200">
                <h2 class="text-xl font-bold text-gray-800 mb-4">Batch Production</h2>
                <div class="max-w-xs">
                    <label for="batch_size" class="block text-sm font-medium text-gray-700 mb-1">Produce Batch (kg)</label>
                    <input type="number" id="batch_size" step="0.01" min="0" name="batch_size" class="w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm px-4 py-2" placeholder="e.g., 100">
                    <p class="text-xs text-gray-500 mt-2">Deducts ingredient on-hand stock and increases feed stock by this amount.</p>
                </div>
            </div>

            <div class="pt-6 border-t border-gray-200 flex justify-end space-x-3">
                <a href="{{ route('feed-types.index') }}" 
                   class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50 transition shadow-sm">
                   Cancel
                </a>
                <button type="submit" 
                        class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-lg shadow-sm text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition">
                    <svg class="w-4 h-4 mr-2 -ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                    Save New Feed Type
                </button>
            </div>
        </form>
    </div>
    <x-copyright-minimal />
    
    <script>
        // The ingredientsData is now augmented with current_stock_display and current_stock_unit
        const ingredientsData = @json($ingredients);

        const container = document.getElementById('items');
        let index = 1; 

        // Function to build the options HTML with stock info
        function buildIngredientOptions() {
            let options = '<option value="">-- Select ingredient --</option>';
            ingredientsData.forEach(ing => {
                // Access the new 'current_stock_display' and 'current_stock_unit' properties
                const stock = ing.current_stock_display || '0';
                const unit = ing.current_stock_unit || 'units';

                options += `<option value="${ing.id}">${ing.name} (Stock: ${stock} ${unit})</option>`;
            });
            return options;
        }

        document.getElementById('add-item').addEventListener('click', () => {
            const optionsHtml = buildIngredientOptions();
            const row = document.createElement('div');
            row.className = 'grid grid-cols-12 gap-3 p-2 bg-white border border-gray-200 rounded-lg ingredient-row';
            
            row.innerHTML = `
                <div class="col-span-8">
                    <select name="items[${index}][ingredient_id]" class="w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm text-sm px-3 py-2" required>
                        ${optionsHtml}
                    </select>
                </div>
                <div class="col-span-3">
                    <input type="number" step="0.01" min="0" name="items[${index}][quantity]" class="w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm text-sm px-3 py-2" placeholder="Qty (kg)" required>
                </div>
                <div class="col-span-1 text-center flex items-center justify-center text-sm font-medium text-gray-500">
                    <button type="button" class="text-red-500 hover:text-red-700 remove-item" title="Remove Ingredient">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                    </button>
                </div>
            `;
            container.appendChild(row);
            
            // Add removal functionality for the new row
            row.querySelector('.remove-item').addEventListener('click', () => {
                row.remove();
            });

            index++;
        });
    </script>
</x-app-layout>
