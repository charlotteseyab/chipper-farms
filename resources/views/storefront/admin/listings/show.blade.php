<x-app-layout>
    <div class="max-w-4xl mx-auto py-6 px-4">
        @if(session('success'))<div class="mb-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 text-sm">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="mb-4 rounded-xl bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">{{ session('error') }}</div>@endif

        <div class="flex items-start justify-between mb-6">
            <div>
                <p class="text-xs font-mono text-slate-400">{{ $listing->reference }}</p>
                <h1 class="text-2xl font-bold text-gray-800">{{ $listing->title }}</h1>
                <p class="text-sm text-slate-500 mt-1">{{ str_replace('_',' ',$listing->kind) }} · {{ $listing->sale_mode }} · <span class="font-medium">{{ $listing->status }}</span></p>
            </div>
            <a href="{{ route('storefront.admin.listings.index') }}" class="text-sm text-slate-500 hover:text-slate-800">← Back</a>
        </div>

        @unless($sourceAvailable)
            <div class="mb-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-700 px-4 py-3 text-sm">
                The operational source for this listing is no longer available (e.g. the pig has been sold). It cannot be published.
            </div>
        @endunless

        <div class="grid md:grid-cols-3 gap-6">
            <div class="md:col-span-2 space-y-4">
                <div class="rounded-2xl bg-white border border-slate-200 p-5">
                    <h2 class="font-semibold text-slate-700 mb-3">Details</h2>
                    <dl class="grid grid-cols-2 gap-3 text-sm">
                        <dt class="text-slate-400">Pricing basis</dt><dd>{{ $listing->pricing_basis }}</dd>
                        <dt class="text-slate-400">Price</dt><dd>{{ $listing->displayPrice() !== null ? $listing->currency_code.' '.number_format((float)$listing->displayPrice(),2) : '—' }}</dd>
                        <dt class="text-slate-400">Quantity</dt><dd>{{ rtrim(rtrim((string)$listing->quantity_available,'0'),'.') }} {{ $listing->quantity_unit }}</dd>
                        <dt class="text-slate-400">Est. weight</dt><dd>{{ $listing->estimated_weight_kg !== null ? $listing->estimated_weight_kg.' kg' : '—' }}</dd>
                        <dt class="text-slate-400">Pickup</dt><dd>{{ $listing->pickup_allowed ? 'Yes' : 'No' }}</dd>
                        <dt class="text-slate-400">Delivery</dt><dd>{{ $listing->delivery_allowed ? 'Yes' : 'No' }}</dd>
                        <dt class="text-slate-400">Source</dt><dd class="font-mono text-xs">{{ $listing->source_type ? class_basename($listing->source_type).'#'.$listing->source_id : 'manual' }}</dd>
                    </dl>
                    @if($listing->public_description)
                        <p class="mt-4 text-sm text-slate-600 whitespace-pre-line">{{ $listing->public_description }}</p>
                    @endif
                </div>
            </div>

            <div class="space-y-4">
                <div class="rounded-2xl bg-white border border-slate-200 p-5">
                    <h2 class="font-semibold text-slate-700 mb-3">Actions</h2>
                    @can('manage_storefront_listings')
                        <div class="space-y-2">
                            <a href="{{ route('storefront.admin.listings.edit', $listing) }}" class="block text-center w-full px-3 py-2 rounded-xl border border-slate-300 text-sm">Edit listing &amp; media</a>
                            @if($listing->status !== 'published')
                                <form method="POST" action="{{ route('storefront.admin.listings.publish', $listing) }}">@csrf
                                    <button class="w-full px-3 py-2 rounded-xl bg-emerald-600 text-white text-sm" @disabled(!$sourceAvailable)>Publish</button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('storefront.admin.listings.unpublish', $listing) }}">@csrf
                                    <button class="w-full px-3 py-2 rounded-xl bg-slate-700 text-white text-sm">Unpublish</button>
                                </form>
                            @endif
                            @if($listing->status !== 'archived')
                                <form method="POST" action="{{ route('storefront.admin.listings.archive', $listing) }}" onsubmit="return confirm('Archive this listing?');">@csrf
                                    <button class="w-full px-3 py-2 rounded-xl border border-slate-300 text-sm">Archive</button>
                                </form>
                            @endif
                        </div>
                    @else
                        <p class="text-sm text-slate-400">You don't have permission to manage this listing.</p>
                    @endcan
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
