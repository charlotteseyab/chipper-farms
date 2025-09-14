<x-app-layout>
    <div class="min-h-screen bg-gray-50">
        <!-- Header -->
        <div class="bg-white shadow-sm border-b border-gray-200">
            <div class="max-w-8xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
                <div class="flex items-center space-x-4">
                    <a href="{{ route('feeds.index') }}" class="text-gray-500 hover:text-gray-700">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                    </a>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">Feed Schedule</h1>
                        <p class="text-gray-500">View and manage feeding schedules for pigs</p>
                    </div>
                </div>
            </div>
        </div>

        <main class="max-w-8xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <!-- Feed Schedule Overview -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                <!-- Today's Feeds -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">Today's Feeds</h3>
                    </div>
                    <div class="p-6">
                        <div class="text-3xl font-bold text-green-600">
                            {{ \App\Models\Feed::whereDate('feed_date', today())->count() }}
                        </div>
                        <p class="text-sm text-gray-500 mt-1">Feed records today</p>
                    </div>
                </div>

                <!-- Overdue Feeds -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">Pending Feeds</h3>
                    </div>
                    <div class="p-6">
                        <div class="text-3xl font-bold text-orange-600">
                            {{ $pigs->where('stock_type', '!=', 'breeding')->count() }}
                        </div>
                        <p class="text-sm text-gray-500 mt-1">Pigs needing feeds</p>
                    </div>
                </div>

                <!-- Total Feed Cost Today -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">Today's Feed Cost</h3>
                    </div>
                    <div class="p-6">
                        <div class="text-3xl font-bold text-blue-600">
                            ${{ number_format(\App\Models\Feed::whereDate('feed_date', today())->sum('feed_cost') ?? 0, 2) }}
                        </div>
                        <p class="text-sm text-gray-500 mt-1">Total cost today</p>
                    </div>
                </div>
            </div>

            <!-- Feed Schedule by Pig Type -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-6">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Feed Schedule by Stock Type</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stock Type</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Recommended Feed Type</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Daily Amount</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Frequency</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Active Pigs</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                        Weaner
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">Starter Feed</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">0.5 - 1.0 kg</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">3 times daily</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ $pigs->where('stock_type', 'weaner')->count() }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <a href="{{ route('feeds.create') }}?stock_type=weaner" class="text-green-600 hover:text-green-900">Quick Feed</a>
                                </td>
                            </tr>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        Grower
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">Grower Feed</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">1.5 - 2.5 kg</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">2 times daily</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ $pigs->where('stock_type', 'grower')->count() }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <a href="{{ route('feeds.create') }}?stock_type=grower" class="text-green-600 hover:text-green-900">Quick Feed</a>
                                </td>
                            </tr>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-800">
                                        Finisher
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">Finisher Feed</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">2.5 - 3.5 kg</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">2 times daily</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ $pigs->where('stock_type', 'finisher')->count() }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <a href="{{ route('feeds.create') }}?stock_type=finisher" class="text-green-600 hover:text-green-900">Quick Feed</a>
                                </td>
                            </tr>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                                        Breeding
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">Breeding Feed</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">2.0 - 3.0 kg</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">2 times daily</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ $pigs->where('stock_type', 'breeding')->count() }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <a href="{{ route('feeds.create') }}?stock_type=breeding" class="text-green-600 hover:text-green-900">Quick Feed</a>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Recent Feed Activity -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                <div class="px-6 py-4 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-medium text-gray-900">Recent Feed Activity</h3>
                        <a href="{{ route('feeds.index') }}" class="text-sm text-green-600 hover:text-green-800">View All</a>
                    </div>
                </div>
                <div class="p-6">
                    @php
                        $recentFeeds = \App\Models\Feed::with(['pig', 'recorder'])
                            ->orderBy('feed_date', 'desc')
                            ->orderBy('feed_time', 'desc')
                            ->limit(10)
                            ->get();
                    @endphp
                    
                    @if($recentFeeds->count() > 0)
                        <div class="space-y-4">
                            @foreach($recentFeeds as $feed)
                                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                                    <div class="flex items-center space-x-4">
                                        <div class="flex-shrink-0">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $feed->feed_type_badge }}">
                                                {{ $feed->feed_type_display }}
                                            </span>
                                        </div>
                                        <div>
                                            <div class="text-sm font-medium text-gray-900">
                                                <a href="{{ route('pigs.show', $feed->pig_id) }}" class="text-green-600 hover:text-green-800">
                                                    {{ $feed->pig->ear_tag_number }}
                                                </a>
                                                - {{ $feed->formatted_amount }}
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                {{ $feed->feed_date->format('M d, Y') }}
                                                @if($feed->feed_time)
                                                    at {{ $feed->feed_time->format('g:i A') }}
                                                @endif
                                                by {{ $feed->recorder->name }}
                                            </div>
                                        </div>
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        {{ $feed->formatted_cost }}
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-8">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900">No recent feed activity</h3>
                            <p class="mt-1 text-sm text-gray-500">Start recording feeds to see activity here.</p>
                        </div>
                    @endif
                </div>
            </div>
        </main>
    </div>
</x-app-layout>
