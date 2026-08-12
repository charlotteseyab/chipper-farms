<x-app-layout>
    @php
        $listingService = app(\App\Storefront\Services\ListingService::class);
        $statusLabel = static fn (string $status): string => ucwords(str_replace('_', ' ', $status));
    @endphp

    <div class="max-w-6xl mx-auto py-6 px-4">
        <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Listings</h1>
                <p class="mt-1 text-sm text-slate-500">Manage what appears in the public store.</p>
            </div>
            @can('push_to_storefront')
                <a href="{{ route('storefront.admin.push.create') }}" class="inline-flex items-center justify-center rounded-xl bg-slate-800 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">
                    Push to storefront
                </a>
            @endcan
        </div>

        @if(session('success'))<div class="mb-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 text-sm">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="mb-4 rounded-xl bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">{{ session('error') }}</div>@endif

        <form method="GET" class="mb-6 grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 md:grid-cols-[minmax(0,1fr)_180px_180px_auto_auto]">
            <input type="text" name="q" value="{{ $filters['q'] }}" placeholder="Search ref or title..." class="rounded-xl border-slate-300 text-sm">
            <select name="status" class="rounded-xl border-slate-300 text-sm">
                <option value="">Any status</option>
                @foreach(\App\Models\Storefront\StorefrontListing::STATUSES as $s)
                    <option value="{{ $s }}" @selected($filters['status'] === $s)>{{ $statusLabel($s) }}</option>
                @endforeach
            </select>
            <select name="kind" class="rounded-xl border-slate-300 text-sm">
                <option value="">Any kind</option>
                @foreach(\App\Models\Storefront\StorefrontListing::KINDS as $k)
                    <option value="{{ $k }}" @selected($filters['kind'] === $k)>{{ $statusLabel($k) }}</option>
                @endforeach
            </select>
            <button class="rounded-xl bg-slate-800 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">Filter</button>
            <a href="{{ route('storefront.admin.listings.index') }}" class="inline-flex items-center justify-center rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Clear</a>
        </form>

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
            <div class="overflow-x-auto">
            <table class="min-w-[980px] w-full text-sm">
                <thead class="bg-slate-50 text-left text-slate-500">
                    <tr>
                        <th class="px-5 py-3 font-semibold">Ref</th>
                        <th class="px-5 py-3 font-semibold">Title</th>
                        <th class="px-5 py-3 font-semibold">Kind</th>
                        <th class="px-5 py-3 font-semibold">Mode</th>
                        <th class="px-5 py-3 font-semibold">Price</th>
                        <th class="px-5 py-3 font-semibold">Status</th>
                        <th class="px-5 py-3 text-right font-semibold">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($listings as $listing)
                        @php
                            $canPublish = $listingService->canTransition($listing, \App\Models\Storefront\StorefrontListing::STATUS_PUBLISHED);
                            $canUnpublish = $listingService->canTransition($listing, \App\Models\Storefront\StorefrontListing::STATUS_UNPUBLISHED);
                            $canArchive = $listingService->canTransition($listing, \App\Models\Storefront\StorefrontListing::STATUS_ARCHIVED);
                        @endphp
                        <tr class="border-t border-slate-100">
                            <td class="whitespace-nowrap px-5 py-3 font-mono text-xs">{{ $listing->reference }}</td>
                            <td class="px-5 py-3">
                                <a href="{{ route('storefront.admin.listings.show', $listing) }}" class="font-medium text-slate-800 hover:underline">{{ $listing->title }}</a>
                            </td>
                            <td class="whitespace-nowrap px-5 py-3 text-slate-500">{{ $statusLabel($listing->kind) }}</td>
                            <td class="whitespace-nowrap px-5 py-3 text-slate-500">{{ $statusLabel($listing->sale_mode) }}</td>
                            <td class="whitespace-nowrap px-5 py-3 text-slate-700">{{ $listing->displayPrice() !== null ? $listing->currency_code.' '.number_format((float)$listing->displayPrice(),2) : '-' }}</td>
                            <td class="whitespace-nowrap px-5 py-3">
                                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">{{ $statusLabel($listing->status) }}</span>
                            </td>
                            <td class="px-5 py-3">
                                <div class="flex items-center justify-end gap-2 whitespace-nowrap">
                                    @can('view', $listing)
                                        <a href="{{ route('storefront.admin.listings.show', $listing) }}" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">View</a>
                                    @endcan

                                    @can('update', $listing)
                                        <a href="{{ route('storefront.admin.listings.edit', $listing) }}" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Edit</a>

                                        @if($canPublish)
                                            <form method="POST" action="{{ route('storefront.admin.listings.publish', $listing) }}">
                                                @csrf
                                                <button class="rounded-lg bg-emerald-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-800">Publish</button>
                                            </form>
                                        @endif

                                        @if($canUnpublish)
                                            <form method="POST" action="{{ route('storefront.admin.listings.unpublish', $listing) }}">
                                                @csrf
                                                <button class="rounded-lg border border-amber-300 px-3 py-1.5 text-xs font-semibold text-amber-700 hover:bg-amber-50">Unpublish</button>
                                            </form>
                                        @endif

                                        @if($canArchive)
                                            <form method="POST" action="{{ route('storefront.admin.listings.archive', $listing) }}" onsubmit="return confirm('Archive this listing? Archived listings are not public and cannot be restored from this table.');">
                                                @csrf
                                                <button class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Archive</button>
                                            </form>
                                        @elseif($listing->status === \App\Models\Storefront\StorefrontListing::STATUS_ARCHIVED)
                                            <span class="rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-500" title="Archived listings are terminal in the current lifecycle.">Restore unavailable</span>
                                        @endif
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-10 text-center">
                                <p class="font-medium text-slate-600">No listings found.</p>
                                @can('push_to_storefront')
                                    <a href="{{ route('storefront.admin.push.create') }}" class="mt-3 inline-flex rounded-xl bg-slate-800 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">Push to storefront</a>
                                @endcan
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            </div>
        </div>
        <div class="mt-6">{{ $listings->links() }}</div>
    </div>
</x-app-layout>
