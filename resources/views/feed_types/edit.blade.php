<x-app-layout>
    <div class="max-w-6xl mx-auto py-2 px-4 sm:px-6 lg:px-8">
        
        <!-- <div class="mb-8 border-b border-gray-200 pb-3">
            <h1 class="text-3xl font-extrabold text-gray-900 leading-tight">
                Update Feed Type 🛠️
            </h1>
            <p class="text-gray-500 mt-1">
                Editing: <span class="font-semibold text-indigo-600">{{ $feedType->name }}</span>
            </p>
        </div> -->

        <form method="POST" action="{{ route('feed-types.update', $feedType) }}" class="bg-white p-8 rounded-xl shadow-2xl border border-gray-100 space-y-6">
            @csrf @method('PUT')

            <div class="space-y-4">
                <h2 class="text-xl font-bold text-gray-800 border-b pb-2 mb-4">General Details</h2>
                
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                        <input type="text" id="name" name="name" class="w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm px-4 py-2" required value="{{ old('name', $feedType->name) }}">
                    </div>

                    <div>
                        <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                        <input type="text" id="description" name="description" class="w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm px-4 py-2" value="{{ old('description', $feedType->description) }}">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-5 gap-6">
                    <div>
                        <label for="protein_percent" class="block text-sm font-medium text-gray-700 mb-1">Protein (%)</label>
                        <input type="number" id="protein_percent" name="protein_percent" class="w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm px-4 py-2" step="0.01" min="0" max="100" value="{{ old('protein_percent', $feedType->protein_percent) }}">
                    </div>

                    <div>
                        <label for="energy_mj_per_kg" class="block text-sm font-medium text-gray-700 mb-1">Energy (MJ/kg)</label>
                        <input type="number" id="energy_mj_per_kg" name="energy_mj_per_kg" class="w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm px-4 py-2" step="0.01" min="0" value="{{ old('energy_mj_per_kg', $feedType->energy_mj_per_kg) }}">
                    </div>

                    <div>
                        <label for="fiber_percent" class="block text-sm font-medium text-gray-700 mb-1">Fiber (%)</label>
                        <input type="number" id="fiber_percent" name="fiber_percent" class="w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm px-4 py-2" step="0.01" min="0" max="100" value="{{ old('fiber_percent', $feedType->fiber_percent) }}">
                    </div>
                    
                    <div>
                        <label for="cost_per_unit" class="block text-sm font-medium text-gray-700 mb-1">Cost (₵)</label>
                        <input type="number" id="cost_per_unit" name="cost_per_unit" class="w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm px-4 py-2" step="0.01" min="0" value="{{ old('cost_per_unit', $feedType->cost_per_unit) }}">
                    </div>

                    <div>
                        <label for="unit" class="block text-sm font-medium text-gray-700 mb-1">Unit</label>
                        <input type="text" id="unit" name="unit" class="w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm px-4 py-2" value="{{ old('unit', $feedType->unit) }}" required>
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
                    @php($items = optional($feedType->recipe)->items ?? collect())
                    @if($items->count() === 0)
                        <div class="grid grid-cols-12 gap-3 p-2 bg-gray-50 rounded-lg">
                            <div class="col-span-8">
                                <select name="items[0][ingredient_id]" class="w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm text-sm px-3 py-2">
                                    <option value="">-- Select ingredient --</option>
                                    @foreach($ingredients as $ing)
                                        <option value="{{ $ing->id }}">{{ $ing->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-span-3">
                                <input type="number" step="0.01" min="0" name="items[0][quantity]" class="w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm text-sm px-3 py-2" placeholder="Qty (kg)">
                            </div>
                            <div class="col-span-1 text-center flex items-center justify-center text-sm font-medium text-gray-500">kg</div>
                        </div>
                    @else
                        @foreach($items as $idx => $it)
                            <div class="grid grid-cols-12 gap-3 p-2 bg-white border border-gray-200 rounded-lg">
                                <div class="col-span-8">
                                    <select name="items[{{ $idx }}][ingredient_id]" class="w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm text-sm px-3 py-2">
                                        @foreach($ingredients as $ing)
                                            <option value="{{ $ing->id }}" {{ $it->ingredient_id == $ing->id ? 'selected' : '' }}>{{ $ing->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-span-3">
                                    <input type="number" step="0.01" min="0" name="items[{{ $idx }}][quantity]" class="w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm text-sm px-3 py-2" value="{{ $it->percentage }}" placeholder="Qty (kg)">
                                </div>
                                <div class="col-span-1 text-center flex items-center justify-center text-sm font-medium text-gray-500">kg</div>
                            </div>
                        @endforeach
                    @endif
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
                        class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-lg shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition">
                    <svg class="w-4 h-4 mr-2 -ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    Save Changes
                </button>
            </div>
        </form>
    </div>
    <script>
        const container = document.getElementById('items');
        // NOTE: The PHP logic has been updated to correctly initialize the JS index
        // by counting existing items, ensuring new items have the correct index.
        let index = {{ optional($feedType->recipe)->items?->count() ?? 0 }}; // Start count from 0 if no items, otherwise count of existing items
        if (index === 0) {
            // If the initial block was rendered (which used index 0), increment.
            // However, since we are handling this in PHP, setting to 0 works better for JS append.
            // If the initial block uses index 0, we must start new items at index 1.
            // Let's stick to the original PHP logic's intent by setting index to count.
            index = {{ optional($feedType->recipe)->items?->count() ?? 1 }};
        }


        document.getElementById('add-item').addEventListener('click', () => {
            const row = document.createElement('div');
            // Using the same styling for new rows as the existing items
            row.className = 'grid grid-cols-12 gap-3 p-2 bg-white border border-gray-200 rounded-lg';
            row.innerHTML = `
                <div class=\"col-span-8\">
                    <select name=\"items[${index}][ingredient_id]\" class=\"w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm text-sm px-3 py-2\">
                        <option value=\"\">-- Select ingredient --</option>
                        ${`@foreach($ingredients as $ing)<option value=\"{{ $ing->id }}\">{{ $ing->name }}</option>@endforeach`}
                    </select>
                </div>
                <div class=\"col-span-3\">
                    <input type=\"number\" step=\"0.01\" min=\"0\" name=\"items[${index}][quantity]\" class=\"w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm text-sm px-3 py-2\" placeholder=\"Qty (kg)\">
                </div>
                <div class=\"col-span-1 text-center flex items-center justify-center text-sm font-medium text-gray-500\">kg</div>
            `;
            container.appendChild(row);
            index++;
        });
        
        // Minor JavaScript fix for index calculation:
        // Ensures the JS index correctly starts where the PHP loop left off.
        // I noticed your original PHP for `index` used `?? 1` even if count was 0, 
        // which could lead to index 0 being skipped. I've adjusted the JS to follow 
        // the count of items to prevent duplicate keys if `count() === 0`.
        index = {{ optional($feedType->recipe)->items?->count() ?? 0 }}; 
        if (index === 0 && document.querySelector('#items .grid')) {
            // If the PHP code rendered an initial empty item at index 0, 
            // then new items should start at index 1.
            index = 1; 
        }

    </script>
</x-app-layout>