<x-app-layout>
    <div class="max-w-6xl mx-auto py-6 px-4">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Loans</h1>
                <p class="text-sm text-gray-500">Bank, equipment, and investor loans.</p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('finance.loans.reclassification') }}" class="px-4 py-2 bg-slate-100 rounded-xl text-sm">Reclassification</a>
                @can('manage_loans')
                    <a href="{{ route('finance.loans.create') }}" class="px-4 py-2 bg-slate-800 text-white rounded-xl text-sm">New loan</a>
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
                        <th class="px-4 py-3 text-left">Reference</th>
                        <th class="px-4 py-3 text-left">Lender</th>
                        <th class="px-4 py-3 text-left">Type</th>
                        <th class="px-4 py-3 text-right">Principal</th>
                        <th class="px-4 py-3 text-right">Outstanding</th>
                        <th class="px-4 py-3 text-left">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @forelse($loans as $loan)
                        <tr>
                            <td class="px-4 py-3 font-medium text-slate-700">{{ $loan->reference }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $loan->lender_name }}</td>
                            <td class="px-4 py-3 text-slate-500">{{ ucfirst($loan->loan_type) }}</td>
                            <td class="px-4 py-3 text-right">{{ $fmt($loan->principal_amount) }}</td>
                            <td class="px-4 py-3 text-right">{{ $fmt($loan->principal_outstanding) }}</td>
                            <td class="px-4 py-3">
                                @php $b = match($loan->status){'active'=>'bg-emerald-50 text-emerald-700','draft'=>'bg-slate-100 text-slate-600','settled'=>'bg-blue-50 text-blue-700',default=>'bg-amber-50 text-amber-700'}; @endphp
                                <span class="px-2 py-1 rounded-lg text-xs font-medium {{ $b }}">{{ ucfirst($loan->status) }}</span>
                            </td>
                            <td class="px-4 py-3 text-right"><a href="{{ route('finance.loans.show', $loan) }}" class="text-blue-600 hover:underline">View</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-8 text-center text-slate-400">No loans yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $loans->links() }}</div>
    </div>
</x-app-layout>
