<x-app-layout>
    <div class="max-w-5xl mx-auto py-6 px-4">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800">Homepage placements</h1>
            <p class="text-sm text-slate-500">Pin published listings into the homepage / featured slots the shop renders.</p>
        </div>

        @if(session('success'))<div class="mb-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 text-sm">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="mb-4 rounded-xl bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">{{ session('error') }}</div>@endif

        <div class="rounded-2xl bg-white border border-slate-200 p-5 mb-8">
            <h2 class="font-semibold text-slate-700 mb-3">Pin a listing</h2>
            <form method="POST" action="{{ route('storefront.admin.placements.store') }}" class="grid md:grid-cols-4 gap-3">
                @csrf
                <select name="code" class="rounded-xl border-slate-300 text-sm" required>
                    @foreach($codes as $code)<option value="{{ $code }}">{{ $code }}</option>@endforeach
                </select>
                <select name="listing_id" class="rounded-xl border-slate-300 text-sm md:col-span-2" required>
                    <option value="">Select a published listing…</option>
                    @foreach($publishedListings as $l)
                        <option value="{{ $l->id }}">{{ $l->reference }} — {{ $l->title }}</option>
                    @endforeach
                </select>
                <button class="rounded-xl bg-slate-800 text-white text-sm px-4 py-2">Pin</button>
            </form>
            @if($publishedListings->isEmpty())
                <p class="text-xs text-amber-600 mt-2">No published listings available to feature yet.</p>
            @endif
        </div>

        <div class="space-y-6">
            @foreach($codes as $code)
                <div class="rounded-2xl bg-white border border-slate-200 overflow-hidden">
                    <div class="px-5 py-3 border-b border-slate-100 font-mono text-xs text-slate-500">{{ $code }}</div>
                    <table class="w-full text-sm">
                        <tbody>
                            @forelse(($placements[$code] ?? collect())->filter(fn($p) => $p->listing_id) as $p)
                                <tr class="border-t border-slate-100">
                                    <td class="px-5 py-2 w-16 text-slate-400">#{{ $p->position }}</td>
                                    <td class="px-5 py-2">{{ $p->listing?->reference }} — {{ $p->listing?->title ?? '(missing)' }}</td>
                                    <td class="px-5 py-2 text-right">
                                        <form method="POST" action="{{ route('storefront.admin.placements.destroy', $p) }}" onsubmit="return confirm('Remove this placement?');">
                                            @csrf @method('DELETE')
                                            <button class="text-red-600 text-xs hover:underline">Remove</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td class="px-5 py-4 text-slate-400 text-sm">No listing pinned in this slot.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @endforeach
        </div>
    </div>
</x-app-layout>
