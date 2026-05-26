<x-app-layout>
    <div class="max-w-4xl mx-auto py-6 px-4">
        <div class="mb-6">
            <a href="{{ route('finance.investors.index') }}" class="text-sm text-blue-600 hover:underline">← Investors</a>
            <h1 class="text-2xl font-bold text-gray-800 mt-1">{{ $investor->name }}</h1>
            <p class="text-sm text-gray-500">
                {{ ucfirst($investor->investor_class) }} · ownership {{ number_format((float) $investor->ownership_percentage * 100, 2) }}%
                @if($investor->linkedLoan) · linked loan {{ $investor->linkedLoan->reference }}@endif
            </p>
        </div>

        @if(session('success'))<div class="mb-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 text-sm">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="mb-4 rounded-xl bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">{{ session('error') }}</div>@endif

        @php $fmt = fn ($v) => number_format((float) $v, 2); $s = $statement['summary']; @endphp

        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-5">
            @foreach(['Contributed'=>$s['total_contributed'],'Allocated'=>$s['total_allocated'],'Distributed'=>$s['total_distributed'],'Balance'=>$s['current_balance']] as $label=>$val)
                <div class="bg-white rounded-2xl border border-slate-100 p-4">
                    <div class="text-xs text-slate-500">{{ $label }}</div>
                    <div class="text-lg font-bold text-slate-800">{{ $fmt($val) }}</div>
                </div>
            @endforeach
        </div>

        @can('manage_investors')
            <div class="grid md:grid-cols-2 gap-4 mb-6">
                <form method="POST" action="{{ route('finance.investors.contribute', $investor) }}" class="bg-white rounded-2xl border border-slate-100 p-4">
                    @csrf
                    <h3 class="font-bold text-slate-700 mb-2">Record contribution</h3>
                    <input name="amount" type="number" step="0.01" min="0.01" placeholder="Amount (GHS)" required class="w-full rounded-xl border-slate-200 text-sm mb-2">
                    <select name="bank_account_id" class="w-full rounded-xl border-slate-200 text-sm mb-2">
                        <option value="">— default account —</option>
                        @foreach($bankAccounts as $ba)<option value="{{ $ba->id }}">{{ $ba->label ?? $ba->bank_name }}</option>@endforeach
                    </select>
                    <button class="px-4 py-2 bg-emerald-600 text-white rounded-xl text-sm">Contribute</button>
                </form>
                <form method="POST" action="{{ route('finance.investors.distribute', $investor) }}" class="bg-white rounded-2xl border border-slate-100 p-4">
                    @csrf
                    <h3 class="font-bold text-slate-700 mb-2">Record distribution</h3>
                    <input name="amount" type="number" step="0.01" min="0.01" placeholder="Amount (GHS)" required class="w-full rounded-xl border-slate-200 text-sm mb-2">
                    <select name="bank_account_id" class="w-full rounded-xl border-slate-200 text-sm mb-2">
                        <option value="">— default account —</option>
                        @foreach($bankAccounts as $ba)<option value="{{ $ba->id }}">{{ $ba->label ?? $ba->bank_name }}</option>@endforeach
                    </select>
                    <button class="px-4 py-2 bg-slate-800 text-white rounded-xl text-sm">Distribute</button>
                </form>
            </div>
        @endcan

        <h2 class="text-lg font-bold text-slate-700 mb-2">Capital Statement</h2>
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-600">
                    <tr>
                        <th class="px-4 py-2 text-left">Date</th>
                        <th class="px-4 py-2 text-left">Type</th>
                        <th class="px-4 py-2 text-right">Amount</th>
                        <th class="px-4 py-2 text-right">Capital Δ</th>
                        <th class="px-4 py-2 text-right">Balance</th>
                        <th class="px-4 py-2 text-left">Batch</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @forelse($statement['lines'] as $line)
                        <tr>
                            <td class="px-4 py-2 text-slate-500">{{ $line['date'] }}</td>
                            <td class="px-4 py-2">{{ ucfirst(str_replace('_',' ',$line['type'])) }}</td>
                            <td class="px-4 py-2 text-right">{{ $fmt($line['amount']) }}</td>
                            <td class="px-4 py-2 text-right {{ (float) $line['capital_delta'] < 0 ? 'text-red-700' : 'text-emerald-700' }}">{{ $fmt($line['capital_delta']) }}</td>
                            <td class="px-4 py-2 text-right font-medium">{{ $fmt($line['balance']) }}</td>
                            <td class="px-4 py-2 text-slate-400">#{{ $line['batch_id'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">No transactions yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
