<x-app-layout>
    <div class="py-6">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-2xl font-semibold text-gray-900">{{ $structure->name }}</h1>
                        <div class="flex items-center gap-4 mt-2">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                @if($structure->status === 'active') bg-green-100 text-green-800
                                @elseif($structure->status === 'inactive') bg-red-100 text-red-800
                                @else bg-yellow-100 text-yellow-800 @endif">
                                {{ $structure->status === 'under_maintenance' ? 'Under Maintenance' : ucfirst($structure->status) }}
                            </span>
                            <span class="text-sm text-gray-600">
                                @if($structure->capacity)
                                    Rooms: {{ $structure->current_room_count }}/{{ $structure->capacity }}
                                    @if($structure->is_full)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 ml-2">Full</span>
                                    @endif
                                @else
                                    Rooms: {{ $structure->current_room_count }} (Unlimited capacity)
                                @endif
                            </span>
                        </div>
                    </div>
                    <div class="space-x-2">
                        <a href="{{ route('structures.edit', $structure) }}" class="px-3 py-2 bg-blue-600 text-white rounded-lg text-sm">Edit</a>
                        <form action="{{ route('structures.destroy', $structure) }}" method="POST" class="inline" onsubmit="return confirm('Delete this structure?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="px-3 py-2 bg-red-600 text-white rounded-lg text-sm">Delete</button>
                        </form>
                    </div>
                </div>
                @if($structure->description)
                    <div class="mt-4 text-sm text-gray-700">{{ $structure->description }}</div>
                @endif
            </div>

            <!-- Rooms -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-gray-900">Rooms</h2>
                </div>
                <div class="p-6">
                    <form method="POST" action="{{ route('structures.rooms.store', $structure) }}" class="grid grid-cols-1 md:grid-cols-5 gap-3 mb-6">
                        @csrf
                        <input type="text" name="name" placeholder="Room name" class="border-gray-300 rounded-lg" required>
                        <input type="text" name="code" placeholder="Code" class="border-gray-300 rounded-lg" required>
                        <input type="number" name="capacity" min="0" placeholder="Capacity" class="border-gray-300 rounded-lg">
                        <select name="type" class="border-gray-300 rounded-lg">
                            <option value="general">General</option>
                            <option value="maternity">Maternity</option>
                            <option value="gestation">Gestation</option>
                            <option value="weaning">Weaning</option>
                            <option value="finisher">Finisher</option>
                            <option value="quarantine">Quarantine</option>
                        </select>
                        <button type="submit" class="px-3 py-2 bg-green-600 text-white rounded-lg">Add Room</button>
                        <div class="md:col-span-5">
                            <textarea name="notes" rows="2" placeholder="Notes (optional)" class="w-full border-gray-300 rounded-lg"></textarea>
                        </div>
                    </form>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Code</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Capacity</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Notes</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse($structure->rooms as $room)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $room->name }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $room->code }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ ucfirst($room->type) }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $room->capacity ?? '-' }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ $room->notes ?? '-' }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <div class="flex items-center space-x-2">
                                                <a href="{{ route('rooms.show', $room) }}" class="text-blue-600 hover:text-blue-900">View</a>
                                                    <form action="{{ route('structures.rooms.destroy', [$structure, $room]) }}" method="POST" onsubmit="return confirm('Delete this room?');" class="inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="text-red-600 hover:text-red-900">Delete</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-6 py-12 text-center text-gray-500">No rooms yet. Use the form above to add rooms.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>


