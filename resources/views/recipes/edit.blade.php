<x-app-layout>
    <div class="container mx-auto py-2 max-w-5xl">
        <h1 class="text-2xl font-bold mb-6">Edit Feed Recipe</h1>
        <form method="POST" action="{{ route('recipes.update', $recipe) }}" class="bg-white p-6 rounded shadow border space-y-4">
            @csrf
            @method('PUT')
            <div>
                <label class="block font-medium mb-1">Feed Type</label>
                <select name="feed_type_id" class="w-full border rounded px-3 py-2" required>
                    @foreach($feedTypes as $ft)
                        <option value="{{ $ft->id }}" {{ $recipe->feed_type_id == $ft->id ? 'selected' : '' }}>{{ $ft->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block font-medium mb-1">Recipe Name (optional)</label>
                <input type="text" name="name" class="w-full border rounded px-3 py-2" value="{{ old('name', $recipe->name) }}">
            </div>
            <div id="items" class="space-y-2">
                @foreach($recipe->items as $idx => $it)
                <div class="grid grid-cols-12 gap-2">
                    <div class="col-span-8">
                        <select name="items[{{ $idx }}][ingredient_id]" class="w-full border rounded px-3 py-2" required>
                            @foreach($ingredients as $ing)
                                <option value="{{ $ing->id }}" {{ $it->ingredient_id == $ing->id ? 'selected' : '' }}>{{ $ing->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-span-3">
                        <input type="number" step="0.01" min="0" max="100" name="items[{{ $idx }}][percentage]" class="w-full border rounded px-3 py-2" value="{{ $it->percentage }}" required>
                    </div>
                    <div class="col-span-1 text-center pt-2">%</div>
                </div>
                @endforeach
            </div>
            <button type="button" id="add-item" class="px-3 py-1 bg-gray-200 rounded">Add Ingredient</button>
            <div>
                <button class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Save</button>
                <a href="{{ route('recipes.index') }}" class="ml-3 text-gray-600 hover:underline">Cancel</a>
            </div>
        </form>
    </div>
    <script>
        const container = document.getElementById('items');
        let index = {{ $recipe->items->count() }};
        document.getElementById('add-item').addEventListener('click', () => {
            const row = document.createElement('div');
            row.className = 'grid grid-cols-12 gap-2';
            row.innerHTML = `
                <div class="col-span-8">
                    <select name="items[${index}][ingredient_id]" class="w-full border rounded px-3 py-2" required>
                        ${`@foreach($ingredients as $ing)<option value="{{ $ing->id }}">{{ $ing->name }}</option>@endforeach`}
                    </select>
                </div>
                <div class="col-span-3">
                    <input type="number" step="0.01" min="0" max="100" name="items[${index}][percentage]" class="w-full border rounded px-3 py-2" placeholder="%" required>
                </div>
                <div class="col-span-1 text-center pt-2">%</div>
            `;
            container.appendChild(row);
            index++;
        });
    </script>
</x-app-layout>
