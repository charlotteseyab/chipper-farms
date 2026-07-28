<x-app-layout>
    <div class="max-w-6xl mx-auto py-6 px-4">
        <div class="mb-6 flex items-center justify-between">
            <h1 class="text-2xl font-bold text-gray-800">Quote requests</h1>
        </div>

        @if(session('success'))<div class="mb-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 text-sm">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="mb-4 rounded-xl bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">{{ session('error') }}</div>@endif

        <form method="GET" class="bg-white rounded-2xl border border-slate-200 p-4 mb-6 flex gap-3">
            <select name="status" class="rounded-xl border-slate-300 text-sm">
                <option value="">Open quotes</option>
                @foreach(\App\Models\Storefront\StorefrontQuote::STATUSES as $s)
                    <option value="{{ $s }}" @selected($status === $s)>{{ $s }}</option>
                @endforeach
            </select>
            <button class="rounded-xl bg-slate-800 text-white text-sm px-4 py-2">Filter</button>
        </form>

        <div class="rounded-2xl bg-white border border-slate-200 overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 text-left">
                    <tr><th class="px-5 py-2">Ref</th><th class="px-5 py-2">Contact</th><th class="px-5 py-2">Items</th><th class="px-5 py-2">Status</th><th class="px-5 py-2">Requested</th><th class="px-5 py-2 text-right">Actions</th></tr>
                </thead>
                <tbody>
                    @forelse($quotes as $q)
                        <tr class="border-t border-slate-100">
                            <td class="px-5 py-2 font-mono text-xs"><a href="{{ route('storefront.admin.quotes.show', $q) }}" class="text-slate-800 hover:underline">{{ $q->reference }}</a></td>
                            <td class="px-5 py-2">{{ $q->contact_name }}</td>
                            <td class="px-5 py-2">{{ $q->items_count }}</td>
                            <td class="px-5 py-2"><span class="px-2 py-0.5 rounded-full text-xs bg-slate-100 text-slate-600">{{ $q->status }}</span></td>
                            <td class="px-5 py-2 text-slate-500">{{ $q->created_at->format('Y-m-d') }}</td>
                            <td class="px-5 py-2">
                                <div class="flex flex-wrap items-center justify-end gap-2">
                                    <a href="{{ route('storefront.admin.quotes.show', $q) }}" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50">View</a>

                                    @can('manage_storefront_quotes')
                                        @if($q->status === \App\Models\Storefront\StorefrontQuote::STATUS_REQUESTED)
                                            <form method="POST" action="{{ route('storefront.admin.quotes.review', $q) }}">
                                                @csrf
                                                <button class="rounded-lg bg-slate-700 px-3 py-1.5 text-xs font-medium text-white">Review</button>
                                            </form>
                                        @endif

                                        @if(in_array($q->status, [\App\Models\Storefront\StorefrontQuote::STATUS_REQUESTED, \App\Models\Storefront\StorefrontQuote::STATUS_REVIEWING], true))
                                            <form method="POST" action="{{ route('storefront.admin.quotes.quote', $q) }}" class="flex items-center gap-2">
                                                @csrf
                                                <input type="number" step="0.01" min="0" name="quoted_total" required placeholder="Total" class="w-24 rounded-lg border-slate-300 px-2 py-1.5 text-xs">
                                                <button class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-medium text-white">Send</button>
                                            </form>
                                        @endif

                                        @if($q->status === \App\Models\Storefront\StorefrontQuote::STATUS_QUOTED)
                                            <form method="POST" action="{{ route('storefront.admin.quotes.accept', $q) }}">
                                                @csrf
                                                <button class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-medium text-white">Accept</button>
                                            </form>
                                        @endif

                                        @if($q->status === \App\Models\Storefront\StorefrontQuote::STATUS_ACCEPTED)
                                            <form method="POST" action="{{ route('storefront.admin.quotes.convert', $q) }}">
                                                @csrf
                                                <button class="rounded-lg bg-slate-800 px-3 py-1.5 text-xs font-medium text-white">Convert</button>
                                            </form>
                                        @endif

                                        @unless($q->isTerminal())
                                            <form method="POST" action="{{ route('storefront.admin.quotes.decline', $q) }}" onsubmit="return confirm('Decline this quote?');">
                                                @csrf
                                                <button class="rounded-lg border border-red-200 px-3 py-1.5 text-xs font-medium text-red-600">Decline</button>
                                            </form>
                                        @endunless
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-8 text-center text-slate-400">No quotes found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-6">{{ $quotes->links() }}</div>
    </div>
</x-app-layout>
