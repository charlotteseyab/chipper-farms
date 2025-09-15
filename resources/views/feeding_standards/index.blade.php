<x-app-layout>
    <div class="max-w-6xl mx-auto py-8 px-4">
        <!-- <h1 class="text-2xl font-bold mb-6">Feeding Standards</h1> -->
        <!-- @if(session('success'))
            <div class="mb-4 p-3 bg-green-100 text-green-800 rounded shadow">{{ session('success') }}</div>
        @endif -->
        <div class="mb-4">
            <a href="{{ route('feeding-standards.create') }}" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Add Feeding Standard</a>
        </div>
        <table class="min-w-full bg-white border rounded shadow text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-2">Pig Category</th>
                    <th class="px-4 py-2">Feed Type</th>
                    <th class="px-4 py-2">Daily Amount</th>
                    <th class="px-4 py-2">Unit</th>
                    <th class="px-4 py-2">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($standards as $std)
                <tr>
                    <td class="px-4 py-2 font-semibold">{{ $std->pig_category }}</td>
                    <td class="px-4 py-2">{{ $std->feedType->name ?? '-' }}</td>
                    <td class="px-4 py-2">{{ $std->daily_amount }}</td>
                    <td class="px-4 py-2">{{ $std->unit }}</td>
                    <td class="px-4 py-2">
                        <a href="{{ route('feeding-standards.edit', $std) }}" class="text-blue-600 hover:underline">Edit</a>
                        <form action="{{ route('feeding-standards.destroy', $std) }}" method="POST" class="inline">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-red-600 hover:underline ml-2" onclick="return confirm('Delete this standard?')">Delete</button>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-app-layout>
