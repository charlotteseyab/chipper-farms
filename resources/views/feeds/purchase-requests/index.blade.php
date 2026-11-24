<x-app-layout>
    <x-feed-hub hub="store" />
    @php
        $pendingCount = $stats['pending'] ?? 0;
        $approvedCount = $stats['approved'] ?? 0;
        $paidCount = $stats['paid'] ?? 0;
        $declinedCount = $stats['declined'] ?? 0;

        $statusClasses = [
            'pending' => 'bg-blue-100 text-blue-800 border border-blue-200',
            'approved' => 'bg-green-100 text-green-800 border border-green-200',
            'paid' => 'bg-emerald-100 text-emerald-800 border border-emerald-200',
            'declined' => 'bg-red-100 text-red-800 border border-red-200',
            'draft' => 'bg-amber-100 text-amber-800 border border-amber-200',
        ];
    @endphp

    <div class="max-w-7xl mx-auto py-6 px-4">
        <div class="mb-6 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Purchase Requests</h1>
                <p class="text-sm text-gray-500">Manage and monitor all purchase requests from one place.</p>
            </div>

            <a href="{{ route('purchase-requests.create') }}"
               class="inline-flex items-center justify-center px-4 py-2.5 bg-green-600 hover:bg-green-700 text-white text-sm font-semibold rounded-xl shadow-sm transition">
                + Create Request
            </a>
        </div>
        
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
            <div class="rounded-2xl border border-blue-100 bg-white shadow-sm p-5">
                <p class="text-sm font-medium text-gray-500">Pending Requests</p>
                <h3 class="mt-2 text-3xl font-bold text-blue-700">{{ $pendingCount }}</h3>
                <p class="mt-1 text-xs text-gray-400">Awaiting approval or decline</p>
            </div>

            <div class="rounded-2xl border border-green-100 bg-white shadow-sm p-5">
                <p class="text-sm font-medium text-gray-500">Approved Requests</p>
                <h3 class="mt-2 text-3xl font-bold text-green-700">{{ $approvedCount }}</h3>
                <p class="mt-1 text-xs text-gray-400">Approved and not yet paid</p>
            </div>

            <div class="rounded-2xl border border-emerald-100 bg-white shadow-sm p-5">
                <p class="text-sm font-medium text-gray-500">Paid Requests</p>
                <h3 class="mt-2 text-3xl font-bold text-emerald-700">{{ $paidCount }}</h3>
                <p class="mt-1 text-xs text-gray-400">Completed payments</p>
            </div>

            <div class="rounded-2xl border border-red-100 bg-white shadow-sm p-5">
                <p class="text-sm font-medium text-gray-500">Declined Requests</p>
                <h3 class="mt-2 text-3xl font-bold text-red-700">{{ $declinedCount }}</h3>
                <p class="mt-1 text-xs text-gray-400">Requests not approved</p>
            </div>
        </div>

        <form method="GET" action="{{ route('purchase-requests.index') }}" class="mb-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                    <input type="text"
                        name="search"
                        value="{{ $search }}"
                        placeholder="Search request no, type, purpose, reason or notes"
                        class="w-full rounded-xl border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                    <select name="status"
                            class="w-full rounded-xl border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                        <option value="">All Statuses</option>
                        <option value="pending" {{ $status === 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="approved" {{ $status === 'approved' ? 'selected' : '' }}>Approved</option>
                        <option value="paid" {{ $status === 'paid' ? 'selected' : '' }}>Paid</option>
                        <option value="completed" {{ $status === 'completed' ? 'selected' : '' }}>Completed</option>
                        <option value="declined" {{ $status === 'declined' ? 'selected' : '' }}>Declined</option>
                    </select>
                </div>

                <div class="flex items-end gap-3">
                    <button type="submit"
                            class="inline-flex items-center justify-center px-4 py-2.5 bg-green-600 hover:bg-green-700 text-white text-sm font-semibold rounded-xl shadow-sm transition">
                        Filter
                    </button>

                    <a href="{{ route('purchase-requests.index') }}"
                       class="inline-flex items-center justify-center px-4 py-2.5 bg-gray-200 hover:bg-gray-300 text-gray-800 text-sm font-semibold rounded-xl transition">
                        Reset
                    </a>
                </div>
            </div>
        </form>

        <div class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
            <div class="border-b border-gray-100 px-6 py-4">
                <h2 class="text-base font-semibold text-gray-800">All Purchase Requests</h2>
                <p class="text-sm text-gray-500 mt-1">Review request details, statuses, and total amounts.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Request No</th>
                            <th>Date</th>
                            <th>Requested By</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Total Amount</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse($purchaseRequests as $purchaseRequest)
                            <tr>
                                <td>
                                    <div class="font-semibold text-gray-800">{{ $purchaseRequest->request_no }}</div>
                                </td>

                                <td class="text-sm text-gray-600">
                                    {{ optional($purchaseRequest->request_date)->format('M d, Y') ?? $purchaseRequest->request_date }}
                                </td>

                                <td class="text-sm text-gray-700">
                                    {{ trim(($purchaseRequest->requester->first_name ?? '') . ' ' . ($purchaseRequest->requester->last_name ?? '')) ?: ($purchaseRequest->requester->name ?? 'N/A') }}
                                </td>

                                <td>
                                    <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold {{ ($purchaseRequest->purchase_type ?? 'other') === 'feed' ? 'bg-green-50 text-green-700 border border-green-100' : 'bg-blue-50 text-blue-700 border border-blue-100' }}">
                                        {{ ($purchaseRequest->purchase_type ?? 'other') === 'feed' ? 'Feed' : 'Other' }}
                                    </span>
                                </td>

                                <td>
                                    <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold {{ $statusClasses[$purchaseRequest->status] ?? 'bg-gray-100 text-gray-700 border border-gray-200' }}">
                                        {{ ucfirst($purchaseRequest->status) }}
                                    </span>
                                </td>

                                <td>
                                    <span class="font-semibold text-gray-800">{{ number_format($purchaseRequest->total_amount, 2) }}</span>
                                </td>

                                <td>
                                    <div class="flex flex-wrap gap-2">
                                        <a href="{{ route('purchase-requests.show', $purchaseRequest) }}"
                                           class="inline-flex items-center rounded-lg bg-green-50 px-3 py-1.5 text-sm font-semibold text-green-700 hover:bg-green-100 transition">
                                            View
                                        </a>

                                        @can('update', $purchaseRequest)
                                            <a href="{{ route('purchase-requests.edit', $purchaseRequest) }}"
                                               class="inline-flex items-center rounded-lg bg-blue-50 px-3 py-1.5 text-sm font-semibold text-blue-700 hover:bg-blue-100 transition">
                                                Edit
                                            </a>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center">
                                    <div class="flex flex-col items-center justify-center">
                                        <div class="mb-3 flex h-14 w-14 items-center justify-center rounded-full bg-gray-100 text-gray-400">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6M7 4h10a2 2 0 012 2v12a2 2 0 01-2 2H7a2 2 0 01-2-2V6a2 2 0 012-2z" />
                                            </svg>
                                        </div>
                                        <h3 class="text-base font-semibold text-gray-700">No purchase requests found</h3>
                                        <p class="mt-1 text-sm text-gray-500">Start by creating your first purchase request.</p>
                                        <a href="{{ route('purchase-requests.create') }}"
                                           class="mt-4 inline-flex items-center rounded-xl bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-700 transition">
                                            + Create Request
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-4"><x-pager :paginator="$purchaseRequests" label="purchase requests" /></div>
    </div>
</x-app-layout>
