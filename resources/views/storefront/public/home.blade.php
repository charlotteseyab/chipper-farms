@php
    use App\Storefront\Support\StorefrontCatalog;
    use App\Storefront\Support\StorefrontSettings;

    // Phase 8: the hero buttons pointed at Live Pigs and Feed unconditionally.
    // A farm selling only breeding stock advertised two empty aisles. Take the
    // first two enabled kinds instead, and drop the row entirely when a farm has
    // disabled every category.
    $heroKinds = array_slice(StorefrontCatalog::enabledKinds(), 0, 2);
    $kindLabels = [
        'live_pig'       => 'Live Pigs',
        'piglet_lot'     => 'Piglets',
        'breeding_stock' => 'Breeding Stock',
        'feed'           => 'Feed',
        'manure'         => 'Manure',
        'service'        => 'Services',
    ];
@endphp
<x-storefront.layout :title="StorefrontSettings::storeName()">
    {{-- Hero --}}
    <section class="bg-gradient-to-br from-slate-900 to-slate-700 text-white">
        <div class="max-w-6xl mx-auto px-4 py-16 md:py-24 grid md:grid-cols-2 gap-8 items-center">
            <div>
                <h1 class="text-3xl md:text-5xl font-bold leading-tight">{{ StorefrontSettings::heroHeadline() }}</h1>
                <p class="mt-4 text-slate-300 text-lg">{{ StorefrontSettings::heroSubheadline() }}</p>
                <div class="mt-8 flex flex-wrap gap-3">
                    @foreach($heroKinds as $i => $heroKind)
                        <a href="{{ route('storefront.shop', ['kind' => $heroKind]) }}"
                           class="px-5 py-2.5 rounded-xl font-medium {{ $i === 0 ? 'bg-white text-slate-900' : 'bg-slate-800 border border-slate-600' }}">
                            Shop {{ $kindLabels[$heroKind] ?? $heroKind }}
                        </a>
                    @endforeach
                    @if(StorefrontSettings::quotesEnabled())
                        <a href="{{ route('storefront.quote.create') }}" class="px-5 py-2.5 rounded-xl border border-slate-500 font-medium">Request a Quote</a>
                    @endif
                </div>
            </div>
            @if($hero)
                <div class="hidden md:block">
                    @include('storefront.partials.listing-card', ['listing' => $hero])
                </div>
            @endif
        </div>
    </section>

    {{-- Category cards --}}
    <section class="max-w-6xl mx-auto px-4 py-12">
        <h2 class="text-xl font-bold text-slate-800 mb-6">Shop by category</h2>
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
            @forelse($categories as $category)
                <a href="{{ route('storefront.shop', ['category' => $category->slug]) }}"
                   class="rounded-2xl border border-slate-200 bg-white p-5 text-center hover:border-slate-400 transition">
                    <div class="text-3xl mb-2">Pig</div>
                    <p class="font-medium text-slate-700 text-sm">{{ $category->name }}</p>
                </a>
            @empty
                <p class="text-slate-400 text-sm col-span-full">No categories configured yet.</p>
            @endforelse
        </div>
    </section>

    {{-- Featured groups --}}
    @foreach(['new_arrivals' => 'New arrivals', 'live_pigs' => 'Featured live pigs', 'breeding_stock' => 'Breeding stock', 'feed' => 'Feed & supplies', 'manure' => 'Manure'] as $key => $label)
        @if(isset($featured[$key]) && $featured[$key]->isNotEmpty())
            <section class="max-w-6xl mx-auto px-4 py-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-bold text-slate-800">{{ $label }}</h2>
                </div>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    @foreach($featured[$key] as $listing)
                        @include('storefront.partials.listing-card', ['listing' => $listing])
                    @endforeach
                </div>
            </section>
        @endif
    @endforeach

    {{-- Trust / how-it-works --}}
    <section class="max-w-6xl mx-auto px-4 py-12 grid md:grid-cols-3 gap-6">
        <div class="rounded-2xl bg-white border border-slate-200 p-6">
            <p class="font-semibold text-slate-800 mb-1">1. Order or request a quote</p>
            <p class="text-sm text-slate-500">Buy fixed-price items, or request a quote for bulk and breeding stock.</p>
        </div>
        <div class="rounded-2xl bg-white border border-slate-200 p-6">
            <p class="font-semibold text-slate-800 mb-1">2. We confirm</p>
            <p class="text-sm text-slate-500">Our team confirms availability and details before your order is accepted.</p>
        </div>
        <div class="rounded-2xl bg-white border border-slate-200 p-6">
            <p class="font-semibold text-slate-800 mb-1">3. Pay &amp; collect</p>
            <p class="text-sm text-slate-500">Pay securely with Paystack or cash on delivery. Pickup or local delivery.</p>
        </div>
    </section>
</x-storefront.layout>
