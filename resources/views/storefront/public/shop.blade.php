@php use App\Storefront\Support\StorefrontSettings; @endphp
<x-storefront.layout :title="'Shop — ' . StorefrontSettings::storeName()">
    <div class="max-w-6xl mx-auto px-4 py-8">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold text-slate-800">Shop</h1>
        </div>

        {{-- Filters --}}
        <form method="GET" action="{{ route('storefront.shop') }}" class="bg-white rounded-2xl border border-slate-200 p-4 mb-6 grid md:grid-cols-4 gap-3">
            <input type="text" name="q" value="{{ $filters['q'] }}" placeholder="Search listings…"
                   class="rounded-xl border-slate-300 text-sm">
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
            <button type="submit" class="rounded-xl bg-slate-800 text-white text-sm font-medium px-4 py-2">Apply</button>
        </form>

        {{-- Grid --}}
        @if($listings->isEmpty())
            <div class="rounded-2xl border border-dashed border-slate-300 p-12 text-center text-slate-400">
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
