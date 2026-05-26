<x-app-layout>
    <div class="max-w-5xl mx-auto py-6 px-4">
        <div class="mb-6">
            <a href="{{ route('finance.loans.index') }}" class="text-sm text-blue-600 hover:underline">← Loans</a>
            <h1 class="text-2xl font-bold text-gray-800 mt-1">{{ $loan->reference }} — {{ $loan->lender_name }}</h1>
            <p class="text-sm text-gray-500">{{ ucfirst($loan->loan_type) }} · {{ ucfirst($loan->status) }} · {{ ucfirst(str_replace('_',' ',$loan->amortisation_method)) }}</p>
        </div>

        @if(session('success'))<div class="mb-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 text-sm">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="mb-4 rounded-xl bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">{{ session('error') }}</div>@endif

        @php $fmt = fn ($v) => number_format((float) $v, 2); @endphp

        {{-- Summary --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-5">
            @foreach([
                'Principal' => $loan->principal_amount,
                'Outstanding' => $loan->principal_outstanding,
                'Accrued interest' => $loan->interest_accrued_outstanding,
                'Interest charged' => $loan->total_interest_charged,
            ] as $label => $val)
                <div class="bg-white rounded-2xl border border-slate-100 p-4">
                    <div class="text-xs text-slate-500">{{ $label }}</div>
                    <div class="text-lg font-bold text-slate-800">{{ $fmt($val) }}</div>
                </div>
            @endforeach
        </div>

        {{-- Actions --}}
        @can('manage_loans')
            <div class="grid md:grid-cols-2 gap-4 mb-6">
                @if($loan->status === 'draft')
                    <form method="POST" action="{{ route('finance.loans.disburse', $loan) }}" class="bg-white rounded-2xl border border-slate-100 p-4">
                        @csrf
                        <h3 class="font-bold text-slate-700 mb-2">Disburse</h3>
                        <label class="block text-xs text-slate-600 mb-1">Disbursement date</label>
                        <input type="date" name="disbursement_date" value="{{ $loan->disbursement_date?->toDateString() }}" class="w-full rounded-xl border-slate-200 text-sm mb-3">
                        <button class="px-4 py-2 bg-emerald-600 text-white rounded-xl text-sm" onclick="return confirm('Post disbursement to the GL?')">Disburse loan</button>
                    </form>
                @elseif($loan->status === 'active')
                    <form method="POST" action="{{ route('finance.loans.repay', $loan) }}" class="bg-white rounded-2xl border border-slate-100 p-4">
                        @csrf
                        <h3 class="font-bold text-slate-700 mb-2">Record repayment</h3>
                        <div class="grid grid-cols-2 gap-2">
                            <div><label class="block text-xs text-slate-600 mb-1">Principal</label><input name="principal" type="number" step="0.01" min="0" value="0" class="w-full rounded-xl border-slate-200 text-sm"></div>
                            <div><label class="block text-xs text-slate-600 mb-1">Interest</label><input name="interest" type="number" step="0.01" min="0" value="0" class="w-full rounded-xl border-slate-200 text-sm"></div>
                            <div><label class="block text-xs text-slate-600 mb-1">Fee</label><input name="fee" type="number" step="0.01" min="0" value="0" class="w-full rounded-xl border-slate-200 text-sm"></div>
                            <div><label class="block text-xs text-slate-600 mb-1">Penalty</label><input name="penalty" type="number" step="0.01" min="0" value="0" class="w-full rounded-xl border-slate-200 text-sm"></div>
                        </div>
                        <label class="block text-xs text-slate-600 mt-2 mb-1">Apply to schedule period (optional)</label>
                        <select name="schedule_line_id" class="w-full rounded-xl border-slate-200 text-sm mb-2">
                            <option value="">— none —</option>
                            @foreach($loan->scheduleLines->where('status','!=','paid') as $sl)
                                <option value="{{ $sl->id }}">P{{ $sl->period_number }} due {{ $sl->due_date->toDateString() }} ({{ $fmt($sl->total_due) }})</option>
                            @endforeach
                        </select>
                        <button class="px-4 py-2 bg-slate-800 text-white rounded-xl text-sm">Record repayment</button>
                    </form>
                @endif
            </div>
        @endcan

        {{-- Schedule --}}
        <h2 class="text-lg font-bold text-slate-700 mb-2">Amortisation Schedule</h2>
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden mb-6">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-600">
                    <tr>
                        <th class="px-3 py-2 text-left">#</th>
                        <th class="px-3 py-2 text-left">Due</th>
                        <th class="px-3 py-2 text-right">Opening</th>
                        <th class="px-3 py-2 text-right">Interest</th>
                        <th class="px-3 py-2 text-right">Principal</th>
                        <th class="px-3 py-2 text-right">Total</th>
                        <th class="px-3 py-2 text-right">Closing</th>
                        <th class="px-3 py-2 text-left">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @foreach($loan->scheduleLines as $sl)
                        <tr>
                            <td class="px-3 py-2">{{ $sl->period_number }}</td>
                            <td class="px-3 py-2 text-slate-500">{{ $sl->due_date->toDateString() }}</td>
                            <td class="px-3 py-2 text-right">{{ $fmt($sl->opening_balance) }}</td>
                            <td class="px-3 py-2 text-right">{{ $fmt($sl->interest_due) }}</td>
                            <td class="px-3 py-2 text-right">{{ $fmt($sl->principal_due) }}</td>
                            <td class="px-3 py-2 text-right font-medium">{{ $fmt($sl->total_due) }}</td>
                            <td class="px-3 py-2 text-right">{{ $fmt($sl->closing_balance) }}</td>
                            <td class="px-3 py-2">
                                @php $b = match($sl->status){'paid'=>'bg-emerald-50 text-emerald-700','partially_paid'=>'bg-amber-50 text-amber-700',default=>'bg-slate-100 text-slate-500'}; @endphp
                                <span class="px-2 py-0.5 rounded text-xs {{ $b }}">{{ str_replace('_',' ',$sl->status) }}</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Transactions --}}
        <h2 class="text-lg font-bold text-slate-700 mb-2">Transactions</h2>
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-600">
                    <tr>
                        <th class="px-3 py-2 text-left">Date</th>
                        <th class="px-3 py-2 text-left">Type</th>
                        <th class="px-3 py-2 text-right">Principal</th>
                        <th class="px-3 py-2 text-right">Interest</th>
                        <th class="px-3 py-2 text-right">Fee</th>
                        <th class="px-3 py-2 text-right">Penalty</th>
                        <th class="px-3 py-2 text-right">Total</th>
                        <th class="px-3 py-2 text-left">Batch</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @forelse($loan->transactions as $txn)
                        <tr>
                            <td class="px-3 py-2 text-slate-500">{{ $txn->txn_date->toDateString() }}</td>
                            <td class="px-3 py-2">{{ ucfirst($txn->txn_type) }}</td>
                            <td class="px-3 py-2 text-right">{{ $fmt($txn->principal_amount) }}</td>
                            <td class="px-3 py-2 text-right">{{ $fmt($txn->interest_amount) }}</td>
                            <td class="px-3 py-2 text-right">{{ $fmt($txn->fee_amount) }}</td>
                            <td class="px-3 py-2 text-right">{{ $fmt($txn->penalty_amount) }}</td>
                            <td class="px-3 py-2 text-right font-medium">{{ $fmt($txn->total_amount) }}</td>
                            <td class="px-3 py-2 text-slate-400">#{{ $txn->journal_batch_id }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-3 py-6 text-center text-slate-400">No transactions yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
