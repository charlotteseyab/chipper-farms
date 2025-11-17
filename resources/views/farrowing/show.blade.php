<x-app-layout>
    <div class="min-h-screen bg-gray-50">
        <!-- Header -->
        <div class="bg-white shadow-sm border-b border-gray-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-4">
                        <a href="{{ route('farrowing.index') }}" class="text-gray-500 hover:text-gray-700">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                            </svg>
                        </a>
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900">Farrowing Details</h1>
                            <p class="text-gray-500">Record #{{ $farrowing->id }} • {{ $farrowing->farrowing_date ? $farrowing->farrowing_date->format('M d, Y') : 'Date not set' }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <!-- Overview Cards -->
            <div class="grid grid-cols-1 md:grid-cols-5 gap-6 mb-8">
                <div class="bg-white rounded-lg shadow border border-gray-200 p-6">
                    <div class="flex items-center">
                        <div class="p-3 bg-green-100 rounded-lg">
                            <svg class="h-6 w-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Live Births</p>
                            <p class="text-2xl font-semibold text-gray-900">{{ $farrowing->live_births }}</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow border border-gray-200 p-6">
                    <div class="flex items-center">
                        <div class="p-3 bg-red-100 rounded-lg">
                            <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v8m-4-4h8"/>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Stillbirths</p>
                            <p class="text-2xl font-semibold text-gray-900">{{ $farrowing->stillbirths }}</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow border border-gray-200 p-6">
                    <div class="flex items-center">
                        <div class="p-3 bg-blue-100 rounded-lg">
                            <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6"/>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Total Born</p>
                            <p class="text-2xl font-semibold text-gray-900">{{ $farrowing->total_piglets_born }}</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow border border-gray-200 p-6">
                    <div class="flex items-center">
                        <div class="p-3 bg-purple-100 rounded-lg">
                            <svg class="h-6 w-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3"/>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Survival Rate</p>
                            <p class="text-2xl font-semibold text-gray-900">{{ $farrowing->survival_rate }}%</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow border border-gray-200 p-6">
                    <div class="flex items-center">
                        <div class="p-3 bg-yellow-100 rounded-lg">
                            <svg class="h-6 w-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3"/>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Recorded Piglet Deaths</p>
                            <p class="text-2xl font-semibold text-red-600">-{{ $totalPigletDeaths }}</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow border border-gray-200 p-6">
                    <div class="flex items-center">
                        <div class="p-3 bg-blue-100 rounded-lg">
                            <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v4a1 1 0 001 1h3v9l7-12h-4l4-7H9l-5 7z"/>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Current Litter Size</p>
                            <p class="text-2xl font-semibold text-gray-900">{{ $netLivePiglets }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Details Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <div class="lg:col-span-2 space-y-8">
                    <!-- Farrowing Details -->
                    <div class="bg-white rounded-lg shadow border border-gray-200">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h2 class="text-lg font-semibold text-gray-900">Farrowing Information</h2>
                        </div>
                        <div class="p-6">
                            <dl class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <dt class="text-sm font-medium text-gray-700">Sow</dt>
                                    <dd class="mt-1 text-sm text-gray-900">
                                        @if($farrowing->sow)
                                            <a href="{{ route('pigs.show', $farrowing->sow->id) }}" class="text-blue-600 hover:text-blue-800">
                                                {{ $farrowing->sow->ear_tag_number }} • {{ $farrowing->sow->code }}
                                            </a>
                                        @else
                                            <span class="text-gray-400">Not set</span>
                                        @endif
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-700">Boar</dt>
                                    <dd class="mt-1 text-sm text-gray-900">
                                        @if($farrowing->boar)
                                            <a href="{{ route('pigs.show', $farrowing->boar->id) }}" class="text-blue-600 hover:text-blue-800">
                                                {{ $farrowing->boar->ear_tag_number }} • {{ $farrowing->boar->code }}
                                            </a>
                                        @else
                                            <span class="text-gray-400">Not set</span>
                                        @endif
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-700">Farrowing Date</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ $farrowing->farrowing_date ? $farrowing->farrowing_date->format('M d, Y') : 'Not set' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-700">Farrowing Time</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ $farrowing->farrowing_time ? $farrowing->farrowing_time->format('H:i') : 'Not set' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-700">Farrowing Type</dt>
                                    <dd class="mt-1">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $farrowing->farrowing_type_badge }}">
                                            {{ ucfirst($farrowing->farrowing_type) }}
                                        </span>
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-700">Difficulty</dt>
                                    <dd class="mt-1">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $farrowing->farrowing_difficulty_badge }}">
                                            {{ ucfirst($farrowing->farrowing_difficulty) }}
                                        </span>
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-700">Sow Condition</dt>
                                    <dd class="mt-1">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $farrowing->sow_condition_badge }}">
                                            {{ ucfirst($farrowing->sow_condition) }}
                                        </span>
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-700">Gestation Length</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ $farrowing->gestation_length_days ? $farrowing->gestation_length_days . ' days' : 'Not set' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-700">Male Piglets</dt>
                                    <dd class="mt-1 text-sm text-gray-900">
                                        <span class="inline-flex items-center">
                                            <svg class="w-4 h-4 text-blue-600 mr-1" fill="currentColor" viewBox="0 0 24 24">
                                                <circle cx="9" cy="15" r="6" stroke="currentColor" stroke-width="2" fill="none"/>
                                                <path d="M15 9L21 3M21 3h-5M21 3v5" stroke="currentColor" stroke-width="2" fill="none"/>
                                            </svg>
                                            {{ $farrowing->number_of_males > 0 ? $farrowing->number_of_males : 'N/A' }}
                                        </span>
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-700">Female Piglets</dt>
                                    <dd class="mt-1 text-sm text-gray-900">
                                        <span class="inline-flex items-center">
                                            <svg class="w-4 h-4 text-pink-600 mr-1" fill="currentColor" viewBox="0 0 24 24">
                                                <circle cx="12" cy="8" r="5" stroke="currentColor" stroke-width="2" fill="none"/>
                                                <path d="M12 13v8M9 18h6" stroke="currentColor" stroke-width="2" fill="none"/>
                                            </svg>
                                            {{ $farrowing->number_of_females > 0 ? $farrowing->number_of_females : 'N/A' }}
                                        </span>
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-700">Recorded By</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ optional($farrowing->recordedBy)->name ?? 'Unknown' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-700">Status</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ ucfirst($farrowing->status ?? 'active') }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-700">Gender Distribution</dt>
                                    <dd class="mt-1 text-sm text-gray-900">
                                        {{ $genderDistribution['male_percentage'] }}% male • {{ $genderDistribution['female_percentage'] }}% female
                                    </dd>
                                </div>
                            </dl>
                        </div>
                    </div>

                    <!-- Litter Details -->
                    <!-- <div class="bg-white rounded-lg shadow border border-gray-200">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h2 class="text-lg font-semibold text-gray-900">Litter Details</h2>
                        </div>
                        <div class="p-6">
                            <dl class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div>
                                    <dt class="text-sm font-medium text-gray-700">Males</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ $farrowing->number_of_males ?? 0 }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-700">Females</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ $farrowing->number_of_females ?? 0 }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-700">Gender Distribution</dt>
                                    <dd class="mt-1 text-sm text-gray-900">
                                        {{ $genderDistribution['male_percentage'] }}% male • {{ $genderDistribution['female_percentage'] }}% female
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-700">Avg Birth Weight</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ $farrowing->average_birth_weight ? number_format($farrowing->average_birth_weight, 2) . ' kg' : 'N/A' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-700">Total Litter Weight</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ $farrowing->total_litter_weight ? number_format($farrowing->total_litter_weight, 2) . ' kg' : 'N/A' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-700">Functional Teats</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ $farrowing->functional_teats ?? 'N/A' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-700">Colostrum Available</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ $farrowing->colostrum_available ? 'Yes' : 'No' }}</dd>
                                </div>
                            </dl>
                        </div>
                    </div> -->

                    <!-- Notes & Complications -->
                    <div class="bg-white rounded-lg shadow border border-gray-200">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h2 class="text-lg font-semibold text-gray-900">Notes & Complications</h2>
                        </div>
                        <div class="p-6 space-y-4">
                            <div>
                                <h3 class="text-sm font-medium text-gray-700">Complications</h3>
                                <p class="mt-1 text-sm text-gray-900">{{ $farrowing->complications ?: 'None reported' }}</p>
                            </div>
                            <div>
                                <h3 class="text-sm font-medium text-gray-700">Notes</h3>
                                <p class="mt-1 text-sm text-gray-900">{{ $farrowing->notes ?: 'No additional notes' }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="space-y-8">
                    <div class="bg-white rounded-lg shadow border border-gray-200">
                        <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                            <h2 class="text-lg font-semibold text-gray-900">Piglet Mortality Records</h2>
                            <div class="text-sm text-gray-600">
                                Total events: {{ $pigletDeaths->count() }}
                            </div>
                        </div>
                        <div class="p-6">
                            @if($pigletDeaths->isEmpty())
                                <div class="text-gray-500 text-sm">No piglet deaths have been recorded for this farrowing.</div>
                            @else
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Count</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cause</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Notes</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Recorded By</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            @foreach($pigletDeaths as $death)
                                                <tr x-data="{ manageOpen: false, editOpen: false }">
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $death->death_date?->format('M d, Y') ?? 'N/A' }}</td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-red-600">{{ $death->count }}</td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $death->cause ?? '—' }}</td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $death->notes ?? '—' }}</td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ optional($death->recordedBy)->name ?? '—' }}</td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                        <div class="relative inline-block text-left">
                                                            <button @click="manageOpen = !manageOpen" class="inline-flex justify-center w-full px-3 py-1 bg-gray-100 text-gray-700 rounded-md text-xs font-medium hover:bg-gray-200 focus:outline-none">
                                                                Manage
                                                                <svg class="-mr-1 ml-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                                                </svg>
                                                            </button>
                                                            <div x-cloak x-show="manageOpen" @click.away="manageOpen = false" class="origin-top-right absolute right-0 mt-2 w-48 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 focus:outline-none z-10">
                                                                <div class="py-1">
                                                                    <button @click="editOpen = true; manageOpen = false" class="block w-full text-left px-4 py-2 text-xs text-indigo-600 hover:bg-indigo-50">Edit</button>
                                                                    <form method="POST" action="{{ route('farrowing.piglet-deaths.destroy', $death) }}" onsubmit="return confirm('Delete this piglet death record?');">
                                                                        @csrf
                                                                        @method('DELETE')
                                                                        <button type="submit" class="block w-full text-left px-4 py-2 text-xs text-red-600 hover:bg-red-50">Delete</button>
                                                                    </form>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <template x-if="editOpen">
                                                            <div class="fixed inset-0 z-50 flex items-center justify-center">
                                                                <div class="absolute inset-0 bg-black bg-opacity-40" @click="editOpen = false"></div>
                                                                <div class="relative bg-white rounded-lg shadow-xl w-full max-w-md mx-4">
                                                                    <div class="px-5 py-4 border-b flex items-center justify-between">
                                                                        <h3 class="text-sm font-semibold text-gray-900">Edit Piglet Death</h3>
                                                                        <button type="button" class="text-gray-400 hover:text-gray-600" @click="editOpen = false">✕</button>
                                                                    </div>
                                                                    <div class="p-5">
                                                                        <form method="POST" action="{{ route('farrowing.piglet-deaths.update', $death) }}" class="space-y-4">
                                                                            @csrf
                                                                            @method('PUT')
                                                                            <div>
                                                                                <label class="block text-xs text-gray-600">Date</label>
                                                                                <input type="date" name="death_date" class="mt-1 block w-full rounded-md border-gray-300 focus:border-red-500 focus:ring-red-500" value="{{ $death->death_date?->toDateString() }}" required>
                                                                            </div>
                                                                            <div>
                                                                                <label class="block text-xs text-gray-600">Count</label>
                                                                                <input type="number" name="count" min="1" class="mt-1 block w-full rounded-md border-gray-300 focus:border-red-500 focus:ring-red-500" value="{{ $death->count }}" required>
                                                                            </div>
                                                                            <div>
                                                                                <label class="block text-xs text-gray-600">Cause (optional)</label>
                                                                                <input type="text" name="cause" class="mt-1 block w-full rounded-md border-gray-300 focus:border-red-500 focus:ring-red-500" value="{{ $death->cause }}">
                                                                            </div>
                                                                            <div>
                                                                                <label class="block text-xs text-gray-600">Notes (optional)</label>
                                                                                <textarea name="notes" rows="3" class="mt-1 block w-full rounded-md border-gray-300 focus:border-red-500 focus:ring-red-500">{{ $death->notes }}</textarea>
                                                                            </div>
                                                                            <div class="flex items-center justify-end space-x-2">
                                                                                <button type="button" @click="editOpen = false" class="px-3 py-1.5 rounded-md border text-xs text-gray-700 hover:bg-gray-50">Cancel</button>
                                                                                <button type="submit" class="px-3 py-1.5 rounded-md bg-red-600 text-white hover:bg-red-700 text-xs">Save Changes</button>
                                                                            </div>
                                                                        </form>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </template>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- Follow-up -->
                    <div class="bg-white rounded-lg shadow border border-gray-200">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h2 class="text-lg font-semibold text-gray-900">Follow-up</h2>
                        </div>
                        <div class="p-6">
                            <dl class="space-y-3">
                                <div class="flex justify-between">
                                    <dt class="text-sm text-gray-600">Follow-up Date</dt>
                                    <dd class="text-sm font-medium text-gray-900">{{ $farrowing->follow_up_date ? $farrowing->follow_up_date->format('M d, Y H:i') : 'Not scheduled' }}</dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-sm text-gray-600">Sow Weight After Farrowing</dt>
                                    <dd class="text-sm font-medium text-gray-900">{{ $farrowing->sow_weight_after_farrowing ? number_format($farrowing->sow_weight_after_farrowing, 1) . ' kg' : 'N/A' }}</dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </main>
        <x-copyright-minimal />
    </div>
</x-app-layout>


