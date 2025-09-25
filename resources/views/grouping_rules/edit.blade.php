<x-app-layout>
    <div class="container mx-auto py-6 max-w-3xl">
        <h1 class="text-2xl font-bold mb-6">Edit Grouping Rule</h1>
        <form method="POST" action="{{ route('grouping-rules.update', $rule) }}" class="bg-white p-6 rounded shadow border space-y-4">
            @csrf
            @method('PUT')
            <div>
                <label class="block font-medium mb-1">Name</label>
                <input type="text" name="name" class="w-full border rounded px-3 py-2" required value="{{ old('name', $rule->name) }}">
            </div>
            <div>
                <label class="block font-medium mb-1">Conditions (plain text)</label>
                <textarea name="conditions_text" class="w-full border rounded px-3 py-2 h-40" required>{{ old('conditions_text', $rule->conditions_text) }}</textarea>
                <p class="text-xs text-gray-500 mt-1">Describe the grouping in plain text. We'll use this as a label/description.</p>
            </div>
            <div>
                <label class="block font-medium mb-1">Feeding Standard (optional)</label>
                <select name="feeding_standard_id" class="w-full border rounded px-3 py-2">
                    <option value="">None</option>
                    @foreach($standards as $std)
                        <option value="{{ $std->id }}" {{ old('feeding_standard_id', $rule->feeding_standard_id) == $std->id ? 'selected' : '' }}>{{ $std->pig_category }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-center space-x-2">
                <input type="checkbox" name="active" value="1" id="active" {{ old('active', $rule->active) ? 'checked' : '' }}>
                <label for="active">Active</label>
            </div>
            <div class="pt-2">
                <button class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Save</button>
                <a href="{{ route('grouping-rules.index') }}" class="ml-3 text-gray-600 hover:underline">Cancel</a>
            </div>
        </form>
    </div>
</x-app-layout>
