<x-app-layout>
    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="flex items-center justify-between mb-4">
                <h1 class="text-xl font-semibold text-gray-900">Structures</h1>
                <a href="{{ route('structures.create') }}" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">New Structure</a>
            </div>
            <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Capacity</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($structures as $structure)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <div>
                                            <div class="font-medium">{{ $structure->name }}</div>
                                            @if($structure->code)
                                                <div class="text-xs text-gray-500">Code: {{ $structure->code }}</div>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        <div class="max-w-xs truncate" title="{{ $structure->description }}">
                                            {{ $structure->description ?? 'N/A' }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        @if($structure->capacity)
                                            {{ $structure->current_room_count }}/{{ $structure->capacity }}
                                            @if($structure->is_full)
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 ml-2">Full</span>
                                            @endif
                                        @else
                                            {{ $structure->current_room_count }} (Unlimited)
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="text-sm font-medium text-gray-900">
                                         {{ ucfirst($structure->status) }}
                                        </span>
                                    </td>

                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <a href="{{ route('structures.show', $structure) }}" class="text-blue-600 hover:text-blue-900">View</a>
                                        <form action="{{ route('structures.destroy', $structure) }}" method="POST" onsubmit="return confirm('Delete this structure?');" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:text-red-900">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-12 text-center text-gray-500">No structures found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($structures->hasPages())
                    <div class="px-6 py-4">
                        <x-simple-pagination :paginator="$structures" />
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>


