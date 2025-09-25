<x-app-layout>
    <div class="max-w-6xl mx-auto py-2 px-4">
        <h1 class="text-2xl font-bold mb-6">Add Feed Type</h1>
        <form method="POST" action="{{ route('feed-types.store') }}" class="bg-white p-6 rounded shadow">
            @csrf
            <div class="mb-4">
                <label class="block font-medium mb-1">Name</label>
                <input type="text" name="name" class="w-full border rounded px-3 py-2" required value="{{ old('name') }}">
            </div>
            <div class="mb-4">
                <label class="block font-medium mb-1">Description</label>
                <input type="text" name="description" class="w-full border rounded px-3 py-2" value="{{ old('description') }}">
            </div>
            <div class="mb-4">
                <label class="block font-medium mb-1">Protein (%)</label>
                <input type="number" name="protein_percent" class="w-full border rounded px-3 py-2" step="0.01" min="0" max="100" value="{{ old('protein_percent') }}">
            </div>
            <div class="mb-4">
                <label class="block font-medium mb-1">Energy (MJ/kg)</label>
                <input type="number" name="energy_mj_per_kg" class="w-full border rounded px-3 py-2" step="0.01" min="0" value="{{ old('energy_mj_per_kg') }}">
            </div>
            <div class="mb-4">
                <label class="block font-medium mb-1">Fiber (%)</label>
                <input type="number" name="fiber_percent" class="w-full border rounded px-3 py-2" step="0.01" min="0" max="100" value="{{ old('fiber_percent') }}">
            </div>
            <div class="mb-4">
                <label class="block font-medium mb-1">Cost per Unit (₵)</label>
                <input type="number" name="cost_per_unit" class="w-full border rounded px-3 py-2" step="0.01" min="0" value="{{ old('cost_per_unit') }}">
            </div>
            <div class="mb-4">
                <label class="block font-medium mb-1">Unit</label>
                <input type="text" name="unit" class="w-full border rounded px-3 py-2" value="{{ old('unit', 'kg') }}" required>
            </div>

            <div class="mb-4 border-t pt-4">
                <h2 class="text-lg font-semibold mb-2">Recipe (optional)</h2>
                <div id="items" class="space-y-2">
                    <div class="grid grid-cols-12 gap-2">
                        <div class="col-span-8">
                            <select name="items[0][ingredient_id]" class="w-full border rounded px-3 py-2">
                                <option value="">-- Select ingredient --</option>
                                @foreach($ingredients as $ing)
                                    <option value="{{ $ing->id }}">{{ $ing->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-span-3">
                            <input type="number" step="0.01" min="0" name="items[0][quantity]" class="w-full border rounded px-3 py-2" placeholder="Qty (kg)">
                        </div>
                        <div class="col-span-1 text-center pt-2">kg</div>
                    </div>
                </div>
                <button type="button" id="add-item" class="px-3 py-1 bg-gray-200 rounded mt-2">Add Ingredient</button>

                <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block font-medium mb-1">Produce Batch (kg)</label>
                        <input type="number" step="0.01" min="0" name="batch_size" class="w-full border rounded px-3 py-2" placeholder="e.g., 100">
                        <p class="text-xs text-gray-500 mt-1">Deducts ingredient on-hand and increases feed stock by this amount.</p>
                    </div>
                </div>
            </div>

            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Save</button>
            <a href="{{ route('feed-types.index') }}" class="ml-4 text-gray-600 hover:underline">Cancel</a>
        </form>
    </div>
    <script>
        const container = document.getElementById('items');
        let index = 1;
        document.getElementById('add-item').addEventListener('click', () => {
            const row = document.createElement('div');
            row.className = 'grid grid-cols-12 gap-2';
            row.innerHTML = `
                <div class="col-span-8">
                    <select name="items[${index}][ingredient_id]" class="w-full border rounded px-3 py-2">
                        <option value="">-- Select ingredient --</option>
                        ${`@foreach($ingredients as $ing)<option value=\"{{ $ing->id }}\">{{ $ing->name }}</option>@endforeach`}
                    </select>
                </div>
                <div class="col-span-3">
                    <input type="number" step="0.01" min="0" name="items[${index}][quantity]" class="w-full border rounded px-3 py-2" placeholder="Qty (kg)">
                </div>
                <div class="col-span-1 text-center pt-2">kg</div>
            `;
            container.appendChild(row);
            index++;
        });
    </script>
</x-app-layout>
