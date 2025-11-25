<x-app-layout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between mb-6 gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Archived Pigs & Piglets</h1>
                <p class="text-sm text-gray-500">Includes animals removed because of sales, deaths, or manual deletions. Restore when needed.</p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('deaths.index') }}" class="inline-flex items-center px-4 py-2 bg-white border border-gray-200 rounded-lg text-sm font-semibold text-gray-700 hover:bg-gray-50">
                    View Death Records
                </a>
                <a href="{{ route('sales.index') }}" class="inline-flex items-center px-4 py-2 bg-white border border-gray-200 rounded-lg text-sm font-semibold text-gray-700 hover:bg-gray-50">
                    View Sales
                </a>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 mb-6">
            <form method="GET" class="p-4 grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide">Search</label>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Ear tag, code..." class="mt-1 w-full rounded-lg border-gray-200 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide">Category</label>
                    <select name="category" class="mt-1 w-full rounded-lg border-gray-200 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="">All</option>
                        <option value="piglet" @selected(request('category') === 'piglet')>Piglets (&lt; {{ $weaningAge }} days)</option>
                        <option value="adult" @selected(request('category') === 'adult')>Adults (≥ {{ $weaningAge }} days)</option>
                    </select>
                </div>
                <div class="flex items-end">
                    <button type="submit" class="w-full px-4 py-2 bg-indigo-600 text-white rounded-lg font-semibold shadow hover:bg-indigo-700">Apply Filters</button>
                </div>
            </form>
        </div>

        <div class="bg-white rounded-xl shadow border border-gray-100">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Ear Tag / Code</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Category</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Reason</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Removed</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        @forelse($pigs as $pig)
                            @php
                                $isPiglet = $pig->date_of_birth?->diffInDays(now()) < $weaningAge;
                                $status = $pig->deaths->isNotEmpty() ? 'Deceased' : ($pig->sales->isNotEmpty() ? 'Sold' : 'Removed');
                            @endphp
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-4">
                                    <div class="text-sm font-semibold text-gray-900">{{ $pig->ear_tag_number ?? 'No Tag' }}</div>
                                    <div class="text-xs text-gray-500">{{ $pig->code }}</div>
                                </td>
                                <td class="px-4 py-4">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $isPiglet ? 'bg-yellow-100 text-yellow-800' : 'bg-blue-100 text-blue-800' }}">
                                        {{ $isPiglet ? 'Piglet (< ' . $weaningAge . 'd)' : 'Adult' }}
                                    </span>
                                </td>
                                <td class="px-4 py-4">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                        {{ $status === 'Deceased' ? 'bg-red-100 text-red-800' : ($status === 'Sold' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800') }}">
                                        {{ $status }}
                                    </span>
                                </td>
                                <td class="px-4 py-4 text-sm text-gray-600">
                                    {{ optional($pig->deleted_at)->format('M d, Y') }}
                                    <div class="text-xs text-gray-400">
                                        {{ optional($pig->deleted_at)->diffForHumans() }}
                                    </div>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="flex items-center gap-3">
                                        <a href="{{ route('pigs.show', $pig->id) }}" class="text-indigo-600 text-sm font-semibold hover:text-indigo-800">View</a>
                                        <form action="{{ route('pigs.restore', $pig->id) }}" method="POST" onsubmit="return confirm('Restore this record to active pigs?');">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="text-sm font-semibold text-green-600 hover:text-green-700">Restore</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-12 text-center text-gray-500">
                                    <div class="flex flex-col items-center">
                                        <svg class="w-10 h-10 text-gray-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8c1.654 0 3-1.346 3-3S13.654 2 12 2 9 3.346 9 5s1.346 3 3 3zm0 2c-2.21 0-4 1.79-4 4v2H4v4h16v-4h-4v-2c0-2.21-1.79-4-4-4z"/></svg>
                                        <p class="font-semibold">No archived pigs found</p>
                                        <p class="text-xs text-gray-400 mt-1">Records will appear here after sales, deaths, or manual deletions.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($pigs->hasPages())
                <div class="px-6 py-4 border-t border-gray-100">
                    {{ $pigs->links() }}
                </div>
            @endif
        </div>

        <!-- Archived Piglet Deaths Table -->
        <div class="bg-white rounded-xl shadow border border-gray-100 mt-12">
            <div class="px-6 py-4 border-b border-gray-200 bg-yellow-50">
                <h2 class="text-lg text-yellow-900 font-bold">Archived Piglet Death Records</h2>
                <p class="text-xs text-yellow-700">Piglet deaths that have been soft-deleted. Restore if mistakenly recorded.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Sow (Tag)</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Sow (Code)</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Farrowing Date</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Death Date</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Count</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Cause</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Notes</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Restore</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-100">
                        @forelse($pigletDeaths as $death)
                            <tr class="hover:bg-yellow-50">
                                <td class="px-4 py-4 text-sm text-gray-900">{{ $death->farrowing->sow->ear_tag_number ?? 'N/A' }}</td>
                                <td class="px-4 py-4 text-sm text-gray-600">{{ $death->farrowing->sow->code ?? 'N/A' }}</td>
                                <td class="px-4 py-4 text-sm">{{ optional($death->farrowing->farrowing_date)->format('M d, Y') }}</td>
                                <td class="px-4 py-4 text-sm">{{ optional($death->death_date)->format('M d, Y') }}</td>
                                <td class="px-4 py-4 text-lg text-center font-extrabold text-red-700">{{ $death->count }}</td>
                                <td class="px-4 py-4 text-sm text-gray-700">{{ $death->cause ?? '-' }}</td>
                                <td class="px-4 py-4 text-xs text-gray-500">{{ $death->notes }}</td>
                                <td class="px-4 py-4 text-center">
                                    <form action="{{ route('pigs.piglet-death-restore', $death->id) }}" method="POST" onsubmit="return confirm('Restore this piglet death record?');">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="inline-flex items-center px-3 py-1.5 text-xs font-semibold rounded bg-yellow-200 text-yellow-900 hover:bg-yellow-300 transition">
                                            Restore
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-12 text-center text-gray-500">
                                    <div class="flex flex-col items-center">
                                        <svg class="w-8 h-8 text-gray-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8c1.654 0 3-1.346 3-3S13.654 2 12 2 9 3.346 9 5s1.346 3 3 3zm0 2c-2.21 0-4 1.79-4 4v2H4v4h16v-4h-4v-2c0-2.21-1.79-4-4-4z"/></svg>
                                        <p class="font-semibold">No archived piglet deaths</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($pigletDeaths->hasPages())
                <div class="px-6 py-4 border-t border-gray-100">
                    {{ $pigletDeaths->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>

