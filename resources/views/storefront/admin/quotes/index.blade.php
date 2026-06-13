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
                    <tr><th class="px-5 py-2">Ref</th><th class="px-5 py-2">Contact</th><th class="px-5 py-2">Items</th><th class="px-5 py-2">Status</th><th class="px-5 py-2">Requested</th></tr>
                </thead>
                <tbody>
                    @forelse($quotes as $q)
                        <tr class="border-t border-slate-100">
                            <td class="px-5 py-2 font-mono text-xs"><a href="{{ route('storefront.admin.quotes.show', $q) }}" class="text-slate-800 hover:underline">{{ $q->reference }}</a></td>
                            <td class="px-5 py-2">{{ $q->contact_name }}</td>
                            <td class="px-5 py-2">{{ $q->items_count }}</td>
                            <td class="px-5 py-2"><span class="px-2 py-0.5 rounded-full text-xs bg-slate-100 text-slate-600">{{ $q->status }}</span></td>
                            <td class="px-5 py-2 text-slate-500">{{ $q->created_at->format('Y-m-d') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-8 text-center text-slate-400">No quotes found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-6">{{ $quotes->links() }}</div>
    </div>
</x-app-layout>
