<x-app-layout>
<div class="container mx-auto py-2 max-w-5xl">
<div class="bg-white rounded-xl shadow-xl border border-gray-200">
<div class="px-6 py-4 border-b border-gray-100">
<h1 class="text-2xl font-extrabold text-gray-800">New Feed Material</h1>
<p class="text-sm text-gray-500">Define the nutritional profile and default inventory settings for this material.</p>
</div>

        <form method="POST" action="{{ route('ingredients.store') }}" class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
            @csrf

            <!-- Name -->
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                <input type="text" name="name" value="{{ old('name') }}" 
                    class="w-full border-gray-300 rounded-lg px-3 py-2 shadow-sm focus:ring-blue-500 focus:border-blue-500 transition duration-150" required>
            </div>

            <!-- Description -->
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                <textarea name="description" rows="3" 
                    class="w-full border-gray-300 rounded-lg px-3 py-2 shadow-sm focus:ring-blue-500 focus:border-blue-500 transition duration-150">{{ old('description') }}</textarea>
            </div>
            
            <hr class="md:col-span-2 border-t border-gray-200 mt-2 mb-1">
            <h2 class="md:col-span-2 text-lg font-semibold text-gray-700">Nutritional Profile</h2>

            <!-- Protein -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Protein (%)</label>
                <input type="number" step="0.01" min="0" max="100" name="protein_percentage" value="{{ old('protein_percentage') }}"
                    class="w-full border-gray-300 rounded-lg px-3 py-2 shadow-sm focus:ring-blue-500 focus:border-blue-500 transition duration-150">
            </div>

            <!-- Energy -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Energy (kcal/kg)</label>
                <input type="number" step="0.01" min="0" name="energy_kcal_per_kg" value="{{ old('energy_kcal_per_kg') }}"
                    class="w-full border-gray-300 rounded-lg px-3 py-2 shadow-sm focus:ring-blue-500 focus:border-blue-500 transition duration-150">
            </div>

            <!-- Fat -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Fat (%)</label>
                <input type="number" step="0.01" min="0" max="100" name="fat_percentage" value="{{ old('fat_percentage') }}"
                    class="w-full border-gray-300 rounded-lg px-3 py-2 shadow-sm focus:ring-blue-500 focus:border-blue-500 transition duration-150">
            </div>

            <!-- Fiber -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Fiber (%)</label>
                <input type="number" step="0.01" min="0" max="100" name="fiber_percentage" value="{{ old('fiber_percentage') }}"
                    class="w-full border-gray-300 rounded-lg px-3 py-2 shadow-sm focus:ring-blue-500 focus:border-blue-500 transition duration-150">
            </div>
            
            <hr class="md:col-span-2 border-t border-gray-200 mt-2 mb-1">
            <h2 class="md:col-span-2 text-lg font-semibold text-gray-700">Inventory Setup</h2>


            <!-- Unit -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Stock Unit (e.g., kg, bag)</label>
                <input type="text" name="unit" value="{{ old('unit','kg') }}" 
                    class="w-full border-gray-300 rounded-lg px-3 py-2 shadow-sm focus:ring-blue-500 focus:border-blue-500 transition duration-150" required>
                <p class="mt-1 text-xs text-gray-500">The unit this item is counted in for inventory.</p>
            </div>

            <!-- Default Bag Weight (NEW FIELD) -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Default Bag Weight (kg)</label>
                <input type="number" step="0.01" min="0.01" name="bag_weight_kg" value="{{ old('bag_weight_kg') }}"
                    class="w-full border-gray-300 rounded-lg px-3 py-2 shadow-sm focus:ring-blue-500 focus:border-blue-500 transition duration-150">
                <p class="mt-1 text-xs text-gray-500">Required if the Stock Unit is 'bag'. This is the standard weight used for converting KG usage to BAG deduction.</p>
            </div>

            <!-- Quantity -->
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Quantity Purchased (Initial Stock)</label>
                <input type="number" step="0.01" min="0" name="initial_quantity" value="{{ old('initial_quantity') }}"
                    class="w-full border-gray-300 rounded-lg px-3 py-2 shadow-sm focus:ring-blue-500 focus:border-blue-500 transition duration-150">
                <p class="mt-1 text-xs text-gray-500">Enter the amount in the **Stock Unit** (e.g., 100 kg or 4 bags) to start inventory.</p>
            </div>

            <!-- Buttons -->
            <div class="md:col-span-2 flex justify-end space-x-3 mt-4">
                <a href="{{ route('ingredients.index') }}" 
                    class="px-5 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-100 transition duration-150 shadow-sm">Cancel</a>
                <button type="submit" 
                    class="px-5 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 shadow-md transition duration-150 flex items-center">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-3M16 5l3 3m0 0l-3 3M19 8h-4m-7 8H8m-2 4h4m-4-8v4"></path></svg>
                    Save Ingredient
                </button>
            </div>
        </form>
    </div>
</div>

</x-app-layout>