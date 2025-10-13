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
                        <thead class="bg-indigo-100">
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
                                                
                                                <button
                                                    type="button"
                                                    onclick="openSkipModal('{{ route('feeds.schedule.mark-skipped', $entry) }}')"
                                                    title="Skip Feeding"
                                                    class="h-8 w-16 flex items-center justify-center bg-gray-500 text-white rounded-lg text-xs font-medium shadow hover:bg-gray-600 transition"
                                                >
                                                    Skip
                                                </button>
                                                
                                                <!-- NEW: View Button for Planned -->
                                                <a href="{{ route('feeds.schedule.show', $entry) }}" 
                                                   title="View Details" 
                                                   class="h-8 w-16 flex items-center justify-center bg-indigo-600 text-white rounded-lg text-xs font-medium shadow hover:bg-indigo-700 transition">
                                                    View
                                                </a>
                                            </div>
                                        @elseif($entry->status === 'fed')
                                            <div class="text-xs text-gray-500 space-y-0.5">
                                                <div class="font-medium text-green-700 flex items-center">
                                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                                    Fed @ {{ optional($entry->fed_at)->format('H:i') }}
                                                </div>
                                                <div class="text-gray-600">By {{ optional($entry->fedBy)->name ?? '—' }}</div>
                                                <!-- NEW: View Button for Fed -->
                                                <a href="{{ route('feeds.schedule.show', $entry) }}" 
                                                   title="View Details" 
                                                   class="mt-2 h-6 w-16 flex items-center justify-center border border-indigo-600 text-indigo-600 rounded-lg text-xs font-medium hover:bg-indigo-50 transition">
                                                    View
                                                </a>
                                            </div>
                                        @else
                                            <div class="text-xs text-gray-500 space-y-0.5">
                                                <div class="text-gray-500 flex items-center">
                                                    <svg class="w-4 h-4 mr-1 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                                    Skipped 
                                                </div>
                                                @if(isset($entry->skip_reason))
                                                    <span class="ml-1 text-gray-400" title="{{ $entry->skip_reason }}"> (View Reason)</span>
                                                @endif
                                                <!-- NEW: View Button for Skipped -->
                                                <a href="{{ route('feeds.schedule.show', $entry) }}" 
                                                   title="View Details" 
                                                   class="mt-2 h-6 w-16 flex items-center justify-center border border-indigo-600 text-indigo-600 rounded-lg text-xs font-medium hover:bg-indigo-50 transition">
                                                    View
                                                </a>
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
        <x-copyright-minimal />

        <!-- Skip Reason Modal -->
        <div id="skip-modal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <!-- Background overlay -->
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" onclick="closeSkipModal()"></div>

                <!-- This element is to trick the browser into centering the modal contents. -->
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                <!-- Modal Panel -->
                <div class="inline-block align-bottom bg-white rounded-xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <form id="skip-form" method="POST" action="">
                        <div class="bg-white p-6">
                            <div class="sm:flex sm:items-start">
                                <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10 shadow-lg">
                                    <!-- Icon -->
                                    <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                                </div>
                                <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                                    <h3 class="text-xl leading-6 font-extrabold text-gray-900" id="modal-title">
                                        Skip Feeding Event
                                    </h3>
                                    <div class="mt-4">
                                        <p class="text-sm text-gray-500 mb-4">
                                            Please provide a brief reason for skipping this feeding event. This is required for record keeping.
                                        </p>
                                        <label for="skip_reason" class="block text-sm font-semibold text-gray-700 mb-1">Reason for Skipping</label>
                                        <div class="mt-1">
                                            <textarea id="skip_reason" name="skip_reason" rows="3" required
                                                class="shadow-inner focus:ring-red-500 focus:border-red-500 block w-full sm:text-sm border-gray-300 rounded-lg p-3 transition duration-150"
                                                placeholder="e.g., Equipment malfunction, insufficient feed stock, extreme weather, etc."></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="bg-gray-100 px-6 py-4 flex flex-col sm:flex-row-reverse sm:justify-start gap-3">
                            <!-- Since the mark-skipped route usually expects a POST, we ensure the method is POST -->
                            <input type="hidden" name="_method" value="POST"> 
                            @csrf
                            <button type="submit"
                                    class="w-full inline-flex justify-center rounded-lg shadow-md px-5 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition duration-150 sm:w-auto sm:text-sm">
                                Confirm Skip
                            </button>
                            <button type="button" onclick="closeSkipModal()"
                                    class="mt-3 sm:mt-0 w-full inline-flex justify-center rounded-lg border border-gray-300 shadow-sm px-5 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition duration-150 sm:w-auto sm:text-sm">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    </div>

    <script>
        const skipModal = document.getElementById('skip-modal');
        const skipForm = document.getElementById('skip-form');
        const skipReason = document.getElementById('skip_reason');
        
        /**
         * Opens the modal and sets the form action URL dynamically.
         * @param {string} route The Laravel route URL for the mark-skipped action.
         */
        function openSkipModal(route) {
            skipForm.action = route;
            skipReason.value = ''; // Clear previous reason
            skipModal.classList.remove('hidden');
            skipReason.focus(); // Focus on the reason input for immediate typing
        }
        
        /**
         * Closes the modal and resets the form.
         */
        function closeSkipModal() {
            skipModal.classList.add('hidden');
            skipReason.value = ''; // Clear reason on cancel
        }

        // Allow closing the modal using the Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape' && !skipModal.classList.contains('hidden')) {
                closeSkipModal();
            }
        });
    </script>
</x-app-layout>
