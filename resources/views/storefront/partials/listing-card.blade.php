@php
    /** @var \App\Models\Storefront\StorefrontListing $listing */
    $primary = $listing->media->firstWhere('is_primary', true) ?? $listing->media->first();
    $price = $listing->displayPrice();
    $priceRange = $listing->displayPriceRangeBounds();
@endphp
<a href="{{ route('storefront.listing', $listing->slug) }}" class="block bg-white rounded-2xl border border-slate-200 overflow-hidden flex flex-col hover:border-slate-400 transition">
    <div class="aspect-[4/3] bg-slate-100 flex items-center justify-center overflow-hidden">
        @if($primary && $primary->url())
            <img src="{{ $primary->url() }}" alt="{{ $primary->alt_text ?? $listing->title }}" class="w-full h-full object-cover">
        @else
            <x-ui-icon name="pig" class="h-12 w-12 text-slate-400" />
        @endif
    </div>
    <div class="p-4 flex-1 flex flex-col">
        <p class="text-xs uppercase tracking-wide text-slate-400">{{ str_replace('_', ' ', $listing->kind) }}</p>
        <h3 class="font-semibold text-slate-800 mt-1 line-clamp-2">{{ $listing->title }}</h3>

        @if($listing->isQuoteOnly())
            <p class="mt-2 text-sm font-medium text-amber-600">Price on request</p>
        @elseif($priceRange)
            <p class="mt-2 text-sm font-semibold text-slate-900">
                @if(bccomp($priceRange['min'], $priceRange['max'], 2) === 0)
                    {{ $listing->currency_code }} {{ number_format((float) $priceRange['min'], 2) }}
                @else
                    {{ $listing->currency_code }} {{ number_format((float) $priceRange['min'], 2) }} - {{ number_format((float) $priceRange['max'], 2) }}
                @endif
            </p>
            <p class="mt-0.5 text-xs text-slate-500">Estimated per {{ $listing->kind === \App\Models\Storefront\StorefrontListing::KIND_PIGLET_LOT ? 'piglet' : 'pig' }}</p>
        @elseif($price !== null)
            <p class="mt-2 text-sm font-semibold text-slate-900">
                {{ $listing->currency_code }} {{ number_format((float) $price, 2) }}
                @if($listing->pricing_basis !== 'fixed')<span class="text-slate-400 font-normal">/ kg ({{ $listing->pricing_basis }})</span>@endif
            </p>
        @endif

        <div class="mt-3 flex flex-wrap gap-1.5 text-[11px]">
            @if($listing->pickup_allowed)<span class="px-2 py-0.5 rounded-full bg-slate-100 text-slate-600">Pickup</span>@endif
            @if($listing->delivery_allowed)<span class="px-2 py-0.5 rounded-full bg-slate-100 text-slate-600">Delivery</span>@endif
            @foreach(($listing->badges ?? []) as $badge)
                <span class="px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700">{{ str_replace('_', ' ', $badge) }}</span>
            @endforeach
        </div>

        <div class="mt-4 pt-3 border-t border-slate-100">
            <span class="inline-block text-sm font-medium text-slate-500">{{ $listing->reference }}</span>
        </div>
    </div>
</a>
