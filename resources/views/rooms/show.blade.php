<x-app-layout>
    <style>
        /* Custom styles for modern capacity bar */
        .capacity-fill {
            height: 100%;
            transition: width 0.3s ease-in-out;
            border-radius: 9999px;
        }
    </style>
    <main class="flex-1 p-4 sm:p-8 bg-gray-100 min-h-screen">
        <div class="max-w-7xl mx-auto">
            
            <!-- Header & Back Button -->
            <div class="flex justify-between items-center mb-6 border-b pb-4 border-gray-200">
                <div>
                    <h2 class="text-3xl font-extrabold text-gray-900">Room: {{ $room->name }}</h2>
                    <p class="text-lg text-gray-600 mt-1">{{ $room->structure->name }} / {{ $room->code }}</p>
                </div>
                <div class="flex items-center space-x-3">
                    <a href="{{ route('rooms.edit', $room) }}" class="flex items-center px-5 py-2 bg-indigo-600 text-white rounded-xl font-medium hover:bg-indigo-700 transition shadow-lg">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                        Edit Room
                    </a>
                    <a href="{{ route('structures.show', $room->structure) }}" class="flex items-center px-5 py-2 bg-gray-800 text-white rounded-xl font-medium hover:bg-gray-700 transition shadow-lg">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                        </svg>
                        Back to Structure
                    </a>
                </div>
            </div>

            <!-- Top Row: Key Metrics and Alerts -->
            
            @php
                $currentCount = $room->pigs->count();
                $capacity = $room->capacity ?? 0;
                $percentage = $capacity > 0 ? round(($currentCount / $capacity) * 100) : 0;
                $isFull = $capacity > 0 && $percentage >= 100;
                $isNearFull = $capacity > 0 && $percentage >= 75 && $percentage < 100;

                if ($isFull) {
                    $statusColor = 'bg-red-600';
                    $ringColor = 'ring-red-500';
                } elseif ($isNearFull) {
                    $statusColor = 'bg-yellow-500';
                    $ringColor = 'ring-yellow-400';
                } else {
                    $statusColor = 'bg-green-600';
                    $ringColor = 'ring-green-500';
                }

                $femaleIds = $room->pigs->where('gender','F')->pluck('id');
                // NOTE: Assuming Mating model and 'pregnancy_status' field are correct as per original code
                $pregnantFemaleIds = $femaleIds->isNotEmpty()
                    ? \App\Models\Mating::whereIn('female_id', $femaleIds)
                        ->where('pregnancy_status','pregnant')
                        ->pluck('female_id')
                        ->unique()
                    : collect();
                $pregnantPigs = $room->pigs->whereIn('id', $pregnantFemaleIds);
            @endphp

            <!-- Key Metrics -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                
                <!-- Metric Card 1: Animal Count -->
                <div class="bg-white rounded-xl shadow-md p-5 border border-gray-200">
                    <p class="text-sm font-medium text-gray-500">Current Stock</p>
                    <div class="flex items-end justify-between mt-1">
                        <div class="text-4xl font-extrabold text-indigo-700">{{ $currentCount }} / {{ $room->capacity }}</div>
                        <div class="text-sm font-medium text-indigo-500">Total Animals</div>
                    </div>
                </div>

                <!-- Metric Card 2: Room Type -->
                <div class="bg-white rounded-xl shadow-md p-5 border border-gray-200">
                    <p class="text-sm font-bold text-gray-500">Room Code</p>
                    <div class="flex items-end justify-between mt-1">
                        <!-- <div class="text-4xl font-extrabold text-gray-700">{{ ucfirst($room->type) }}</div> -->
                        <div class="text-sm font-medium text-gray-500">T{{ $room->code }}</div>
                    </div>
                </div>

                <!-- Metric Card 3: Capacity Status -->
                <div class="bg-white rounded-xl shadow-md p-5 border border-gray-200">
                    <p class="text-sm font-medium text-gray-500 mb-2">Capacity Utilization</p>
                    <div class="flex items-center justify-between gap-4">
                        <div class="flex-1">
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="capacity-fill {{ $statusColor }}" style="width: {{ min($percentage, 100) }}%;"></div>
                            </div>
                        </div>
                        <span class="text-lg font-bold text-gray-800">{{ $percentage }}%</span>
                    </div>
                    <p class="text-xs text-gray-500 mt-2 text-right">
                        {{ $currentCount }} of {{ $room->capacity ?? 'N/A' }} animals allowed
                    </p>
                </div>
            </div>

            <!-- Alerts and Notes Row (NEW FULL WIDTH SECTION) -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                
                <!-- Pregnant Sows Alert (2/3 width) -->
                <div class="lg:col-span-2 bg-yellow-50 rounded-xl shadow-md p-5 border border-yellow-300">
                    <h4 class="flex items-center text-sm font-bold text-yellow-800 mb-3">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Sow Pregnancy Watch ({{ $pregnantPigs->count() }})
                    </h4>
                    <div class="flex flex-wrap gap-2">
                        @if($pregnantPigs->count() > 0)
                            @foreach($pregnantPigs as $pig)
                                <a href="{{ route('pigs.show', $pig->id) }}" class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-yellow-100 text-yellow-800 hover:bg-yellow-200 transition">
                                    {{ $pig->ear_tag_number }}
                                </a>
                            @endforeach
                        @else
                            <p class="text-sm text-yellow-700">No pregnant sows in this room.</p>
                        @endif
                    </div>
                </div>

                <!-- Room Notes (1/3 width) -->
                <div class="lg:col-span-1 bg-white rounded-xl shadow-md p-5 border border-gray-200">
                    <h4 class="text-sm font-bold text-gray-700 mb-2 border-b pb-1">Notes</h4>
                    <p class="text-sm text-gray-600">
                        {{ $room->notes ?? 'No specific notes recorded for this room.' }}
                    </p>
                </div>

            </div>
            
            <!-- Assigned Animals Table (NOW FULL WIDTH) -->
            <div class="bg-white rounded-xl shadow-lg border border-gray-200">
                <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between bg-gray-50 rounded-t-xl">
                    <h3 class="text-xl font-bold text-gray-900">Assigned Animals ({{ $currentCount }})</h3>
                    <a href="{{ route('all-stocks.index') }}" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 transition">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                        </svg>
                        Add New Animal
                    </a>
                </div>
                
                @if($currentCount > 0)
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Ear Tag</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Code</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Breed</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Gender</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Age</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Stock Type</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-100">
                                @foreach($room->pigs as $pig)
                                    <tr class="hover:bg-indigo-50 transition">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            {{ $pig->ear_tag_number }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                            {{ $pig->code }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                            {{ $pig->breed }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium
                                                {{ $pig->gender === 'M' ? 'bg-blue-100 text-blue-800' : 'bg-pink-100 text-pink-800' }}">
                                                {{ $pig->gender === 'M' ? 'Male' : 'Female' }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                            {{ $pig->age_compact }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium
                                                {{ $pig->stock_type === 'breeding' ? 'bg-purple-100 text-purple-800' : 'bg-green-100 text-green-800' }}">
                                                {{ ucfirst($pig->stock_type) }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <a href="{{ route('pigs.show', $pig->id) }}" class="text-indigo-600 hover:text-indigo-900 font-semibold">
                                                View
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="px-6 py-12 text-center bg-white rounded-b-xl">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                        </svg>
                        <h3 class="mt-2 text-base font-semibold text-gray-900">No Animals Assigned</h3>
                        <p class="mt-1 text-sm text-gray-500">This room is empty. Use the Animal Move feature to assign stock.</p>
                    </div>
                @endif
            </div>
        </div>
    </main>
    <x-copyright-minimal />
</x-app-layout>
