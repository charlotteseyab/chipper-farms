<x-app-layout>
    <div class="min-h-screen">
        <!-- Header -->
        <div class="bg-white shadow">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 flex flex-col sm:flex-row items-start sm:items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-black">Feed Schedule</h1>
                    <p class="text-gray-500 mt-1">Plan and record feeding events.</p>
                </div>
                <form method="GET" class="mt-4 sm:mt-0 flex items-end gap-4 bg-white rounded-lg shadow px-4 py-3">
                    <div>
                        <label class="block text-xs text-gray-500">From</label>
                        <input type="date" name="from" value="{{ $from ?? now()->toDateString() }}"
                            class="border rounded px-2 py-1 text-sm focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500">To</label>
                        <input type="date" name="to" value="{{ $to ?? now()->toDateString() }}"
                            class="border rounded px-2 py-1 text-sm focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500">Status</label>
                        <select name="status"
                            class="border rounded px-2 py-1 text-sm focus:ring-blue-500 focus:border-blue-500">
                            @php($all = implode(',', $statuses ?? []))
                            <option value="planned,fed,skipped" {{ $all=="planned,fed,skipped" ? 'selected' : '' }}>All</option>
                            <option value="planned" {{ $all=="planned" ? 'selected' : '' }}>Planned</option>
                            <option value="fed" {{ $all=="fed" ? 'selected' : '' }}>Fed</option>
                            <option value="skipped" {{ $all=="skipped" ? 'selected' : '' }}>Skipped</option>
                        </select>
                    </div>
                    <button class="px-4 py-2 bg-blue-600 text-white rounded-md shadow hover:bg-blue-700 transition">
                        Filter
                    </button>
                </form>
            </div>
        </div>

        <!-- Main Content -->
        <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <!-- Button -->
            <div class="mb-6 flex justify-end">
                <a href="{{ route('feeds.schedule.create') }}"
                   class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg shadow hover:bg-blue-700 transition">
                    + New Schedule
                </a>
            </div>

            <!-- Table -->
            <div class="bg-white rounded-lg shadow border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-800">Scheduled Feedings</h3>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left font-medium text-gray-600">Date</th>
                                <th class="px-6 py-3 text-left font-medium text-gray-600">Structure</th>
                                <th class="px-6 py-3 text-left font-medium text-gray-600">Feed Type</th>
                                <th class="px-6 py-3 text-left font-medium text-gray-600">Planned Amount</th>
                                <th class="px-6 py-3 text-left font-medium text-gray-600">Status</th>
                                <th class="px-6 py-3 text-left font-medium text-gray-600">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-100">
                            @forelse($entries as $entry)
                                <tr class="hover:bg-gray-50 transition">
                                    <td class="px-6 py-4">{{ $entry->scheduled_for->format('M d, Y') }}</td>
                                    <td class="px-6 py-4">{{ optional($entry->structure)->name ?? optional($entry->group->structure ?? null)->name ?? ('Structure #' . $entry->structure_id) }}</td>
                                    <td class="px-6 py-4 font-medium text-gray-800">{{ $entry->feedType->name ?? ('FeedType #' . $entry->feed_type_id) }}</td>
                                    <td class="px-6 py-4">{{ number_format($entry->planned_amount, 2) }} {{ $entry->unit }}</td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                            {{
                                                match($entry->status) {
                                                    'planned' => 'bg-yellow-100 text-yellow-800',
                                                    'fed' => 'bg-green-100 text-green-800',
                                                    'skipped' => 'bg-gray-200 text-gray-700',
                                                    default => 'bg-gray-200 text-gray-700'
                                                }
                                            }}">
                                            {{ ucfirst($entry->status) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        @if($entry->status === 'planned')
                                            <div class="flex items-center gap-2">
                                                <form method="POST" action="{{ route('feeds.schedule.mark-fed', $entry) }}">
                                                    @csrf
                                                    <button class="px-3 py-1 bg-green-600 text-white rounded text-xs shadow hover:bg-green-700">Mark as Fed</button>
                                                </form>
                                                <form method="POST" action="{{ route('feeds.schedule.mark-skipped', $entry) }}">
                                                    @csrf
                                                    <button class="px-3 py-1 bg-gray-500 text-white rounded text-xs shadow hover:bg-gray-600">Skip</button>
                                                </form>
                                            </div>
                                        @elseif($entry->status === 'fed')
                                            <div class="text-xs text-gray-500">✔ Fed {{ optional($entry->fed_at)->format('M d, Y H:i') }} by <span class="font-medium">{{ optional($entry->feeder)->name ?? '—' }}</span></div>
                                        @else
                                            <div class="text-xs text-gray-500">✖ Skipped</div>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-6 py-12 text-center text-gray-400">No scheduled feedings for the selected range.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="px-6 py-4 border-t border-gray-100">
                    {{ $entries->links('pagination::tailwind') }}
                </div>
            </div>
        </main>
    </div>
</x-app-layout>
