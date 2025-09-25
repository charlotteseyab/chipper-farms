<x-app-layout>
    <div class="container mx-auto py-2 max-w-5xl">
        <div class="bg-white rounded-xl shadow-md border border-gray-200">
            <!-- Header -->
            <div class="px-6 py-4 border-b border-gray-100">
                <h1 class="text-2xl font-bold text-gray-800">Edit Feed Material</h1>
                <p class="text-sm text-gray-500">Update details for <span class="font-semibold text-gray-700">{{ $ingredient->name }}</span>.</p>
            </div>

            <!-- Form -->
            <form method="POST" action="{{ route('ingredients.update', $ingredient) }}" class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                @csrf
                @method('PUT')

                <!-- Name -->
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                    <input type="text" name="name" value="{{ old('name', $ingredient->name) }}"
                        class="w-full border-gray-300 rounded-lg px-3 py-2 shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                </div>

                <!-- Description -->
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea name="description" rows="3"
                        class="w-full border-gray-300 rounded-lg px-3 py-2 shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">{{ old('description', $ingredient->description) }}</textarea>
                </div>

                <!-- Protein -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Protein %</label>
                    <input type="number" step="0.01" min="0" max="100" name="protein_percentage"
                        value="{{ old('protein_percentage', $ingredient->protein_percentage) }}"
                        class="w-full border-gray-300 rounded-lg px-3 py-2 shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>

                <!-- Energy -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Energy (kcal/kg)</label>
                    <input type="number" step="0.01" min="0" name="energy_kcal_per_kg"
                        value="{{ old('energy_kcal_per_kg', $ingredient->energy_kcal_per_kg) }}"
                        class="w-full border-gray-300 rounded-lg px-3 py-2 shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>

                <!-- Fat -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Fat %</label>
                    <input type="number" step="0.01" min="0" max="100" name="fat_percentage"
                        value="{{ old('fat_percentage', $ingredient->fat_percentage) }}"
                        class="w-full border-gray-300 rounded-lg px-3 py-2 shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>

                <!-- Fiber -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Fiber %</label>
                    <input type="number" step="0.01" min="0" max="100" name="fiber_percentage"
                        value="{{ old('fiber_percentage', $ingredient->fiber_percentage) }}"
                        class="w-full border-gray-300 rounded-lg px-3 py-2 shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>

                <!-- Unit -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Unit</label>
                    <input type="text" name="unit" value="{{ old('unit', $ingredient->unit) }}"
                        class="w-full border-gray-300 rounded-lg px-3 py-2 shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                </div>

                <!-- Cost -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Cost per Unit (GHS)</label>
                    <input type="number" step="0.01" min="0" name="cost_per_unit"
                        value="{{ old('cost_per_unit', $ingredient->cost_per_unit) }}"
                        class="w-full border-gray-300 rounded-lg px-3 py-2 shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                </div>

                <!-- Current On-hand and Quantity Purchased -->
                <div class="md:col-span-2">
                    <div class="mb-2 text-sm text-gray-600">
                        <span class="inline-block px-2 py-1 bg-gray-100 border border-gray-200 rounded-md">Current On-hand: {{ isset($onHand) ? number_format($onHand->quantity, 2) . ' ' . $onHand->unit : '0.00' }}</span>
                    </div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Quantity Purchased (adds to on-hand)</label>
                    <input type="number" step="0.01" min="0" name="initial_quantity"
                        value="{{ old('initial_quantity') }}"
                        placeholder="Enter quantity purchased"
                        class="w-full border-gray-300 rounded-lg px-3 py-2 shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <p class="text-xs text-gray-500 mt-1">Leave blank to keep on-hand unchanged.</p>
                </div>

                <!-- Buttons -->
                <div class="md:col-span-2 flex justify-end space-x-3">
                    <a href="{{ route('ingredients.index') }}"
                        class="px-4 py-2 border rounded-lg text-gray-700 hover:bg-gray-100">Cancel</a>
                    <button type="submit"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 shadow-sm">💾 Update</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
