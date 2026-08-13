@php use App\Storefront\Support\StorefrontSettings; @endphp
<x-storefront.layout :title="'Shop — ' . StorefrontSettings::storeName()">
    <div class="container-wrap py-10 md:py-14">
        <div class="mb-8 max-w-3xl">
            <div class="mb-4 inline-flex items-center gap-2 rounded-full border border-[#d8ebe2] bg-white/90 px-4 py-2 text-sm font-bold text-[var(--green-700)] shadow-sm">
                <span class="h-2 w-2 rounded-full bg-[#23b26d] shadow-[0_0_0_6px_rgba(35,178,109,.15)]"></span>
                Chipper Farms shop
            </div>
            <h1 class="text-4xl font-black leading-tight tracking-tight text-[var(--text-main)] md:text-5xl">Shop</h1>
            <p class="mt-3 text-base leading-8 text-[var(--text-soft)]">Browse available farm products and place an order through the same Chipper Farms platform.</p>
        </div>

        {{-- Filters --}}
        <form method="GET" action="{{ route('storefront.shop') }}" class="bg-white rounded-3xl border border-[#e3efe9] p-4 mb-8 grid md:grid-cols-4 gap-3 shadow-sm">
            <input type="text" name="q" value="{{ $filters['q'] }}" placeholder="Search listings…"
                   class="rounded-xl border-[#d8ebe2] text-sm focus:border-[var(--green-700)] focus:ring-[var(--green-700)]">
            <select name="kind" class="rounded-xl border-slate-300 text-sm">
                <option value="">All kinds</option>
                @foreach(\App\Models\Storefront\StorefrontListing::KINDS as $k)
                    <option value="{{ $k }}" @selected($filters['kind'] === $k)>{{ ucwords(str_replace('_', ' ', $k)) }}</option>
                @endforeach
            </select>
            <select name="sort" class="rounded-xl border-slate-300 text-sm">
                <option value="">Newest</option>
                <option value="price_asc" @selected($filters['sort'] === 'price_asc')>Price: low to high</option>
                <option value="price_desc" @selected($filters['sort'] === 'price_desc')>Price: high to low</option>
                <option value="oldest" @selected($filters['sort'] === 'oldest')>Oldest</option>
            </select>
            <button type="submit" class="rounded-xl bg-[var(--green-700)] text-white text-sm font-bold px-4 py-2 shadow-sm transition hover:bg-[var(--green-800)]">Apply</button>
        </form>

        {{-- Grid --}}
        @if($listings->isEmpty())
            <div class="rounded-3xl border border-dashed border-[#c8e5da] bg-white/80 p-12 text-center text-[var(--text-soft)] shadow-sm">
                No listings match your search.
            </div>
        @else
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                @foreach($listings as $listing)
                    @include('storefront.partials.listing-card', ['listing' => $listing])
                @endforeach
            </div>
            <div class="mt-8">{{ $listings->links() }}</div>
        @endif
    </div>
</x-storefront.layout>
