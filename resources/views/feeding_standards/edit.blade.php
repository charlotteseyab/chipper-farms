<x-app-layout>
    <div class="max-w-5xl mx-auto py-8 px-4">
        <h1 class="text-2xl font-bold mb-6">Edit Feeding Standard</h1>
        <form method="POST" action="{{ route('feeding-standards.update', $feedingStandard) }}" class="bg-white p-6 rounded shadow">
            @csrf @method('PUT')
            <div class="mb-4">
                <label class="block font-medium mb-1">Pig Category</label>
                <input type="text" name="pig_category" class="w-full border rounded px-3 py-2" required value="{{ old('pig_category', $feedingStandard->pig_category) }}">
            </div>
            <div class="mb-4">
                <label class="block font-medium mb-1">Feed Type</label>
                <select name="feed_type_id" class="w-full border rounded px-3 py-2" required>
                    <option value="">Select Feed Type</option>
                    @foreach($feedTypes as $type)
                        <option value="{{ $type->id }}" @if(old('feed_type_id', $feedingStandard->feed_type_id) == $type->id) selected @endif>{{ $type->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="mb-4">
                <label class="block font-medium mb-1">Daily Amount</label>
                <input type="number" name="daily_amount" class="w-full border rounded px-3 py-2" step="0.01" min="0.01" required value="{{ old('daily_amount', $feedingStandard->daily_amount) }}">
            </div>
            <div class="mb-4">
                <label class="block font-medium mb-1">Unit</label>
                <input type="text" name="unit" class="w-full border rounded px-3 py-2" value="{{ old('unit', $feedingStandard->unit) }}" required>
            </div>
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Update</button>
            <a href="{{ route('feeding-standards.index') }}" class="ml-4 text-gray-600 hover:underline">Cancel</a>
        </form>
    </div>
</x-app-layout>
