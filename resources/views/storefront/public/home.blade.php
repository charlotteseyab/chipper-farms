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
    $hasFeaturedListings = collect($featured)->contains(fn ($listings) => $listings->isNotEmpty());
@endphp
<x-storefront.layout :title="StorefrontSettings::storeName()">
    <style>
        /* Critical page rhythm lives here instead of relying on newly generated
           utility classes, so deployed and cached Vite builds render identically. */
        .sf-home-hero-inner {
            display: grid;
            align-items: center;
            gap: 4rem;
            padding-top: 5.5rem;
            padding-bottom: 5.5rem;
        }

        .sf-home-hero-inner--featured {
            grid-template-columns: minmax(0, 1.08fr) minmax(0, .92fr);
        }

        .sf-home-actions {
            display: flex;
            flex-wrap: wrap;
            gap: .75rem;
            margin-top: 2rem;
        }

        .sf-home-primary-action {
            background: var(--green-700) !important;
            color: #fff !important;
            box-shadow: 0 12px 24px rgba(28,108,81,.2);
        }

        .sf-home-category-section {
            padding-top: 4.5rem !important;
            padding-bottom: 4.5rem !important;
        }

        .sf-home-section-header {
            margin-bottom: 1.75rem !important;
        }

        .sf-home-featured {
            padding-top: 4.5rem !important;
            padding-bottom: 4.5rem !important;
        }

        .sf-home-featured-section + .sf-home-featured-section {
            margin-top: 4rem !important;
        }

        .sf-home-how {
            padding-top: 5rem !important;
            padding-bottom: 5rem !important;
        }

        .sf-home-how-heading {
            margin-bottom: 2.5rem !important;
        }

        .sf-home-how-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 1.5rem;
        }

        .sf-home-step {
            padding: 1.75rem !important;
        }

        .sf-home-step-number {
            margin-bottom: 1.5rem !important;
        }

        .sf-home-step-title {
            margin-bottom: .5rem !important;
        }

        @media (max-width: 767px) {
            .sf-home-hero-inner,
            .sf-home-hero-inner--featured {
                grid-template-columns: minmax(0, 1fr);
                gap: 2.5rem;
                padding-top: 3.5rem;
                padding-bottom: 3.75rem;
            }

            .sf-home-category-section,
            .sf-home-featured {
                padding-top: 3.5rem !important;
                padding-bottom: 3.5rem !important;
            }

            .sf-home-how {
                padding-top: 4rem !important;
                padding-bottom: 4rem !important;
            }

            .sf-home-how-heading {
                margin-bottom: 2rem !important;
            }

            .sf-home-how-grid {
                grid-template-columns: minmax(0, 1fr);
                gap: 1rem;
            }
        }
    </style>

    {{-- Hero --}}
    <section class="border-b border-[#e3efe9]/80">
        <div class="container-wrap sf-home-hero-inner {{ $hero ? 'sf-home-hero-inner--featured' : '' }}">
            <div class="max-w-3xl {{ $hero ? '' : 'mx-auto text-center' }}">
                <div class="mb-6 inline-flex items-center gap-2 rounded-full border border-[#d8ebe2] bg-white/90 px-4 py-2 text-sm font-bold text-[var(--green-700)] shadow-sm">
                    <span class="h-2 w-2 rounded-full bg-[#23b26d] shadow-[0_0_0_6px_rgba(35,178,109,.15)]"></span>
                    Chipper Farms shop
                </div>
                <h1 class="text-4xl font-black leading-[1.02] tracking-[-.04em] text-[var(--text-main)] sm:text-5xl lg:text-6xl">{{ StorefrontSettings::heroHeadline() }}</h1>
                <p class="mt-5 text-base leading-8 text-[var(--text-soft)] sm:text-lg">{{ StorefrontSettings::heroSubheadline() }}</p>
                <div class="sf-home-actions {{ $hero ? '' : 'justify-center' }}">
                    <a href="{{ route('storefront.shop') }}" class="sf-home-primary-action rounded-[14px] px-5 py-3 font-bold transition hover:-translate-y-0.5 hover:bg-[var(--green-800)]">
                        Browse all products
                    </a>
                    @foreach($heroKinds as $heroKind)
                        <a href="{{ route('storefront.shop', ['kind' => $heroKind]) }}"
                           class="rounded-[14px] border border-[var(--line)] bg-white px-5 py-3 font-bold text-[var(--green-700)] shadow-sm transition hover:-translate-y-0.5 hover:border-[#c8e5da]">
                            Shop {{ $kindLabels[$heroKind] ?? $heroKind }}
                        </a>
                    @endforeach
                    @if(StorefrontSettings::quotesEnabled())
                        <a href="{{ route('storefront.quote.create') }}" class="rounded-[14px] border border-[var(--line)] bg-white px-5 py-3 font-bold text-[var(--green-700)] shadow-sm transition hover:-translate-y-0.5 hover:border-[#c8e5da]">Request a Quote</a>
                    @endif
                </div>
            </div>
            @if($hero)
                <div class="mx-auto w-full max-w-xl rounded-[30px] border border-[#deefe7] bg-white/80 p-4 shadow-[var(--shadow-lg)] backdrop-blur-sm lg:p-5">
                    @include('storefront.partials.listing-card', ['listing' => $hero])
                </div>
            @endif
        </div>
    </section>

    {{-- Category cards --}}
    <section class="container-wrap sf-home-category-section">
        <div class="sf-home-section-header flex items-end justify-between gap-4">
            <div>
                <p class="mb-2 text-xs font-extrabold uppercase tracking-[.18em] text-[var(--green-600)]">Find what you need</p>
                <h2 class="text-2xl font-black tracking-tight text-[var(--text-main)] sm:text-3xl">Shop by category</h2>
            </div>
            <a href="{{ route('storefront.shop') }}" class="shrink-0 text-sm font-bold text-[var(--green-700)] hover:text-[var(--green-900)]">View all <span aria-hidden="true">&rarr;</span></a>
        </div>
        <div class="grid grid-cols-2 gap-3 sm:gap-4 md:grid-cols-3 lg:grid-cols-6">
            @forelse($categories as $category)
                <a href="{{ route('storefront.shop', ['category' => $category->slug]) }}"
                   class="group flex min-h-28 flex-col items-center justify-center rounded-3xl border border-[#e3efe9] bg-white p-4 text-center shadow-sm transition hover:-translate-y-1 hover:border-[#b9ddce] hover:shadow-md sm:min-h-32 sm:p-5">
                    <span class="mb-3 flex h-9 w-9 items-center justify-center rounded-full bg-[var(--green-50)] text-lg text-[var(--green-700)] transition group-hover:bg-[var(--green-100)]" aria-hidden="true">&bull;</span>
                    <p class="text-sm font-extrabold leading-5 text-[var(--green-800)]">{{ $category->name }}</p>
                </a>
            @empty
                <div class="col-span-full rounded-3xl border border-dashed border-[#cfe4da] bg-white/70 px-6 py-10 text-center text-sm text-[var(--text-soft)]">No categories configured yet.</div>
            @endforelse
        </div>
    </section>

    {{-- Featured groups --}}
    @if($hasFeaturedListings)
    <div class="sf-home-featured border-y border-[#e3efe9]/80 bg-white/45">
    @foreach(['new_arrivals' => 'New arrivals', 'best_sellers' => 'Best sellers', 'live_pigs' => 'Featured live pigs', 'breeding_stock' => 'Breeding stock', 'feed' => 'Feed & supplies', 'manure' => 'Manure', 'bulk_orders' => 'Bulk orders'] as $key => $label)
        @if(isset($featured[$key]) && $featured[$key]->isNotEmpty())
            <section class="container-wrap sf-home-featured-section">
                <div class="mb-6 flex items-center justify-between gap-4">
                    <h2 class="text-xl font-black tracking-tight text-[var(--text-main)] sm:text-2xl">{{ $label }}</h2>
                    <a href="{{ route('storefront.shop') }}" class="text-sm font-bold text-[var(--green-700)] hover:text-[var(--green-900)]">Browse shop <span aria-hidden="true">&rarr;</span></a>
                </div>
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach($featured[$key] as $listing)
                        @include('storefront.partials.listing-card', ['listing' => $listing])
                    @endforeach
                </div>
            </section>
        @endif
    @endforeach
    </div>
    @endif

    {{-- Trust / how-it-works --}}
    <section class="container-wrap sf-home-how">
        <div class="sf-home-how-heading mx-auto max-w-2xl text-center">
            <p class="mb-2 text-xs font-extrabold uppercase tracking-[.18em] text-[var(--green-600)]">Simple and reliable</p>
            <h2 class="text-2xl font-black tracking-tight text-[var(--text-main)] sm:text-3xl">How ordering works</h2>
        </div>
        <div class="sf-home-how-grid">
            @foreach([
                ['Order or request a quote', 'Buy fixed-price items, or request a quote for bulk and breeding stock.'],
                ['We confirm', 'Our team confirms availability and details before your order is accepted.'],
                ['Pay & collect', 'Pay securely with Paystack or cash on delivery. Pickup or local delivery.'],
            ] as $step)
                <div class="sf-home-step rounded-3xl border border-[#e3efe9] bg-white shadow-sm">
                    <span class="sf-home-step-number flex h-10 w-10 items-center justify-center rounded-full bg-[var(--green-100)] text-sm font-black text-[var(--green-800)]">{{ $loop->iteration }}</span>
                    <p class="sf-home-step-title font-extrabold text-[var(--text-main)]">{{ $step[0] }}</p>
                    <p class="text-sm leading-7 text-[var(--text-soft)]">{{ $step[1] }}</p>
                </div>
            @endforeach
        </div>
    </section>
</x-storefront.layout>
