<x-storefront.layout :title="'My quotes'">
    <div class="max-w-4xl mx-auto px-4 py-10">
        <a href="{{ route('storefront.account.dashboard') }}" class="text-sm text-slate-500">← Account</a>
        <h1 class="text-2xl font-bold text-slate-800 mt-2 mb-6">My quotes</h1>

        @if($quotes->isEmpty())
            <div class="rounded-2xl border border-dashed border-slate-300 p-12 text-center text-slate-400">
                You have no quote requests yet. <a href="{{ route('storefront.quote.create') }}" class="text-slate-700 underline">Request a quote</a>.
            </div>
        @else
            <div class="rounded-2xl bg-white border border-slate-200 overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-slate-500 text-left">
                        <tr><th class="px-5 py-2">Ref</th><th class="px-5 py-2">Items</th><th class="px-5 py-2">Status</th><th class="px-5 py-2">Quoted</th><th class="px-5 py-2">Requested</th></tr>
                    </thead>
                    <tbody>
                        @foreach($quotes as $q)
                            <tr class="border-t border-slate-100">
                                <td class="px-5 py-2 font-mono text-xs">{{ $q->reference }}</td>
                                <td class="px-5 py-2">{{ $q->items_count }}</td>
                                <td class="px-5 py-2"><span class="px-2 py-0.5 rounded-full text-xs bg-slate-100 text-slate-600">{{ $q->status }}</span></td>
                                <td class="px-5 py-2">{{ $q->quoted_total !== null ? $q->currency_code.' '.number_format((float)$q->quoted_total,2) : '—' }}</td>
                                <td class="px-5 py-2 text-slate-500">{{ $q->created_at->format('Y-m-d') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-storefront.layout>
