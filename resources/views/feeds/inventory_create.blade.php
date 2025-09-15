<x-app-layout>
<div class="container mx-auto py-6 max-w-5xl">
    <!-- <h1 class="text-2xl font-bold mb-4">Add Feed Stock</h1> -->
    <form method="POST" action="{{ route('feed-inventory.store') }}" class="bg-white p-6 rounded shadow">
        @csrf
        <div class="mb-4">
            <label class="block font-medium mb-1">Feed Type</label>
            <input type="text" name="feed_type" class="w-full border rounded px-3 py-2" required value="{{ old('feed_type') }}">
        </div>
        <div class="mb-4">
            <label class="block font-medium mb-1">Quantity</label>
            <input type="number" name="quantity" class="w-full border rounded px-3 py-2" step="0.01" min="0.1" required value="{{ old('quantity') }}">
        </div>
        <div class="mb-4">
            <label class="block font-medium mb-1">Unit</label>
            <input type="text" name="unit" class="w-full border rounded px-3 py-2" value="{{ old('unit', 'kg') }}" required>
        </div>
        <div class="mb-4">
            <label class="block font-medium mb-1">Last Updated</label>
            <input type="date" name="last_updated" class="w-full border rounded px-3 py-2" value="{{ old('last_updated', now()->toDateString()) }}">
        </div>
        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Save</button>
        <a href="{{ route('feed-inventory.index') }}" class="ml-4 text-gray-600 hover:underline">Cancel</a>
    </form>
</div>
</x-app-layout>
