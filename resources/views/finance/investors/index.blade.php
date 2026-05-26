<x-app-layout>
    <div class="max-w-5xl mx-auto py-6 px-4">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Investors</h1>
                <p class="text-sm text-gray-500">Equity and preferred-share investors.</p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('finance.investors.cap-table') }}" class="px-4 py-2 bg-slate-100 rounded-xl text-sm">Cap table</a>
                @can('manage_investors')
                    <a href="{{ route('finance.investors.allocate.form') }}" class="px-4 py-2 bg-slate-100 rounded-xl text-sm">Allocate profit</a>
                    <a href="{{ route('finance.investors.create') }}" class="px-4 py-2 bg-slate-800 text-white rounded-xl text-sm">New investor</a>
                @endcan
            </div>
        </div>

        @if(session('success'))<div class="mb-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 text-sm">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="mb-4 rounded-xl bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">{{ session('error') }}</div>@endif

        @php $fmt = fn ($v) => number_format((float) $v, 2); @endphp
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-600">
                    <tr>
                        <th class="px-4 py-3 text-left">Name</th>
                        <th class="px-4 py-3 text-left">Class</th>
                        <th class="px-4 py-3 text-right">Ownership %</th>
                        <th class="px-4 py-3 text-right">Capital balance</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @forelse($investors as $inv)
                        <tr>
                            <td class="px-4 py-3 font-medium text-slate-700">{{ $inv->name }}</td>
                            <td class="px-4 py-3 text-slate-500">{{ ucfirst($inv->investor_class) }}</td>
                            <td class="px-4 py-3 text-right">{{ number_format((float) $inv->ownership_percentage * 100, 2) }}%</td>
                            <td class="px-4 py-3 text-right">{{ $fmt($inv->capitalAccount->current_balance ?? 0) }}</td>
                            <td class="px-4 py-3 text-right"><a href="{{ route('finance.investors.show', $inv) }}" class="text-blue-600 hover:underline">View</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-8 text-center text-slate-400">No investors yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $investors->links() }}</div>
    </div>
</x-app-layout>
