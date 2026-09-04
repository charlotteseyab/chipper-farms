<x-app-layout>
    <div class="min-h-screen bg-gray-50">
        <div class="bg-white shadow-sm border-b border-gray-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">Mortality Records</h1>
                        <p class="text-gray-600 mt-1">Recorded animal losses, causes and disposal details</p>
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
                        <a href="{{ route('all-stocks.index') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Record Mortality</a>
                        <a href="{{ route('audit-trail.index', ['tab' => 'archived']) }}" class="inline-flex items-center px-4 py-2 bg-gray-900 text-white rounded-lg hover:bg-black/80">
                            Archived Records
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                <div class="bg-white rounded-lg shadow border border-gray-200 p-6">
                    <div class="text-sm text-gray-600">Total Mortality</div>
                    <div class="text-2xl font-semibold text-gray-900">{{ $stats['total_deaths'] ?? 0 }}</div>
                    <div class="text-xs text-gray-500 mt-1">
                        {{ $stats['total_adult_deaths'] ?? 0 }} pigs • {{ $stats['total_piglet_deaths'] ?? 0 }} piglets
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow border border-gray-200 p-6">
                    <div class="text-sm text-gray-600">Adult Mortality</div>
                    <div class="text-2xl font-semibold text-gray-900">{{ $stats['total_adult_deaths'] ?? 0 }}</div>
                </div>
                <div class="bg-white rounded-lg shadow border border-gray-200 p-6">
                    <div class="text-sm text-gray-600">Piglet Mortality</div>
                    <div class="text-2xl font-semibold text-red-600">{{ $stats['total_piglet_deaths'] ?? 0 }}</div>
                </div>
                <div class="bg-white rounded-lg shadow border border-gray-200 p-6">
                    <div class="text-sm text-gray-600">Last 30 Days</div>
                    <div class="text-2xl font-semibold text-gray-900">{{ $stats['recent_deaths'] ?? 0 }}</div>
                </div>
            </div>

            <div data-ajax-table>
                <div class="bg-white rounded-lg shadow border border-gray-200 mb-6">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h2 class="text-lg font-semibold text-gray-900">Filter Mortality Records</h2>
                        <p class="text-sm text-gray-500 mt-1">Applies to adult and piglet mortality records.</p>
                    </div>
                    <form method="GET" action="{{ route('deaths.index') }}" data-ajax-table-form class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Cause</label>
                                <input type="text" name="death_cause" value="{{ request('death_cause') }}" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500" placeholder="Cause">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Date From</label>
                                <input type="date" name="date_from" value="{{ request('date_from') }}" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Date To</label>
                                <input type="date" name="date_to" value="{{ request('date_to') }}" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            <div class="flex items-end gap-2">
                                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">Filter</button>
                                <a href="{{ route('deaths.index') }}" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition">Clear</a>
                            </div>
                        </div>
                    </form>
                </div>

                <div data-ajax-table-loading hidden class="mb-3 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-600">
                    Updating table...
                </div>

                <div data-ajax-table-error hidden class="mb-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700"></div>

                <div data-ajax-table-target>
                    @include('deaths._tables', ['deaths' => $deaths, 'pigletDeaths' => $pigletDeaths, 'pigletDeathEvents' => $pigletDeathEvents])
                </div>
            </div>
        </main>
    </div>
</x-app-layout>
