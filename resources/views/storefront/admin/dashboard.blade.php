<x-app-layout>
    <div class="max-w-6xl mx-auto py-6 px-4">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Storefront</h1>
                <p class="text-sm text-gray-500">Manage listings, placements and (from Phase 3) orders.</p>
            </div>
            <div class="flex gap-2">
                @can('view_storefront_quotes')
                    <a href="{{ route('storefront.admin.quotes.index') }}" class="px-4 py-2 border border-slate-300 rounded-xl text-sm">Quotes</a>
                @endcan
                @can('view_storefront_orders')
                    <a href="{{ route('storefront.admin.orders.index') }}" class="px-4 py-2 border border-slate-300 rounded-xl text-sm">Orders</a>
                @endcan
                @can('push_to_storefront')
                    <a href="{{ route('storefront.admin.push.create') }}" class="px-4 py-2 bg-slate-800 text-white rounded-xl text-sm">Push to storefront</a>
                @endcan
                <a href="{{ route('storefront.home') }}" target="_blank" class="px-4 py-2 border border-slate-300 rounded-xl text-sm">View shop ↗</a>
            </div>
        </div>

        @if(session('success'))<div class="mb-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 text-sm">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="mb-4 rounded-xl bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">{{ session('error') }}</div>@endif

        <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-8">
            @foreach(['total' => 'Total', 'published' => 'Published', 'draft' => 'Drafts', 'unpublished' => 'Unpublished', 'sold_out' => 'Sold out'] as $key => $label)
                <div class="rounded-2xl bg-white border border-slate-200 p-5">
                    <p class="text-xs uppercase tracking-wide text-slate-400">{{ $label }}</p>
                    <p class="text-2xl font-bold text-slate-800 mt-1">{{ $stats[$key] }}</p>
                </div>
            @endforeach
        </div>

        <div class="rounded-2xl bg-white border border-slate-200 overflow-hidden">
            <div class="px-5 py-3 border-b border-slate-100 flex items-center justify-between">
                <h2 class="font-semibold text-slate-700">Recent listings</h2>
                <a href="{{ route('storefront.admin.listings.index') }}" class="text-sm text-slate-500 hover:text-slate-800">View all</a>
            </div>
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 text-left">
                    <tr><th class="px-5 py-2">Ref</th><th class="px-5 py-2">Title</th><th class="px-5 py-2">Kind</th><th class="px-5 py-2">Status</th></tr>
                </thead>
                <tbody>
                    @forelse($recent as $listing)
                        <tr class="border-t border-slate-100">
                            <td class="px-5 py-2 font-mono text-xs">{{ $listing->reference }}</td>
                            <td class="px-5 py-2"><a href="{{ route('storefront.admin.listings.show', $listing) }}" class="text-slate-800 hover:underline">{{ $listing->title }}</a></td>
                            <td class="px-5 py-2 text-slate-500">{{ str_replace('_', ' ', $listing->kind) }}</td>
                            <td class="px-5 py-2"><span class="px-2 py-0.5 rounded-full text-xs bg-slate-100 text-slate-600">{{ $listing->status }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-5 py-8 text-center text-slate-400">No listings yet. Push a pig or product to get started.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
