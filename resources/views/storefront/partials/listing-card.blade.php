@php
    /** @var \App\Models\Storefront\StorefrontListing $listing */
    $primary = $listing->media->firstWhere('is_primary', true) ?? $listing->media->first();
    $price = $listing->displayPrice();
    $priceRange = $listing->displayPriceRangeBounds();
@endphp
<a href="{{ route('storefront.listing', $listing->slug) }}" class="group flex h-full flex-col overflow-hidden rounded-2xl border border-[#e1ece7] bg-white shadow-sm transition duration-300 hover:-translate-y-1 hover:border-[#bddbce] hover:shadow-lg">
    <div class="flex aspect-[4/3] items-center justify-center overflow-hidden bg-[#f1f6f3]">
        @if($primary && $primary->url())
            <img src="{{ $primary->url() }}" alt="{{ $primary->alt_text ?? $listing->title }}" class="h-full w-full object-cover transition duration-500 group-hover:scale-[1.03]">
        @else
            <x-ui-icon name="pig" class="h-12 w-12 text-[#9ab5a8]" />
        @endif
    </div>
    <div class="flex flex-1 flex-col p-4 sm:p-5">
        <p class="text-[11px] font-bold uppercase tracking-[.14em] text-[var(--green-600)]">{{ str_replace('_', ' ', $listing->kind) }}</p>
        <h3 class="mt-1.5 line-clamp-2 text-base font-extrabold leading-6 text-[var(--text-main)]">{{ $listing->title }}</h3>

        @if($listing->isQuoteOnly())
            <p class="mt-3 text-sm font-bold text-amber-700">Price on request</p>
        @elseif($priceRange)
            <p class="mt-3 text-sm font-extrabold text-[var(--text-main)]">
                @if(bccomp($priceRange['min'], $priceRange['max'], 2) === 0)
                    {{ $listing->currency_code }} {{ number_format((float) $priceRange['min'], 2) }}
                @else
                    {{ $listing->currency_code }} {{ number_format((float) $priceRange['min'], 2) }} - {{ number_format((float) $priceRange['max'], 2) }}
                @endif
            </p>
            <p class="mt-0.5 text-xs text-slate-500">Estimated per {{ $listing->kind === \App\Models\Storefront\StorefrontListing::KIND_PIGLET_LOT ? 'piglet' : 'pig' }}</p>
        @elseif($price !== null)
            <p class="mt-3 text-sm font-extrabold text-[var(--text-main)]">
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

        <div class="mt-auto flex items-center justify-between border-t border-slate-100 pt-4 text-sm">
            <span class="font-medium text-slate-500">{{ $listing->reference }}</span>
            <span class="font-bold text-[var(--green-700)]">View <span aria-hidden="true">&rarr;</span></span>
        </div>
    </div>
</a>
