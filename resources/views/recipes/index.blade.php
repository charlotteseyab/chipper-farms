<x-app-layout>
    <div class="container mx-auto py-6 max-w-6xl">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold">Feed Recipes</h1>
            <a href="{{ route('recipes.create') }}" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">New Recipe</a>
        </div>
        <div class="bg-white rounded-xl shadow p-6 border">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="text-left p-2">Feed Type</th>
                        <th class="text-left p-2">Recipe</th>
                        <th class="text-left p-2">Computed Cost/kg</th>
                        <th class="text-left p-2">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($recipes as $rec)
                    <tr class="border-t">
                        <td class="p-2 font-medium">{{ $rec->feedType->name }}</td>
                        <td class="p-2">
                            @foreach($rec->items as $it)
                                <div>{{ $it->ingredient->name }} — {{ $it->percentage }}%</div>
                            @endforeach
                        </td>
                        <td class="p-2">₵{{ number_format($rec->feedType->computed_cost_per_kg ?? 0, 2) }}</td>
                        <td class="p-2 space-x-2">
                            <a href="{{ route('recipes.edit', $rec) }}" class="text-blue-600 hover:underline">Edit</a>
                            <form method="POST" action="{{ route('recipes.destroy', $rec) }}" class="inline" onsubmit="return confirm('Delete this recipe?')">
                                @csrf
                                @method('DELETE')
                                <button class="text-red-600 hover:underline">Delete</button>
                            </form>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="mt-4">{{ $recipes->links('pagination::tailwind') }}</div>
        </div>
    </div>
</x-app-layout>
