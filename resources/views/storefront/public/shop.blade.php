@php
    use App\Storefront\Support\StorefrontCatalog;
    use App\Storefront\Support\StorefrontSettings;

    $kindLabels = [
        'live_pig'       => 'Live Pigs',
        'piglet_lot'     => 'Piglets',
        'breeding_stock' => 'Breeding Stock',
        'feed'           => 'Feed',
        'manure'         => 'Manure',
        'service'        => 'Services',
    ];
    $pageHeading = $kindLabels[$filters['kind']] ?? 'Shop all products';
    $hasFilters = filled($filters['q']) || filled($filters['kind']) || filled($filters['category']) || filled($filters['sort']);
@endphp
<x-storefront.layout :title="'Shop — ' . StorefrontSettings::storeName()">
    <style>
        .sf-browse {
            padding-top: 4.5rem;
            padding-bottom: 5rem;
        }

        .sf-browse-header {
            margin-bottom: 2.5rem;
        }

        .sf-browse-filter {
            display: grid;
            grid-template-columns: minmax(0, 4fr) minmax(0, 2.5fr) minmax(0, 2.5fr) minmax(8rem, 1fr);
            align-items: end;
            gap: 1rem;
            margin-bottom: 3rem;
            padding: 1.5rem;
        }

        .sf-browse-field label {
            display: block;
            margin-bottom: .5rem;
            color: var(--text-main);
            font-size: .75rem;
            font-weight: 800;
            letter-spacing: .04em;
        }

        .sf-browse-control {
            width: 100%;
            min-height: 3rem;
            border: 1px solid #d8e8e0 !important;
            border-radius: .75rem !important;
            background-color: #fff;
            color: var(--text-main);
            font-size: .875rem;
        }

        .sf-browse-control:focus {
            border-color: var(--green-700) !important;
            box-shadow: 0 0 0 3px rgba(28,108,81,.12) !important;
            outline: none;
        }

        .sf-browse-submit {
            min-height: 3rem;
            background: var(--green-700) !important;
            color: #fff !important;
        }

        .sf-browse-results-head {
            display: flex;
            align-items: end;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .sf-browse-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 1.5rem;
        }

        .sf-browse-pagination {
            margin-top: 2.5rem;
        }

        @media (max-width: 1023px) {
            .sf-browse-filter {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .sf-browse-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 639px) {
            .sf-browse {
                padding-top: 3.5rem;
                padding-bottom: 4rem;
            }

            .sf-browse-header {
                margin-bottom: 2rem;
            }

            .sf-browse-filter {
                grid-template-columns: minmax(0, 1fr);
                gap: 1rem;
                margin-bottom: 2.5rem;
                padding: 1.25rem;
            }

            .sf-browse-grid {
                grid-template-columns: minmax(0, 1fr);
                gap: 1.25rem;
            }
        }
    </style>

    <div class="container-wrap sf-browse">
        <div class="sf-browse-header max-w-3xl">
            <div class="mb-4 inline-flex items-center gap-2 rounded-full border border-[#d8ebe2] bg-white/90 px-4 py-2 text-sm font-bold text-[var(--green-700)] shadow-sm">
                <span class="h-2 w-2 rounded-full bg-[#23b26d] shadow-[0_0_0_6px_rgba(35,178,109,.15)]"></span>
                Chipper Farms shop
            </div>
            <h1 class="text-4xl font-black leading-tight tracking-tight text-[var(--text-main)] md:text-5xl">{{ $pageHeading }}</h1>
            <p class="mt-3 text-base leading-8 text-[var(--text-soft)]">Browse available farm products and order directly from Chipper Farms.</p>
        </div>

        {{-- Filters --}}
        <form method="GET" action="{{ route('storefront.shop') }}" class="sf-browse-filter rounded-3xl border border-[#e3efe9] bg-white shadow-sm">
            @if(filled($filters['category']))
                <input type="hidden" name="category" value="{{ $filters['category'] }}">
            @endif
            <div class="sf-browse-field">
                <label for="storefront-search">SEARCH</label>
                <input id="storefront-search" type="search" name="q" value="{{ $filters['q'] }}" placeholder="Search by product or reference…" class="sf-browse-control">
            </div>
            <div class="sf-browse-field">
                <label for="storefront-kind">CATEGORY</label>
                <select id="storefront-kind" name="kind" class="sf-browse-control">
                    <option value="">All products</option>
                    @foreach(StorefrontCatalog::enabledKinds() as $k)
                        <option value="{{ $k }}" @selected($filters['kind'] === $k)>{{ $kindLabels[$k] ?? ucwords(str_replace('_', ' ', $k)) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="sf-browse-field">
                <label for="storefront-sort">SORT BY</label>
                <select id="storefront-sort" name="sort" class="sf-browse-control">
                    <option value="">Newest first</option>
                    <option value="price_asc" @selected($filters['sort'] === 'price_asc')>Price: low to high</option>
                    <option value="price_desc" @selected($filters['sort'] === 'price_desc')>Price: high to low</option>
                    <option value="oldest" @selected($filters['sort'] === 'oldest')>Oldest first</option>
                </select>
            </div>
            <button type="submit" class="sf-browse-submit rounded-xl px-4 py-2 text-sm font-bold shadow-sm transition hover:bg-[var(--green-800)]">Show results</button>
        </form>

        {{-- Grid --}}
        <div class="sf-browse-results-head">
            <div>
                <p class="text-xs font-extrabold uppercase tracking-[.14em] text-[var(--green-600)]">Available now</p>
                <h2 class="mt-1 text-2xl font-black text-[var(--text-main)]">{{ $listings->total() }} {{ \Illuminate\Support\Str::plural('product', $listings->total()) }}</h2>
            </div>
            @if($hasFilters)
                <a href="{{ route('storefront.shop') }}" class="text-sm font-bold text-[var(--green-700)] hover:text-[var(--green-900)]">Clear filters</a>
            @endif
        </div>
        @if($listings->isEmpty())
            <div class="rounded-3xl border border-dashed border-[#c8e5da] bg-white/80 p-12 text-center text-[var(--text-soft)] shadow-sm">
                <p class="font-bold text-[var(--text-main)]">No products found</p>
                <p class="mt-2 text-sm">Try changing your search or clearing the selected filters.</p>
            </div>
        @else
            <div class="sf-browse-grid">
                @foreach($listings as $listing)
                    @include('storefront.partials.listing-card', ['listing' => $listing])
                @endforeach
            </div>
            <div class="sf-browse-pagination">{{ $listings->links() }}</div>
        @endif
    </div>
</x-storefront.layout>
