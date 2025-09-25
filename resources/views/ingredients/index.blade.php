<x-app-layout>
    <div class="container mx-auto py-6 max-w-6xl">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold">Ingredients</h1>
            <a href="{{ route('ingredients.create') }}" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">New Ingredient</a>
        </div>
        <div class="bg-white rounded-xl shadow p-6 border">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="text-left p-2">Name</th>
                        <th class="text-left p-2">Protein %</th>
                        <th class="text-left p-2">Energy (kcal/kg)</th>
                        <th class="text-left p-2">Cost/unit (GHS)</th>
                        <th class="text-left p-2">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($ingredients as $ing)
                    <tr class="border-t">
                        <td class="p-2 font-medium">{{ $ing->name }}</td>
                        <td class="p-2">{{ $ing->protein_percentage ?? '-' }}</td>
                        <td class="p-2">{{ $ing->energy_kcal_per_kg ?? '-' }}</td>
                        <td class="p-2">₵{{ number_format($ing->cost_per_unit, 2) }}</td>
                        <td class="p-2 space-x-2">
                            <a href="{{ route('ingredients.edit', $ing) }}" class="text-blue-600 hover:underline">Edit</a>
                            <form method="POST" action="{{ route('ingredients.destroy', $ing) }}" class="inline" onsubmit="return confirm('Delete this ingredient?')">
                                @csrf
                                @method('DELETE')
                                <button class="text-red-600 hover:underline">Delete</button>
                            </form>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="mt-4">{{ $ingredients->links('pagination::tailwind') }}</div>
        </div>
    </div>
</x-app-layout>
