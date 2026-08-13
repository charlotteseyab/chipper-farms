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
    <section>
        <div class="container-wrap py-16 md:py-24 grid md:grid-cols-2 gap-10 items-center">
            <div>
                <div class="mb-6 inline-flex items-center gap-2 rounded-full border border-[#d8ebe2] bg-white/90 px-4 py-2 text-sm font-bold text-[var(--green-700)] shadow-sm">
                    <span class="h-2 w-2 rounded-full bg-[#23b26d] shadow-[0_0_0_6px_rgba(35,178,109,.15)]"></span>
                    Chipper Farms shop
                </div>
                <h1 class="max-w-2xl text-4xl font-black leading-[.98] tracking-tight text-[var(--text-main)] md:text-6xl">{{ StorefrontSettings::heroHeadline() }}</h1>
                <p class="mt-5 max-w-2xl text-lg leading-8 text-[var(--text-soft)]">{{ StorefrontSettings::heroSubheadline() }}</p>
                <div class="mt-8 flex flex-wrap gap-3">
                    @foreach($heroKinds as $i => $heroKind)
                        <a href="{{ route('storefront.shop', ['kind' => $heroKind]) }}"
                           class="rounded-[14px] px-5 py-3 font-bold shadow-sm transition hover:-translate-y-0.5 {{ $i === 0 ? 'bg-[var(--green-700)] text-white hover:bg-[var(--green-800)]' : 'border border-[var(--line)] bg-white text-[var(--green-700)] hover:border-[#c8e5da]' }}">
                            Shop {{ $kindLabels[$heroKind] ?? $heroKind }}
                        </a>
                    @endforeach
                    @if(StorefrontSettings::quotesEnabled())
                        <a href="{{ route('storefront.quote.create') }}" class="rounded-[14px] border border-[var(--line)] bg-white px-5 py-3 font-bold text-[var(--green-700)] shadow-sm transition hover:-translate-y-0.5 hover:border-[#c8e5da]">Request a Quote</a>
                    @endif
                </div>
            </div>
            @if($hero)
                <div class="hidden rounded-[30px] border border-[#deefe7] bg-white p-5 shadow-[var(--shadow-lg)] md:block">
                    @include('storefront.partials.listing-card', ['listing' => $hero])
                </div>
            @endif
        </div>
    </section>

    {{-- Category cards --}}
    <section class="container-wrap py-12">
        <h2 class="mb-6 text-2xl font-extrabold text-[var(--text-main)]">Shop by category</h2>
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
            @forelse($categories as $category)
                <a href="{{ route('storefront.shop', ['category' => $category->slug]) }}"
                   class="rounded-3xl border border-[#e3efe9] bg-white p-5 text-center shadow-sm transition hover:-translate-y-1 hover:border-[#c8e5da] hover:shadow-md">
                    <p class="font-bold text-[var(--green-700)] text-sm">{{ $category->name }}</p>
                </a>
            @empty
                <p class="text-slate-400 text-sm col-span-full">No categories configured yet.</p>
            @endforelse
        </div>
    </section>

    {{-- Featured groups --}}
    @foreach(['new_arrivals' => 'New arrivals', 'live_pigs' => 'Featured live pigs', 'breeding_stock' => 'Breeding stock', 'feed' => 'Feed & supplies', 'manure' => 'Manure'] as $key => $label)
        @if(isset($featured[$key]) && $featured[$key]->isNotEmpty())
            <section class="container-wrap py-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-xl font-extrabold text-[var(--text-main)]">{{ $label }}</h2>
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
    <section class="container-wrap py-12 grid md:grid-cols-3 gap-6">
        <div class="rounded-3xl bg-white border border-[#e3efe9] p-6 shadow-sm">
            <p class="font-extrabold text-[var(--text-main)] mb-1">1. Order or request a quote</p>
            <p class="text-sm leading-7 text-[var(--text-soft)]">Buy fixed-price items, or request a quote for bulk and breeding stock.</p>
        </div>
        <div class="rounded-3xl bg-white border border-[#e3efe9] p-6 shadow-sm">
            <p class="font-extrabold text-[var(--text-main)] mb-1">2. We confirm</p>
            <p class="text-sm leading-7 text-[var(--text-soft)]">Our team confirms availability and details before your order is accepted.</p>
        </div>
        <div class="rounded-3xl bg-white border border-[#e3efe9] p-6 shadow-sm">
            <p class="font-extrabold text-[var(--text-main)] mb-1">3. Pay &amp; collect</p>
            <p class="text-sm leading-7 text-[var(--text-soft)]">Pay securely with Paystack or cash on delivery. Pickup or local delivery.</p>
        </div>
    </section>
</x-storefront.layout>
