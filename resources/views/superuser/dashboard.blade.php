<x-app-layout>
    <div class="py-2">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            @php

                $pendingRequests = \App\Models\FeedPurchaseRequest::where('status', 'pending')
                    ->orderByDesc('priority')
                    ->orderBy('needed_by')
                    ->take(6)
                    ->get();

                $pendingRequestCount = \App\Models\FeedPurchaseRequest::where('status', 'pending')->count();
                $pendingRequestValue = \App\Models\FeedPurchaseRequest::where('status', 'pending')->sum('cost_ghc');

                $recentApprovals = \App\Models\FeedPurchaseRequest::where('status', 'approved')
                    ->latest('decided_at')
                    ->latest('updated_at')
                    ->take(5)
                    ->get();

                $inventory = \App\Models\FeedInventory::with('feedType')->get();
                $lowStockFeeds = $inventory->filter(function ($item) {
                    $unit = strtolower($item->unit ?? 'kg');
                    if (str_contains($unit, 'bag')) {
                        return $item->quantity < 5;
                    }
                    return $item->quantity < 50;
                })->sortBy('quantity')->values();
                $lowStockCount = $lowStockFeeds->count();

                $overdueWeighings = \App\Models\PigManagement::countOverdueForWeighing();
                $dueWeighings = \App\Models\PigManagement::countDueForWeighing();

                $weaningAge = config('breeding.cycle.birth_to_weaning_days', 45);
                $readyToWean = \App\Models\Farrowing::where('status', 'active')
                    ->whereDate('farrowing_date', '<=', now()->subDays($weaningAge))
                    ->whereDoesntHave('weanings', function ($query) {
                        $query->where('maturity_completed', true);
                    })
                    ->count();

                $weightAlerts = \App\Models\WeightAlert::where('acknowledged', false)->latest()->take(5)->get();

                $user = auth()->user();
                $recentNotifications = $user
                    ? $user->notifications()->latest()->take(6)->get()
                    : collect();
                $unreadNotifications = $user ? $user->unreadNotifications()->count() : 0;
            @endphp

            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900">Superuser Command Center</h1>
                <p class="text-gray-600 mt-2">Approve procurements, monitor stock exposure, and keep the farm moving.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6 mb-10">
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 flex items-start justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500 uppercase tracking-wide">Pending Approvals</p>
                        <p class="text-3xl font-bold text-gray-900 mt-1">{{ number_format($pendingRequestCount) }}</p>
                        <p class="text-xs text-gray-500 mt-1">₵{{ number_format($pendingRequestValue, 2) }} outstanding</p>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                    </div>
                </div>

                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 flex items-start justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500 uppercase tracking-wide">Low Stock Items</p>
                        <p class="text-3xl font-bold text-gray-900 mt-1">{{ number_format($lowStockCount) }}</p>
                        <p class="text-xs text-gray-500 mt-1">Trigger &lt;5 bags or &lt;50kg</p>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M12 2a10 10 0 110 20 10 10 0 010-20z"/>
                        </svg>
                    </div>
                </div>

                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 flex items-start justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500 uppercase tracking-wide">Weighing Alerts</p>
                        <p class="text-3xl font-bold text-gray-900 mt-1">{{ number_format($overdueWeighings) }}</p>
                        <p class="text-xs text-gray-500 mt-1">{{ number_format($dueWeighings) }} due within window</p>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                </div>

                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 flex items-start justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500 uppercase tracking-wide">Ready to Wean</p>
                        <p class="text-3xl font-bold text-gray-900 mt-1">{{ number_format($readyToWean) }}</p>
                        <p class="text-xs text-gray-500 mt-1">Litters beyond {{ $weaningAge }} days</p>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mb-10">
                <div class="xl:col-span-2 bg-white rounded-2xl border border-gray-100 shadow-sm">
                    <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900">Pending Purchase Requests</h2>
                            <p class="text-sm text-gray-500">Top priorities waiting for superuser approval.</p>
                        </div>
                        <a href="{{ route('feed-purchase-requests.index') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">View all</a>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left font-semibold text-gray-600 uppercase tracking-wide text-xs">Item & Category</th>
                                    <th class="px-6 py-3 text-left font-semibold text-gray-600 uppercase tracking-wide text-xs">Qty</th>
                                    <th class="px-6 py-3 text-left font-semibold text-gray-600 uppercase tracking-wide text-xs">Needed</th>
                                    <th class="px-6 py-3 text-left font-semibold text-gray-600 uppercase tracking-wide text-xs">Priority</th>
                                    <th class="px-6 py-3 text-left font-semibold text-gray-600 uppercase tracking-wide text-xs">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @forelse($pendingRequests as $request)
                                    <tr class="hover:bg-gray-50 transition">
                                        <td class="px-6 py-4">
                                            <div class="font-semibold text-gray-900">{{ $request->item_name ?? $request->feed_type ?? 'N/A' }}</div>
                                            <div class="text-xs text-gray-500 mt-1">Requested by {{ optional($request->requestedBy)->name ?? 'Unknown' }}</div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <p class="font-semibold text-gray-800">{{ number_format($request->quantity, 2) }} {{ $request->unit }}</p>
                                            <p class="text-xs text-gray-500">₵{{ number_format($request->cost_ghc ?? 0, 2) }}</p>
                                        </td>
                                        <td class="px-6 py-4">
                                            <p class="font-semibold text-gray-800">
                                                {{ optional($request->needed_by)->format('M j') ?? 'Flexible' }}
                                            </p>
                                            <p class="text-xs text-gray-500">{{ $request->created_at->diffForHumans() }}</p>
                                        </td>
                                        <td class="px-6 py-4">
                                            @php
                                                $priorityColor = match($request->priority) {
                                                    'critical' => 'bg-rose-100 text-rose-700',
                                                    'high' => 'bg-amber-100 text-amber-700',
                                                    'medium' => 'bg-blue-100 text-blue-700',
                                                    default => 'bg-gray-100 text-gray-600',
                                                };
                                            @endphp
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold {{ $priorityColor }}">
                                                {{ ucfirst($request->priority ?? 'medium') }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <a href="{{ route('feed-purchase-requests.show', $request) }}" class="inline-flex items-center px-3 py-1.5 border border-indigo-200 text-indigo-700 rounded-full text-xs font-semibold hover:bg-indigo-50">
                                                Review
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-6 py-6 text-center text-sm text-gray-500">
                                            All caught up — no pending requests today.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm flex flex-col">
                    <div class="border-b border-gray-100 px-6 py-4">
                        <h2 class="text-lg font-semibold text-gray-900">Notifications & Approvals</h2>
                        <p class="text-sm text-gray-500">{{ number_format($unreadNotifications) }} unread updates</p>
                    </div>
                    <div class="px-6 py-4 space-y-4 flex-1 overflow-y-auto">
                        @forelse($recentNotifications as $notification)
                            <div class="flex items-start gap-3">
                                <div class="w-10 h-10 rounded-full bg-indigo-50 text-indigo-600 flex items-center justify-center">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M12 2a10 10 0 110 20 10 10 0 010-20z"/>
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-sm font-semibold text-gray-900">{{ $notification->data['title'] ?? 'System Update' }}</p>
                                    <p class="text-xs text-gray-500">{{ $notification->data['body'] ?? 'No details provided.' }}</p>
                                    <p class="text-xs text-gray-400 mt-1">{{ $notification->created_at->diffForHumans() }}</p>
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-gray-500">Nothing new in the last few hours.</p>
                        @endforelse
                    </div>

                    <div class="border-t border-gray-100 px-6 py-4">
                        <h3 class="text-sm font-semibold text-gray-900 mb-3">Recent Approvals</h3>
                        <div class="space-y-3">
                            @forelse($recentApprovals as $approval)
                                <div class="flex items-start gap-3">
                                    <div class="w-2 h-2 rounded-full bg-emerald-500 mt-2"></div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-900">{{ $approval->item_name ?? $approval->feed_type ?? 'Feed Request' }}</p>
                                        <p class="text-xs text-gray-500">₵{{ number_format($approval->cost_ghc ?? 0, 2) }} • {{ optional($approval->decider)->name ?? 'System' }}</p>
                                        <p class="text-xs text-gray-400">{{ optional($approval->decided_at ?? $approval->updated_at)->diffForHumans() }}</p>
                                    </div>
                                </div>
                            @empty
                                <p class="text-sm text-gray-500">No approvals logged yet.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm">
                    <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900">Feed Inventory Watchlist</h2>
                            <p class="text-sm text-gray-500">Items below operational thresholds.</p>
                        </div>
                        <a href="{{ route('feeds.index') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">View inventory</a>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left font-semibold text-gray-600 uppercase tracking-wide text-xs">Feed Type</th>
                                    <th class="px-6 py-3 text-left font-semibold text-gray-600 uppercase tracking-wide text-xs">Quantity</th>
                                    <th class="px-6 py-3 text-left font-semibold text-gray-600 uppercase tracking-wide text-xs">Last Updated</th>
                                    <th class="px-6 py-3 text-left font-semibold text-gray-600 uppercase tracking-wide text-xs">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @forelse($lowStockFeeds->take(6) as $inventory)
                                    <tr class="hover:bg-gray-50 transition">
                                        <td class="px-6 py-4">
                                            <p class="font-semibold text-gray-900">{{ optional($inventory->feedType)->name ?? 'Unnamed Feed' }}</p>
                                            <p class="text-xs text-gray-500">{{ strtoupper($inventory->unit) }}</p>
                                        </td>
                                        <td class="px-6 py-4 text-gray-900 font-semibold">{{ number_format($inventory->quantity, 1) }}</td>
                                        <td class="px-6 py-4 text-gray-600">
                                            {{ optional($inventory->last_updated)->format('M j, Y') ?? 'Unknown' }}
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-rose-100 text-rose-700">
                                                Refill Soon
                                            </span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="px-6 py-6 text-center text-sm text-gray-500">
                                            All feed items are above the alert threshold.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm">
                    <div class="border-b border-gray-100 px-6 py-4">
                        <h2 class="text-lg font-semibold text-gray-900">Alert Center</h2>
                        <p class="text-sm text-gray-500">Critical items requiring leadership attention.</p>
                    </div>
                    <div class="divide-y divide-gray-100">
                        <div class="px-6 py-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-semibold text-gray-900">Weight Alerts</p>
                                    <p class="text-xs text-gray-500">Unacknowledged deviations from expected weight</p>
                                </div>
                                <a href="{{ route('weight-alerts.dashboard') }}" class="text-xs font-semibold text-indigo-600 hover:text-indigo-500">Open dashboard</a>
                            </div>
                            <div class="mt-4 space-y-3">
                                @forelse($weightAlerts as $alert)
                                    <div class="flex items-start justify-between">
                                        <div>
                                            <p class="text-sm font-medium text-gray-900">
                                                {{ optional($alert->pig)->ear_tag_number ?? 'Unknown Pig' }}
                                            </p>
                                            <p class="text-xs text-gray-500">{{ $alert->alert_message ?? ucfirst($alert->alert_type) }}</p>
                                            <p class="text-xs text-gray-400 mt-1">{{ $alert->created_at->diffForHumans() }}</p>
                                        </div>
                                        @php
                                            $badgeColor = match($alert->severity) {
                                                'high' => 'bg-rose-100 text-rose-700',
                                                'medium' => 'bg-amber-100 text-amber-700',
                                                default => 'bg-blue-100 text-blue-700',
                                            };
                                        @endphp
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $badgeColor }}">
                                            {{ ucfirst($alert->severity) }}
                                        </span>
                                    </div>
                                @empty
                                    <p class="text-sm text-gray-500">No open weight alerts.</p>
                                @endforelse
                            </div>
                        </div>

                        <div class="px-6 py-4 bg-gray-50">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-semibold text-gray-900">Production Milestones</p>
                                    <p class="text-xs text-gray-500">Track key events that drive revenue</p>
                                </div>
                                <a href="{{ route('weaning.dashboard') }}" class="text-xs font-semibold text-indigo-600 hover:text-indigo-500">Manage weaning</a>
                            </div>
                            <dl class="mt-4 grid grid-cols-2 gap-4">
                                <div class="bg-white rounded-xl border border-gray-100 p-3">
                                    <dt class="text-xs uppercase text-gray-500 tracking-wide">Ready to Wean</dt>
                                    <dd class="text-xl font-bold text-gray-900">{{ number_format($readyToWean) }}</dd>
                                </div>
                                <div class="bg-white rounded-xl border border-gray-100 p-3">
                                    <dt class="text-xs uppercase text-gray-500 tracking-wide">Due for Weighing</dt>
                                    <dd class="text-xl font-bold text-gray-900">{{ number_format($dueWeighings) }}</dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
    <x-copyright-minimal />
</x-app-layout>
