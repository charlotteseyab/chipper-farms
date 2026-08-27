<x-storefront.layout :title="'My quotes'">
    <main class="mx-auto max-w-6xl px-4 py-8 sm:px-6 sm:py-12">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div><p class="text-xs font-bold uppercase tracking-[0.18em] text-emerald-700">Buyer account</p><h1 class="mt-2 text-3xl font-extrabold text-slate-900">My quote requests</h1><p class="mt-2 text-sm text-slate-500">Follow each request from review through pricing and completion.</p></div>
            <a href="{{ route('storefront.quote.create') }}" class="rounded-xl bg-emerald-700 px-5 py-3 text-center text-sm font-bold text-white">Request another quote</a>
        </div>

        <div class="mt-8 grid gap-4">
            @forelse($quotes as $quote)
                @php $labels = ['requested' => 'Request received', 'reviewing' => 'Under review', 'quoted' => 'Price ready', 'accepted' => 'Accepted', 'converted' => 'Order created', 'declined' => 'Declined', 'expired' => 'Expired']; @endphp
                <a href="{{ route('storefront.buyer.quotes.show', $quote) }}" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-emerald-300 hover:shadow-md">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div><div class="flex flex-wrap items-center gap-2"><span class="font-mono text-xs font-bold text-emerald-700">{{ $quote->reference }}</span><span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-bold text-emerald-700">{{ $labels[$quote->status] ?? ucfirst($quote->status) }}</span></div><p class="mt-2 font-bold text-slate-900">{{ $quote->items_count }} {{ Str::plural('item', $quote->items_count) }}</p><p class="mt-1 text-xs text-slate-500">Requested {{ $quote->created_at->format('d M Y, g:i A') }}</p></div>
                        <div class="sm:text-right"><p class="text-xs text-slate-500">Quoted amount</p><p class="mt-1 text-xl font-extrabold text-slate-900">{{ $quote->quoted_total !== null ? ($quote->currency_code.' '.number_format((float) $quote->quoted_total, 2)) : 'Awaiting price' }}</p><span class="mt-2 inline-block text-sm font-bold text-emerald-700">View progress →</span></div>
                    </div>
                </a>
            @empty
                <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center"><h2 class="font-bold text-slate-900">No quote requests yet</h2><p class="mt-2 text-sm text-slate-500">When you request pricing, its progress will appear here.</p></div>
            @endforelse
        </div>
    </main>
</x-storefront.layout>
