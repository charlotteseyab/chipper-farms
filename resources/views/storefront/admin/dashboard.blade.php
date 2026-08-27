<x-app-layout>
    @php
        $statusStyles = [
            'published' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/15',
            'draft' => 'bg-amber-50 text-amber-700 ring-amber-600/15',
            'unpublished' => 'bg-slate-100 text-slate-600 ring-slate-500/15',
            'sold_out' => 'bg-rose-50 text-rose-700 ring-rose-600/15',
            'archived' => 'bg-slate-100 text-slate-500 ring-slate-500/15',
        ];
        $statCards = [
            ['key' => 'total', 'label' => 'All listings', 'icon' => 'fa-layer-group', 'color' => 'bg-slate-100 text-slate-700'],
            ['key' => 'published', 'label' => 'Published', 'icon' => 'fa-circle-check', 'color' => 'bg-emerald-100 text-emerald-700'],
            ['key' => 'draft', 'label' => 'Drafts', 'icon' => 'fa-pen-ruler', 'color' => 'bg-amber-100 text-amber-700'],
            ['key' => 'unpublished', 'label' => 'Unpublished', 'icon' => 'fa-eye-slash', 'color' => 'bg-blue-100 text-blue-700'],
            ['key' => 'sold_out', 'label' => 'Sold out', 'icon' => 'fa-box-open', 'color' => 'bg-rose-100 text-rose-700'],
        ];
    @endphp

    <div class="mx-auto w-full max-w-7xl py-4 sm:py-6">
        <section class="overflow-hidden rounded-3xl bg-gradient-to-br from-[#124f3b] via-[#176c50] to-[#218563] text-white shadow-lg shadow-emerald-950/10">
            <div class="flex flex-col gap-6 px-5 py-6 sm:px-7 sm:py-8 lg:flex-row lg:items-end lg:justify-between">
                <div class="max-w-2xl">
                    <p class="text-xs font-bold uppercase tracking-[0.2em] text-emerald-100">Storefront workspace</p>
                    <h1 class="mt-2 text-2xl font-extrabold tracking-tight sm:text-3xl">Manage your online shop</h1>
                    <p class="mt-2 max-w-xl text-sm leading-6 text-emerald-50/85 sm:text-base">
                        Publish products, manage customer orders, review quotes and keep your storefront ready for buyers.
                    </p>
                </div>

                <div class="grid w-full grid-cols-1 gap-2 sm:w-auto sm:grid-cols-2">
                    @can('push_to_storefront')
                        <a href="{{ route('storefront.admin.push.create') }}" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-bold text-[#176c50] shadow-sm transition hover:bg-emerald-50">
                            <i class="fa-solid fa-plus" aria-hidden="true"></i>
                            Add a listing
                        </a>
                    @endcan
                    <a href="{{ route('storefront.home') }}" target="_blank" rel="noopener" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-white/25 bg-white/10 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-white/20">
                        <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>
                        View public shop
                    </a>
                </div>
            </div>
        </section>

        @if(session('success'))
            <div class="mt-5 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="mt-5 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ session('error') }}</div>
        @endif

        <section aria-labelledby="storefront-overview-heading" class="mt-6">
            <div class="mb-3 flex items-center justify-between gap-3">
                <div>
                    <h2 id="storefront-overview-heading" class="text-lg font-bold text-slate-900">Listing overview</h2>
                    <p class="mt-0.5 text-sm text-slate-500">A quick view of your current catalogue.</p>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-5">
                @foreach($statCards as $card)
                    <div class="min-w-0 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $card['label'] }}</p>
                                <p class="mt-2 text-2xl font-extrabold text-slate-900 sm:text-3xl">{{ $stats[$card['key']] }}</p>
                            </div>
                            <span class="hidden h-10 w-10 shrink-0 items-center justify-center rounded-xl sm:inline-flex {{ $card['color'] }}">
                                <i class="fa-solid {{ $card['icon'] }}" aria-hidden="true"></i>
                            </span>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        <section aria-labelledby="storefront-actions-heading" class="mt-7">
            <h2 id="storefront-actions-heading" class="text-lg font-bold text-slate-900">Quick actions</h2>
            <div class="mt-3 grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
                @can('view_storefront_orders')
                    <a href="{{ route('storefront.admin.orders.index') }}" class="group rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:border-emerald-200 hover:shadow-md">
                        <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700"><i class="fa-solid fa-bag-shopping"></i></span>
                        <span class="mt-3 block text-sm font-bold text-slate-800">Orders</span>
                    </a>
                @endcan
                @can('view_storefront_quotes')
                    <a href="{{ route('storefront.admin.quotes.index') }}" class="group rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:border-emerald-200 hover:shadow-md">
                        <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-blue-50 text-blue-700"><i class="fa-solid fa-file-invoice"></i></span>
                        <span class="mt-3 block text-sm font-bold text-slate-800">Quotes</span>
                    </a>
                @endcan
                <a href="{{ route('storefront.admin.listings.index') }}" class="group rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:border-emerald-200 hover:shadow-md">
                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-violet-50 text-violet-700"><i class="fa-solid fa-list"></i></span>
                    <span class="mt-3 block text-sm font-bold text-slate-800">Listings</span>
                </a>
                @can('view_storefront_reports')
                    <a href="{{ route('storefront.admin.reports.sales') }}" class="group rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:border-emerald-200 hover:shadow-md">
                        <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-amber-50 text-amber-700"><i class="fa-solid fa-chart-line"></i></span>
                        <span class="mt-3 block text-sm font-bold text-slate-800">Sales report</span>
                    </a>
                @endcan
                @can('manage_storefront_settings')
                    <a href="{{ route('storefront.admin.settings.index') }}" class="group rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:border-emerald-200 hover:shadow-md">
                        <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-slate-100 text-slate-700"><i class="fa-solid fa-gear"></i></span>
                        <span class="mt-3 block text-sm font-bold text-slate-800">Shop settings</span>
                    </a>
                @endcan
                @can('manage_storefront_placements')
                    <a href="{{ route('storefront.admin.placements.index') }}" class="group rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:border-emerald-200 hover:shadow-md">
                        <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-rose-50 text-rose-700"><i class="fa-solid fa-thumbtack"></i></span>
                        <span class="mt-3 block text-sm font-bold text-slate-800">Placements</span>
                    </a>
                @endcan
            </div>
        </section>

        <section aria-labelledby="recent-listings-heading" class="mt-7 overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between gap-4 border-b border-slate-100 px-4 py-4 sm:px-6">
                <div>
                    <h2 id="recent-listings-heading" class="font-bold text-slate-900">Recent listings</h2>
                    <p class="mt-0.5 text-xs text-slate-500">The latest products added to your catalogue.</p>
                </div>
                <a href="{{ route('storefront.admin.listings.index') }}" class="shrink-0 text-sm font-bold text-emerald-700 hover:text-emerald-800">View all <span aria-hidden="true">→</span></a>
            </div>

            <div class="divide-y divide-slate-100 md:hidden">
                @forelse($recent as $listing)
                    <a href="{{ route('storefront.admin.listings.show', $listing) }}" class="block p-4 transition hover:bg-slate-50">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate font-bold text-slate-900">{{ $listing->title }}</p>
                                <p class="mt-1 font-mono text-xs text-slate-500">{{ $listing->reference }}</p>
                            </div>
                            <span class="shrink-0 rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1 ring-inset {{ $statusStyles[$listing->status] ?? 'bg-slate-100 text-slate-600 ring-slate-500/15' }}">{{ str_replace('_', ' ', $listing->status) }}</span>
                        </div>
                        <p class="mt-3 text-xs font-semibold uppercase tracking-wide text-slate-500">{{ str_replace('_', ' ', $listing->kind) }}</p>
                    </a>
                @empty
                    <div class="px-5 py-10 text-center text-sm text-slate-500">No listings yet. Add a pig or product to get started.</div>
                @endforelse
            </div>

            <div class="hidden overflow-x-auto md:block" data-responsive-table>
                <table class="w-full min-w-[680px] text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-6 py-3">Reference</th>
                            <th class="px-6 py-3">Listing</th>
                            <th class="px-6 py-3">Type</th>
                            <th class="px-6 py-3">Status</th>
                            <th class="px-6 py-3"><span class="sr-only">Open</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($recent as $listing)
                            <tr class="transition hover:bg-slate-50">
                                <td class="whitespace-nowrap px-6 py-4 font-mono text-xs text-slate-500">{{ $listing->reference }}</td>
                                <td class="px-6 py-4 font-bold text-slate-900">{{ $listing->title }}</td>
                                <td class="px-6 py-4 capitalize text-slate-500">{{ str_replace('_', ' ', $listing->kind) }}</td>
                                <td class="px-6 py-4"><span class="rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset {{ $statusStyles[$listing->status] ?? 'bg-slate-100 text-slate-600 ring-slate-500/15' }}">{{ str_replace('_', ' ', $listing->status) }}</span></td>
                                <td class="px-6 py-4 text-right"><a href="{{ route('storefront.admin.listings.show', $listing) }}" class="font-bold text-emerald-700 hover:text-emerald-800">View</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-6 py-10 text-center text-slate-500">No listings yet. Add a pig or product to get started.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-app-layout>
