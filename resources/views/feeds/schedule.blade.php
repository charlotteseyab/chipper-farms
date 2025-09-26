<x-app-layout>
    <div class="min-h-screen bg-gray-50">
        
        <!-- Header -->
        <div class="bg-white shadow-lg border-b border-gray-100">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-2 flex flex-col md:flex-row items-start md:items-center justify-between">
                
                <div>
                    <h1 class="text-3xl font-extrabold text-gray-900 leading-tight">Feed Schedule</h1>
                    <p class="text-gray-500 text-sm mt-1">Plan and record feeding events.</p>
                </div>
                
                <!-- Filter Form: only show for previous tab -->
                @if(request('tab', 'current') === 'previous')
                    <form id="filter-form" method="GET" class="mt-4 md:mt-0 flex flex-wrap items-end gap-3 bg-gray-50 p-3 rounded-xl border border-gray-200 shadow-inner">
                        <input type="hidden" name="tab" value="previous">
                        
                        <div>
                            <label class="block text-xs font-medium text-gray-700">From</label>
                            <input type="date" name="from" value="{{ $from ?? '' }}"
                                class="border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:ring-indigo-500 focus:border-indigo-500 w-32">
                        </div>
                        
                        <div>
                            <label class="block text-xs font-medium text-gray-700">To</label>
                            <input type="date" name="to" value="{{ $to ?? '' }}"
                                class="border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:ring-indigo-500 focus:border-indigo-500 w-32">
                        </div>
                        
                        <div>
                            <label class="block text-xs font-medium text-gray-700">Status</label>
                            @php
                                $all = implode(',', $statuses ?? []);
                            @endphp
                            <select name="status"
                                class="border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:ring-indigo-500 focus:border-indigo-500 w-32">
                                <option value="planned,fed,skipped" {{ $all=="planned,fed,skipped" ? 'selected' : '' }}>All Statuses</option>
                                <option value="planned" {{ $all=="planned" ? 'selected' : '' }}>Planned</option>
                                <option value="fed" {{ $all=="fed" ? 'selected' : '' }}>Fed</option>
                                <option value="skipped" {{ $all=="skipped" ? 'selected' : '' }}>Skipped</option>
                            </select>
                        </div>
                        
                        <button class="h-9 px-4 bg-indigo-600 text-white rounded-lg text-sm font-medium shadow-md hover:bg-indigo-700 transition">
                            Filter
                        </button>
                    </form>
                @endif
            </div>
        </div>

        <!-- Main Content -->
        <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            
            <div class="mb-6 flex justify-between items-center">
                @php $activeTab = request('tab', 'current'); @endphp
                
                <div class="flex border-b border-gray-300">
                    <a href="{{ route('feeds.schedule', ['tab' => 'current']) }}"
                       class="px-4 py-2 text-sm font-medium {{ $activeTab === 'current' ? 'border-b-2 border-indigo-600 text-indigo-600 bg-gray-100 rounded-t-lg' : 'text-gray-500 hover:text-gray-700' }} transition">
                        Today
                    </a>
                    <a href="{{ route('feeds.schedule', ['tab' => 'previous']) }}"
                       class="px-4 py-2 text-sm font-medium {{ $activeTab === 'previous' ? 'border-b-2 border-indigo-600 text-indigo-600 bg-gray-100 rounded-t-lg' : 'text-gray-500 hover:text-gray-700' }} transition ml-1">
                        Previous Schedules
                    </a>
                </div>

                <a href="{{ route('feeds.schedule.create') }}"
                   class="inline-flex items-center px-5 py-2 border border-transparent text-sm font-medium rounded-full shadow-lg text-white bg-indigo-600 hover:bg-indigo-700 transition duration-300 ease-in-out">
                    <svg class="w-4 h-4 mr-2 -ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                    New Schedule
                </a>
            </div>

            <div class="bg-white rounded-xl shadow-2xl border border-gray-100 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h3 class="text-xl font-bold text-gray-800">
                        {{ $activeTab === 'current' ? 'Scheduled Feedings Today' : 'Feeding Events' }}
                    </h3>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-indigo-50">
                            <tr>
                                <th class="px-6 py-3 text-left font-bold text-xs text-gray-600 uppercase tracking-wider">Date</th>
                                <th class="px-6 py-3 text-left font-bold text-xs text-gray-600 uppercase tracking-wider">Structure</th>
                                <th class="px-6 py-3 text-left font-bold text-xs text-gray-600 uppercase tracking-wider">Feed Type</th>
                                <th class="px-6 py-3 text-left font-bold text-xs text-gray-600 uppercase tracking-wider">Planned Amount</th>
                                <th class="px-6 py-3 text-left font-bold text-xs text-gray-600 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left font-bold text-xs text-gray-600 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-100">
                            @forelse($entries as $entry)
                                <tr class="hover:bg-gray-50 transition">
                                    <td class="px-6 py-3 whitespace-nowrap text-sm font-medium text-gray-900">{{ $entry->scheduled_for->format('M d, Y') }}</td>
                                    
                                    <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-700">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-indigo-100 text-indigo-700">
                                            {{ optional($entry->structure)->name ?? optional($entry->group->structure ?? null)->name ?? ('Structure #' . $entry->structure_id) }}
                                        </span>
                                    </td>
                                    
                                    <td class="px-6 py-3 whitespace-nowrap font-semibold text-gray-800">{{ $entry->feedType->name ?? ('FeedType #' . $entry->feed_type_id) }}</td>
                                    
                                    <td class="px-6 py-3 whitespace-nowrap font-bold text-green-700">{{ number_format($entry->planned_amount, 2) }} <span class="text-xs font-medium text-gray-500">{{ $entry->unit }}</span></td>
                                    
                                    <td class="px-6 py-3 whitespace-nowrap">
                                        @php
                                            if ($entry->status === 'planned') {
                                                $statusClasses = 'bg-yellow-100 text-yellow-800';
                                            } elseif ($entry->status === 'fed') {
                                                $statusClasses = 'bg-green-100 text-green-800';
                                            } elseif ($entry->status === 'skipped') {
                                                $statusClasses = 'bg-gray-200 text-gray-700';
                                            } else {
                                                $statusClasses = 'bg-gray-200 text-gray-700';
                                            }
                                        @endphp
                                        <span class="inline-flex items-center px-3 py-0.5 rounded-full text-xs font-bold {{ $statusClasses }} uppercase">
                                            {{ ucfirst($entry->status) }}
                                        </span>
                                    </td>
                                    
                                    <td class="px-6 py-3 whitespace-nowrap">
                                        @if($entry->status === 'planned')
                                            <div class="flex items-center gap-2">
                                                <form method="POST" action="{{ route('feeds.schedule.mark-fed', $entry) }}">
                                                    @csrf
                                                    <button title="Mark as Fed" class="h-8 w-16 flex items-center justify-center bg-green-600 text-white rounded-lg text-xs font-medium shadow hover:bg-green-700 transition">
                                                        Fed
                                                    </button>
                                                </form>
                                                <form method="POST" action="{{ route('feeds.schedule.mark-skipped', $entry) }}">
                                                    @csrf
                                                    <button title="Skip Feeding" class="h-8 w-16 flex items-center justify-center bg-gray-500 text-white rounded-lg text-xs font-medium shadow hover:bg-gray-600 transition">
                                                        Skip
                                                    </button>
                                                </form>
                                            </div>
                                        @elseif($entry->status === 'fed')
                                            <div class="text-xs text-gray-500 space-y-0.5">
                                                <div class="font-medium text-green-700 flex items-center">
                                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                                    Fed @ {{ optional($entry->fed_at)->format('H:i') }}
                                                </div>
                                                <div class="text-gray-600">By {{ optional($entry->feeder)->name ?? '—' }}</div>
                                            </div>
                                        @else
                                            <div class="text-xs text-gray-500 flex items-center">
                                                <svg class="w-4 h-4 mr-1 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                                Skipped
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-6 py-12 text-center bg-gray-50">
                                        <div class="flex flex-col items-center">
                                            <svg class="w-12 h-12 text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                            <span class="text-gray-500 text-base font-medium">
                                                {{ $activeTab === 'current' ? 'No scheduled feedings for today.' : 'No previous feedings found for this selection.' }}
                                            </span>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="px-6 py-3 border-t border-gray-100 flex justify-end">
                    {{ $entries->links('pagination::tailwind') }}
                </div>
            </div>
        </main>
    </div>
</x-app-layout>
