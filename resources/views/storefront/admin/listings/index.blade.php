<x-app-layout>
    <div class="max-w-6xl mx-auto py-6 px-4">
        <div class="mb-6 flex items-center justify-between">
            <h1 class="text-2xl font-bold text-gray-800">Listings</h1>
            @can('push_to_storefront')
                <a href="{{ route('storefront.admin.push.create') }}" class="px-4 py-2 bg-slate-800 text-white rounded-xl text-sm">Push to storefront</a>
            @endcan
        </div>

        @if(session('success'))<div class="mb-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 text-sm">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="mb-4 rounded-xl bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">{{ session('error') }}</div>@endif

        <form method="GET" class="bg-white rounded-2xl border border-slate-200 p-4 mb-6 grid md:grid-cols-4 gap-3">
            <input type="text" name="q" value="{{ $filters['q'] }}" placeholder="Search ref or title…" class="rounded-xl border-slate-300 text-sm">
            <select name="status" class="rounded-xl border-slate-300 text-sm">
                <option value="">Any status</option>
                @foreach(\App\Models\Storefront\StorefrontListing::STATUSES as $s)
                    <option value="{{ $s }}" @selected($filters['status'] === $s)>{{ $s }}</option>
                @endforeach
            </select>
            <select name="kind" class="rounded-xl border-slate-300 text-sm">
                <option value="">Any kind</option>
                @foreach(\App\Models\Storefront\StorefrontListing::KINDS as $k)
                    <option value="{{ $k }}" @selected($filters['kind'] === $k)>{{ str_replace('_',' ',$k) }}</option>
                @endforeach
            </select>
            <button class="rounded-xl bg-slate-800 text-white text-sm px-4 py-2">Filter</button>
        </form>

        <div class="rounded-2xl bg-white border border-slate-200 overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 text-left">
                    <tr><th class="px-5 py-2">Ref</th><th class="px-5 py-2">Title</th><th class="px-5 py-2">Kind</th><th class="px-5 py-2">Mode</th><th class="px-5 py-2">Price</th><th class="px-5 py-2">Status</th></tr>
                </thead>
                <tbody>
                    @forelse($listings as $listing)
                        <tr class="border-t border-slate-100">
                            <td class="px-5 py-2 font-mono text-xs">{{ $listing->reference }}</td>
                            <td class="px-5 py-2"><a href="{{ route('storefront.admin.listings.show', $listing) }}" class="text-slate-800 hover:underline">{{ $listing->title }}</a></td>
                            <td class="px-5 py-2 text-slate-500">{{ str_replace('_',' ',$listing->kind) }}</td>
                            <td class="px-5 py-2 text-slate-500">{{ $listing->sale_mode }}</td>
                            <td class="px-5 py-2 text-slate-700">{{ $listing->displayPrice() !== null ? $listing->currency_code.' '.number_format((float)$listing->displayPrice(),2) : '—' }}</td>
                            <td class="px-5 py-2"><span class="px-2 py-0.5 rounded-full text-xs bg-slate-100 text-slate-600">{{ $listing->status }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-8 text-center text-slate-400">No listings found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-6">{{ $listings->links() }}</div>
    </div>
</x-app-layout>
