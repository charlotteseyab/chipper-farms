<x-app-layout>
    <div class="max-w-7xl mx-auto py-8 px-4">
        <!-- Header -->
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-gray-800">Feed Types</h1>
            <a href="{{ route('feed-types.create') }}" 
               class="bg-blue-600 text-white px-4 py-2 rounded-lg shadow hover:bg-blue-700 transition">
               ➕ Add Feed Type
            </a>
        </div>

        <!-- Table -->
        <div class="overflow-x-auto bg-white rounded-lg shadow-lg border border-gray-200">
            <table class="min-w-full text-sm border-collapse">
                <thead class="bg-gray-100 text-gray-700 text-xs uppercase tracking-wider sticky top-0">
                    <tr>
                        <th class="px-4 py-3 text-left w-1/6">Name</th>
                        <th class="px-4 py-3 text-left w-1/6">Description</th>
                        <th class="px-4 py-3 text-left w-1/12">Protein(%)</th>
                        <th class="px-4 py-3 text-left w-1/12">Energy(MJ/kg)</th>
                        <th class="px-4 py-3 text-left w-1/12">Fiber(%)</th>
                        <th class="px-4 py-3 text-left w-1/6">Ingredients</th>
                        <th class="px-4 py-3 text-left w-1/12">Unit</th>
                        <th class="px-4 py-3 text-left w-1/6">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach($feedTypes as $type)
                    <tr class="hover:bg-blue-50 transition">
                        <!-- Name -->
                        <td class="px-4 py-3 font-semibold text-gray-900">{{ $type->name }}</td>

                        <!-- Description -->
                        <td class="px-4 py-3 text-gray-600">{{ $type->description ?: 'N/A' }}</td>

                        <!-- Nutrients -->
                        <td class="px-4 py-3 text-gray-700">{{ $type->protein_percent }}</td>
                        <td class="px-4 py-3 text-gray-700">{{ $type->energy_mj_per_kg }}</td>
                        <td class="px-4 py-3 text-gray-700">{{ $type->fiber_percent }}</td>

                        <!-- Ingredients -->
                        <td class="px-4 py-3 text-gray-700">
                            @php($recipe = optional($type->recipe)->items ?? collect())
                            @if($recipe->count() > 0)
                                <ul class="space-y-1">
                                    @foreach($recipe as $it)
                                        <li class="flex items-center gap-2 text-sm">
                                            <span class="font-medium text-gray-800">{{ optional($it->ingredient)->name ?? 'Item' }}</span>
                                            <span class="text-gray-500">· {{ number_format($it->percentage, 2) }} kg</span>
                                        </li>
                                    @endforeach
                                </ul>
                            @else
                                <span class="text-gray-400">N/A</span>
                            @endif
                        </td>

                        <!-- Unit -->
                        <td class="px-4 py-3 text-gray-700">{{ $type->unit }}</td>

                        <!-- Actions -->
                        <td class="px-4 py-3 flex items-center gap-2">
                            <a href="{{ route('feed-types.edit', $type) }}" 
                               class="px-3 py-1 text-xs font-medium text-blue-700 bg-blue-100 rounded-full hover:bg-blue-200 transition">
                                Edit
                            </a>
                            <form action="{{ route('feed-types.destroy', $type) }}" method="POST" onsubmit="return confirm('Delete this feed type?')">
                                @csrf @method('DELETE')
                                <button type="submit" 
                                        class="px-3 py-1 text-xs font-medium text-red-700 bg-red-100 rounded-full hover:bg-red-200 transition">
                                    Delete
                                </button>
                            </form>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
