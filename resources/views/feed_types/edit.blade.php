<x-app-layout>
    <div class="max-w-lg mx-auto py-8 px-4">
        <h1 class="text-2xl font-bold mb-6">Edit Feed Type</h1>
        <form method="POST" action="{{ route('feed-types.update', $feedType) }}" class="bg-white p-6 rounded shadow">
            @csrf @method('PUT')
            <div class="mb-4">
                <label class="block font-medium mb-1">Name</label>
                <input type="text" name="name" class="w-full border rounded px-3 py-2" required value="{{ old('name', $feedType->name) }}">
            </div>
            <div class="mb-4">
                <label class="block font-medium mb-1">Description</label>
                <input type="text" name="description" class="w-full border rounded px-3 py-2" value="{{ old('description', $feedType->description) }}">
            </div>
            <div class="mb-4">
                <label class="block font-medium mb-1">Protein (%)</label>
                <input type="number" name="protein_percent" class="w-full border rounded px-3 py-2" step="0.01" min="0" max="100" value="{{ old('protein_percent', $feedType->protein_percent) }}">
            </div>
            <div class="mb-4">
                <label class="block font-medium mb-1">Energy (MJ/kg)</label>
                <input type="number" name="energy_mj_per_kg" class="w-full border rounded px-3 py-2" step="0.01" min="0" value="{{ old('energy_mj_per_kg', $feedType->energy_mj_per_kg) }}">
            </div>
            <div class="mb-4">
                <label class="block font-medium mb-1">Fiber (%)</label>
                <input type="number" name="fiber_percent" class="w-full border rounded px-3 py-2" step="0.01" min="0" max="100" value="{{ old('fiber_percent', $feedType->fiber_percent) }}">
            </div>
            <div class="mb-4">
                <label class="block font-medium mb-1">Cost per Unit (₵)</label>
                <input type="number" name="cost_per_unit" class="w-full border rounded px-3 py-2" step="0.01" min="0" value="{{ old('cost_per_unit', $feedType->cost_per_unit) }}">
            </div>
            <div class="mb-4">
                <label class="block font-medium mb-1">Unit</label>
                <input type="text" name="unit" class="w-full border rounded px-3 py-2" value="{{ old('unit', $feedType->unit) }}" required>
            </div>
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Update</button>
            <a href="{{ route('feed-types.index') }}" class="ml-4 text-gray-600 hover:underline">Cancel</a>
        </form>
    </div>
</x-app-layout>
