<x-app-layout>
        <main class="flex-1 p-4 sm:p-8 bg-gray-100 min-h-screen">
            <div class="max-w-7xl mx-auto space-y-8">
                
                <!-- Structure Header and Actions -->
                <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-6">
                    <div class="flex flex-col md:flex-row items-start md:items-center justify-between">
                        
                        <!-- Title and Status -->
                        <div class="flex-1 min-w-0">
                            <h1 class="text-3xl font-extrabold text-gray-900 truncate">{{ $structure->name }}</h1>
                            <p class="text-md text-gray-600 mt-1 mb-3">{{ $structure->description ?? 'No description provided.' }}</p>

                            <div class="flex items-center gap-4">
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold
                                    @if($structure->status === 'active') bg-green-100 text-green-800
                                    @elseif($structure->status === 'inactive') bg-red-100 text-red-800
                                    @else bg-yellow-100 text-yellow-800 @endif">
                                    {{ $structure->status === 'under_maintenance' ? 'Under Maintenance' : ucfirst($structure->status) }}
                                </span>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="mt-4 md:mt-0 flex flex-shrink-0 space-x-3">
                            <a href="{{ route('structures.edit', $structure) }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-xl text-sm font-medium hover:bg-indigo-700 transition shadow-md">
                                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-7-3l-4 4m0 0h3m-3 0v3m9-9a2 2 0 012 2v1a2 2 0 01-2 2h-1a2 2 0 01-2-2V9a2 2 0 012-2h1z"/></svg>
                                Edit Structure
                            </a>
                            <form action="{{ route('structures.destroy', $structure) }}" method="POST" class="inline" onsubmit="return confirm('Are you sure you want to permanently delete this structure and all associated rooms?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="inline-flex items-center px-4 py-2 bg-red-600 text-white rounded-xl text-sm font-medium hover:bg-red-700 transition shadow-md">
                                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    Delete
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                @php
                    $weaningAge = config('breeding.cycle.birth_to_weaning_days', 45);
                    $totalWeaned = $structure->rooms->sum(function($room) { return $room->pigs->count(); });
                    $pigletsCount = 0;
                    foreach ($structure->rooms as $room) {
                        foreach ($room->pigs as $sow) {
                            foreach ($sow->farrowingsAsSow as $farrowing) {
                                if ($farrowing->farrowing_date && $farrowing->farrowing_date >= now()->subDays($weaningAge) && $farrowing->status === 'active') {
                                    $pigletsCount += max(0, (int) ($farrowing->net_live_piglets ?? 0));
                                }
                            }
                        }
                    }
                @endphp

                <!-- Key Metrics Overview -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                    
                    <!-- Metric Card 1: Total Pigs -->
                    <div class="bg-white rounded-xl shadow-md p-5 border border-gray-200">
                        <p class="text-sm font-medium text-gray-500">Total Pigs</p>
                        <div class="text-4xl font-extrabold text-blue-700 mt-1">{{ $structure->total_pigs }}</div>
                        <p class="text-xs text-gray-500 mt-2">({{ $totalWeaned }} Weaned + {{ $pigletsCount }} Piglets)</p>
                    </div>

                    <!-- Metric Card 2: Room Count & Capacity -->
                    <div class="bg-white rounded-xl shadow-md p-5 border border-gray-200">
                        <p class="text-sm font-medium text-gray-500">Total Rooms</p>
                        <div class="text-4xl font-extrabold text-purple-700 mt-1">{{ $structure->current_room_count }}</div>
                        @if($structure->capacity)
                            <p class="text-xs text-gray-500 mt-2">Capacity: {{ $structure->current_room_count }}/{{ $structure->capacity }} 
                                @if($structure->is_full)
                                    <span class="text-red-500 font-semibold">(Full)</span>
                                @endif
                            </p>
                        @else
                            <p class="text-xs text-gray-500 mt-2">Unlimited Room Capacity</p>
                        @endif
                    </div>

                    <!-- Metric Card 3: Pregnant Sows (Cross-Room) -->
                    @php
                        $allFemaleIds = $structure->rooms->flatMap(fn($room) => $room->pigs)->where('gender', 'F')->pluck('id');
                        $totalPregnantCount = $allFemaleIds->isNotEmpty()
                            ? \App\Models\Mating::whereIn('female_id', $allFemaleIds)
                                ->where('pregnancy_status','pregnant')
                                ->distinct('female_id')
                                ->count('female_id')
                            : 0;
                    @endphp
                    <div class="bg-white rounded-xl shadow-md p-5 border border-gray-200">
                        <p class="text-sm font-medium text-gray-500">Total Pregnant Sows</p>
                        <div class="text-4xl font-extrabold text-yellow-700 mt-1">{{ $totalPregnantCount }}</div>
                        <p class="text-xs text-gray-500 mt-2">Across all {{ $structure->current_room_count }} rooms.</p>
                    </div>

                    <!-- Metric Card 4: Action / Placeholder -->
                    <!-- <div class="bg-white rounded-xl shadow-md p-5 border border-gray-200 flex flex-col justify-center">
                        <p class="text-sm font-medium text-gray-500">Quick Access</p>
                        <a href="{{ route('all-stocks.create') }}" class="mt-2 text-md font-semibold text-indigo-600 hover:text-indigo-800 transition">
                            + Register New Pig
                        </a>
                        <a href="#" class="mt-1 text-md font-semibold text-indigo-600 hover:text-indigo-800 transition">
                            Manage Records
                        </a>
                    </div> -->
                </div>

                <!-- Room Management Section -->
                <div class="bg-white rounded-xl shadow-lg border border-gray-200">
                    <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between bg-gray-50 rounded-t-xl">
                        <h2 class="text-xl font-bold text-gray-900">Room Inventory</h2>
                        <span class="text-sm font-medium text-gray-600">
                            {{ $structure->current_room_count }} Rooms
                        </span>
                    </div>
                    
                    <!-- Add Room Form (Improved Layout) -->
                    <div class="p-6 border-b border-gray-100">
                        <h3 class="text-lg font-semibold text-indigo-700 mb-3">Add New Room</h3>
                        <form method="POST" action="{{ route('structures.rooms.store', $structure) }}" class="grid grid-cols-1 sm:grid-cols-6 lg:grid-cols-9 gap-4 items-end">
                            @csrf
                            <div class="sm:col-span-2">
                                <label for="name" class="block text-xs font-medium text-gray-700">Name</label>
                                <input type="text" id="name" name="name" placeholder="Farrowing Bay A" class="mt-1 w-full border-gray-300 rounded-xl shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm" required>
                            </div>
                            <div>
                                <label for="code" class="block text-xs font-medium text-gray-700">Code</label>
                                <input type="text" id="code" name="code" placeholder="RMA-01" class="mt-1 w-full border-gray-300 rounded-xl shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm" required>
                            </div>
                            <div>
                                <label for="capacity" class="block text-xs font-medium text-gray-700">Capacity</label>
                                <input type="number" id="capacity" name="capacity" min="0" placeholder="Max Animals" class="mt-1 w-full border-gray-300 rounded-xl shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                            </div>
                            <div class="sm:col-span-2">
                                <label for="type" class="block text-xs font-medium text-gray-700">Type</label>
                                <select id="type" name="type" class="mt-1 w-full border-gray-300 rounded-xl shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                                    <option value="" disabled selected>-- Select --</option>
                                    <option value="general">Breeding</option>
                                    <option value="maternity">Maternity</option>
                                    <option value="gestation">Gestation</option>
                                    <option value="weaning">Weaning</option>
                                    <option value="finisher">Finisher</option>
                                    <option value="quarantine">Quarantine</option>
                                </select>
                            </div>
                            <div class="lg:col-span-3 sm:col-span-5">
                                <label for="notes" class="block text-xs font-medium text-gray-700">Notes (Optional)</label>
                                <textarea id="notes" name="notes" rows="1" placeholder="Special requirements or status" class="mt-1 w-full border-gray-300 rounded-xl shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm"></textarea>
                            </div>
                            <div class="sm:col-span-1 lg:col-span-1">
                                <button type="submit" class="w-full h-10 px-4 py-2 bg-green-600 text-white rounded-xl text-sm font-medium hover:bg-green-700 transition shadow-md">
                                    Add
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Rooms Table -->
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Name / Code</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Type</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Pigs Count</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Capacity</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Pregnant Sows</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Notes</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-100">
                                @forelse($structure->rooms as $room)
                                    <tr class="hover:bg-indigo-50 transition">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <a href="{{ route('rooms.show', $room) }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">{{ $room->name }}</a>
                                            <p class="text-xs text-gray-500">{{ $room->code }}</p>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                                {{ ucfirst($room->type) }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $room->pigs->count() }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                            @if($room->capacity)
                                                <div class="flex items-center">
                                                    <div class="w-16 h-2 bg-gray-200 rounded-full mr-2">
                                                        <div class="h-2 rounded-full 
                                                            @if($room->pigs->count() >= $room->capacity) bg-red-600 @else bg-green-600 @endif" 
                                                            style="width: {{ min(($room->pigs->count() / $room->capacity) * 100, 100) }}%;">
                                                        </div>
                                                    </div>
                                                    <span class="text-xs">{{ $room->pigs->count() }} / {{ $room->capacity }}</span>
                                                </div>
                                                @if($room->pigs->count() >= $room->capacity)
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 mt-1">Full</span>
                                                @endif
                                            @else
                                                <span class="text-gray-400">Unlimited</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            @php
                                                $femaleIds = $room->pigs->where('gender', 'F')->pluck('id');
                                                $pregnantCount = $femaleIds->isNotEmpty()
                                                    ? \App\Models\Mating::whereIn('female_id', $femaleIds)
                                                        ->where('pregnancy_status','pregnant')
                                                        ->distinct('female_id')
                                                        ->count('female_id')
                                                    : 0;
                                            @endphp
                                            @if($pregnantCount > 0)
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">{{ $pregnantCount }}</span>
                                            @else
                                                <span class="text-gray-400">0</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 max-w-xs truncate">{{ $room->notes ?? 'N/A' }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <div class="flex items-center space-x-3">
                                                <a href="{{ route('rooms.show', $room) }}" class="text-indigo-600 hover:text-indigo-900 font-semibold">View</a>
                                                <a href="{{ route('rooms.edit', $room) }}" class="text-blue-600 hover:text-blue-900 font-semibold">Edit</a>
                                                <form action="{{ route('structures.rooms.destroy', [$structure, $room]) }}" method="POST" onsubmit="return confirm('Delete this room?');" class="inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="text-red-600 hover:text-red-900 font-semibold">Delete</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="px-6 py-12 text-center text-gray-500">No rooms yet. Use the form above to add rooms.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <x-copyright-minimal />
        </main>
    </x-app-layout>
