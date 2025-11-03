<x-app-layout>
    <div class="min-h-screen bg-gray-50">
        <div class="bg-white shadow-sm border-b border-gray-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">Deceased Pigs</h1>
                        <p class="text-gray-600 mt-1">Records of pig deaths and disposal</p>
                    </div>
                    <div class="flex items-center space-x-4">
                        <a href="{{ route('deaths.export.csv', request()->query()) }}" 
                           class="inline-flex items-center px-4 py-2 border border-green-300 rounded-lg text-green-700 hover:bg-green-50 transition">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            Download CSV
                        </a>
                        <a href="{{ route('deaths.export.pdf', request()->query()) }}" 
                           class="inline-flex items-center px-4 py-2 border border-red-300 rounded-lg text-red-700 hover:bg-red-50 transition">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                            </svg>
                            Download PDF
                        </a>
                        <a href="{{ route('all-stocks.index') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Record Death</a>
                    </div>
                </div>
            </div>
        </div>

        <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                <div class="bg-white rounded-lg shadow border border-gray-200 p-6">
                    <div class="text-sm text-gray-600">Total Deaths</div>
                    <div class="text-2xl font-semibold text-gray-900">{{ $stats['total_deaths'] ?? 0 }}</div>
                    <div class="text-xs text-gray-500 mt-1">
                        {{ $stats['total_adult_deaths'] ?? 0 }} pigs • {{ $stats['total_piglet_deaths'] ?? 0 }} piglets
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow border border-gray-200 p-6">
                    <div class="text-sm text-gray-600">Adult Deaths</div>
                    <div class="text-2xl font-semibold text-gray-900">{{ $stats['total_adult_deaths'] ?? 0 }}</div>
                </div>
                <div class="bg-white rounded-lg shadow border border-gray-200 p-6">
                    <div class="text-sm text-gray-600">Piglet Deaths</div>
                    <div class="text-2xl font-semibold text-red-600">{{ $stats['total_piglet_deaths'] ?? 0 }}</div>
                </div>
                <div class="bg-white rounded-lg shadow border border-gray-200 p-6">
                    <div class="text-sm text-gray-600">Last 30 Days</div>
                    <div class="text-2xl font-semibold text-gray-900">{{ $stats['recent_deaths'] ?? 0 }}</div>
                </div>
            </div>

            <!-- Adult Deaths Table -->
            <div class="bg-white rounded-lg shadow border border-gray-200 mb-6">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">Adult Deaths (60+ days)</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pig</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Age at Death</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cause</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Disposal</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($deaths ?? [] as $death)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">{{ $death->pig->code ?? 'N/A' }}</div>
                                        <div class="text-sm text-gray-500">{{ $death->pig->ear_tag_number ?? '' }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        @if($death->age_at_death)
                                            {{ $death->age_at_death_display }}
                                        @else
                                            <span class="text-gray-400">N/A</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $death->death_date->format('M d, Y') }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $death->death_cause_display }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $death->disposal_method_display }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-blue-600">
                                        <div class="flex items-center space-x-3">
                                            <a href="{{ route('deaths.show', $death->id) }}" class="hover:text-blue-800">View</a>
                                            @if(!$death->pig->sales()->exists())
                                                <a href="{{ route('pigs.sale-form', $death->pig->id) }}" 
                                                   class="text-green-600 hover:text-green-800 font-medium">Sell</a>
                                            @else
                                                <span class="text-gray-400">Sold</span>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-6 py-12 text-center text-gray-500">No adult death records found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if(isset($deaths) && $deaths->hasPages())
                    <div class="px-6 py-4 border-t border-gray-200">
                        {{ $deaths->links() }}
                    </div>
                @endif
            </div>

            <!-- Piglet Mortality Table (Pig model) -->
            <!-- <div class="bg-white rounded-lg shadow border border-gray-200">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">Piglet Mortality (Under 60 days)</h2>
                    <p class="text-sm text-gray-600 mt-1">Deaths at birth, during weaning, and early life stages</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pig</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Age at Death</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cause</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Weight</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($pigletDeaths ?? [] as $death)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">{{ $death->pig->code ?? 'N/A' }}</div>
                                        <div class="text-sm text-gray-500">{{ $death->pig->ear_tag_number ?? '' }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        @if($death->age_at_death)
                                            {{ $death->age_at_death_display }}
                                        @else
                                            <span class="text-gray-400">Unknown</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $death->death_date->format('M d, Y') }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $death->death_cause_display }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        @if($death->final_weight)
                                            {{ number_format($death->final_weight, 2) }} kg
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-blue-600">
                                        <a href="{{ route('deaths.show', $death->id) }}" class="hover:text-blue-800">View</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-6 py-12 text-center text-gray-500">No piglet mortality records found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if(isset($pigletDeaths) && $pigletDeaths->hasPages())
                    <div class="px-6 py-4 border-t border-gray-200">
                        {{ $pigletDeaths->links() }}
                    </div>
                @endif
            </div> -->

            <!-- Piglet Death Events (Farrowing-based records) -->
            <div class="bg-white rounded-lg shadow border border-gray-200 mt-6">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">Piglet Death Events (Litters)</h2>
                    <p class="text-sm text-gray-600 mt-1">Events recorded against farrowings</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sow</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Count</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cause</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Notes</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($pigletDeathEvents ?? [] as $event)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">{{ optional(optional($event->farrowing)->sow)->code ?? 'N/A' }}</div>
                                        <div class="text-sm text-gray-500">{{ optional(optional($event->farrowing)->sow)->ear_tag_number ?? '' }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ \Carbon\Carbon::parse($event->death_date)->format('M d, Y') }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-red-600 font-semibold">{{ $event->count }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $event->cause ?? '—' }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $event->notes ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-12 text-center text-gray-500">No piglet death events recorded.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if(isset($pigletDeathEvents) && $pigletDeathEvents->hasPages())
                    <div class="px-6 py-4 border-t border-gray-200">
                        {{ $pigletDeathEvents->links() }}
                    </div>
                @endif
            </div>
        </main>
        <x-copyright-minimal />
    </div>
</x-app-layout>


