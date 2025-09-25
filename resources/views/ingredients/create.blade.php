<x-app-layout>
    <div class="container mx-auto py-2 max-w-5xl">
        <div class="bg-white rounded-xl shadow-md border border-gray-200">
            <div class="px-6 py-4 border-b border-gray-100">
                <h1 class="text-2xl font-bold text-gray-800">New Feed Item</h1>
                <p class="text-sm text-gray-500">Fill in the details below to add a new feed material to your records.</p>
            </div>

            <form method="POST" action="{{ route('ingredients.store') }}" class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                @csrf

                <!-- Name -->
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                    <input type="text" name="name" value="{{ old('name') }}" 
                        class="w-full border-gray-300 rounded-lg px-3 py-2 shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                </div>

                <!-- Description -->
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea name="description" rows="3" 
                        class="w-full border-gray-300 rounded-lg px-3 py-2 shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">{{ old('description') }}</textarea>
                </div>

                <!-- Protein -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Protein %</label>
                    <input type="number" step="0.01" min="0" max="100" name="protein_percentage" value="{{ old('protein_percentage') }}"
                        class="w-full border-gray-300 rounded-lg px-3 py-2 shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>

                <!-- Energy -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Energy (kcal/kg)</label>
                    <input type="number" step="0.01" min="0" name="energy_kcal_per_kg" value="{{ old('energy_kcal_per_kg') }}"
                        class="w-full border-gray-300 rounded-lg px-3 py-2 shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>

                <!-- Fat -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Fat %</label>
                    <input type="number" step="0.01" min="0" max="100" name="fat_percentage" value="{{ old('fat_percentage') }}"
                        class="w-full border-gray-300 rounded-lg px-3 py-2 shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>

                <!-- Fiber -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Fiber %</label>
                    <input type="number" step="0.01" min="0" max="100" name="fiber_percentage" value="{{ old('fiber_percentage') }}"
                        class="w-full border-gray-300 rounded-lg px-3 py-2 shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>

                <!-- Unit -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Unit</label>
                    <input type="text" name="unit" value="{{ old('unit','kg') }}" 
                        class="w-full border-gray-300 rounded-lg px-3 py-2 shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                </div>

                <!-- Cost -->
                <!-- <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Cost per Unit (GHS)</label>
                    <input type="number" step="0.01" min="0" name="cost_per_unit" value="{{ old('cost_per_unit','0') }}"
                        class="w-full border-gray-300 rounded-lg px-3 py-2 shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                </div> -->

                <!-- Quantity -->
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Quantity Purchased</label>
                    <input type="number" step="0.01" min="0" name="initial_quantity" value="{{ old('initial_quantity') }}"
                        class="w-full border-gray-300 rounded-lg px-3 py-2 shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>

                <!-- Buttons -->
                <div class="md:col-span-2 flex justify-end space-x-3">
                    <a href="{{ route('ingredients.index') }}" 
                        class="px-4 py-2 border rounded-lg text-gray-700 hover:bg-gray-100">Cancel</a>
                    <button type="submit" 
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 shadow-sm">💾 Save</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
