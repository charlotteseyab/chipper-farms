<x-app-layout>
    <div class="max-w-6xl mx-auto py-8 px-4">
        <h1 class="text-2xl font-bold mb-6">Feed Types</h1>
        <!-- @if(session('success'))
            <div class="mb-4 p-3 bg-green-100 text-green-800 rounded shadow">{{ session('success') }}</div>
        @endif -->
        <div class="mb-4">
            <a href="{{ route('feed-types.create') }}" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Add Feed Type</a>
        </div>
        <table class="min-w-full bg-white border rounded shadow text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-2">Name</th>
                    <th class="px-4 py-2">Description</th>
                    <th class="px-4 py-2">Protein (%)</th>
                    <th class="px-4 py-2">Energy (MJ/kg)</th>
                    <th class="px-4 py-2">Fiber (%)</th>
                    <th class="px-4 py-2">Cost/Unit</th>
                    <th class="px-4 py-2">Unit</th>
                    <th class="px-4 py-2">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($feedTypes as $type)
                <tr>
                    <td class="px-4 py-2 font-semibold">{{ $type->name }}</td>
                    <td class="px-4 py-2">{{ $type->description }}</td>
                    <td class="px-4 py-2">{{ $type->protein_percent }}</td>
                    <td class="px-4 py-2">{{ $type->energy_mj_per_kg }}</td>
                    <td class="px-4 py-2">{{ $type->fiber_percent }}</td>
                    <td class="px-4 py-2">₵{{ number_format($type->cost_per_unit, 2) }}</td>
                    <td class="px-4 py-2">{{ $type->unit }}</td>
                    <td class="px-4 py-2">
                        <a href="{{ route('feed-types.edit', $type) }}" class="text-blue-600 hover:underline">Edit</a>
                        <form action="{{ route('feed-types.destroy', $type) }}" method="POST" class="inline">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-red-600 hover:underline ml-2" onclick="return confirm('Delete this feed type?')">Delete</button>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-app-layout>
