<x-app-layout>
    <div class="max-w-4xl mx-auto py-6 px-4">
        <div class="mb-6">
            <a href="{{ route('finance.close.show', $run->fiscal_period_id) }}" class="text-sm text-blue-600 hover:underline">← Back to period</a>
            <h1 class="text-2xl font-bold text-gray-800 mt-1">Close Pack — Run #{{ $run->id }}</h1>
            <p class="text-sm text-gray-500">
                {{ $run->fiscalPeriod?->name }} ·
                {{ ucfirst($run->status) }} ({{ ucfirst($run->close_level) }}) ·
                Closed {{ $run->completed_at?->format('Y-m-d H:i') }} by {{ $run->completedBy?->name ?? '—' }}
            </p>
        </div>

        @php $fmt = fn ($v) => number_format((float) $v, 2); @endphp

        {{-- Checks summary --}}
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden mb-4">
            <div class="bg-slate-50 px-4 py-3 font-bold text-slate-700">Checklist at Close</div>
            <table class="w-full text-sm">
                <tbody class="divide-y divide-slate-50">
                    @foreach($run->checks as $check)
                        <tr>
                            <td class="px-4 py-2 text-slate-700">
                                {{ $check->label }}
                                @if($check->override_reason)
                                    <div class="text-xs text-amber-600 mt-0.5">Override: {{ $check->override_reason }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-right">
                                @php
                                    $b = match($check->status) {
                                        'pass' => 'bg-emerald-50 text-emerald-700', 'overridden' => 'bg-amber-50 text-amber-700',
                                        'skipped' => 'bg-slate-100 text-slate-500', default => 'bg-red-50 text-red-700',
                                    };
                                @endphp
                                <span class="px-2 py-1 rounded-lg text-xs font-medium {{ $b }}">{{ ucfirst($check->status) }}</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Snapshotted statements --}}
        @php
            $income = $statements['income_statement'] ?? null;
            $balance = $statements['balance_sheet'] ?? null;
            $cash = $statements['cash_flow'] ?? null;
        @endphp

        @if($income)
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 mb-4">
                <h2 class="font-bold text-slate-700 mb-2">Income Statement (frozen)</h2>
                <div class="grid grid-cols-2 gap-2 text-sm">
                    <div class="text-slate-500">Revenue</div><div class="text-right">{{ $fmt($income['totals']['revenue'] ?? 0) }}</div>
                    <div class="text-slate-500">Cost of Sales</div><div class="text-right">{{ $fmt($income['totals']['cost_of_sales'] ?? 0) }}</div>
                    <div class="text-slate-500 font-medium">Gross Profit</div><div class="text-right font-medium">{{ $fmt($income['gross_profit'] ?? 0) }}</div>
                    <div class="text-slate-500">Operating Expenses</div><div class="text-right">{{ $fmt($income['totals']['opex'] ?? 0) }}</div>
                    <div class="text-slate-800 font-bold">Net Profit</div><div class="text-right font-bold">{{ $fmt($income['net_profit'] ?? 0) }}</div>
                </div>
            </div>
        @endif

        @if($balance)
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 mb-4">
                <h2 class="font-bold text-slate-700 mb-2">Balance Sheet (frozen)</h2>
                <div class="grid grid-cols-2 gap-2 text-sm">
                    <div class="text-slate-500">Total Assets</div><div class="text-right">{{ $fmt($balance['assets']['total'] ?? 0) }}</div>
                    <div class="text-slate-500">Total Liabilities</div><div class="text-right">{{ $fmt($balance['liabilities']['total'] ?? 0) }}</div>
                    <div class="text-slate-500">Total Equity</div><div class="text-right">{{ $fmt($balance['equity']['total'] ?? 0) }}</div>
                    <div class="text-slate-800 font-bold">Balanced?</div><div class="text-right font-bold">{{ ($balance['balanced'] ?? false) ? 'Yes' : 'No' }}</div>
                </div>
            </div>
        @endif

        @if($cash)
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4">
                <h2 class="font-bold text-slate-700 mb-2">Cash Flow (frozen)</h2>
                <div class="grid grid-cols-2 gap-2 text-sm">
                    <div class="text-slate-500">Operating</div><div class="text-right">{{ $fmt($cash['operating']['subtotal'] ?? 0) }}</div>
                    <div class="text-slate-500">Investing</div><div class="text-right">{{ $fmt($cash['investing']['subtotal'] ?? 0) }}</div>
                    <div class="text-slate-500">Financing</div><div class="text-right">{{ $fmt($cash['financing']['subtotal'] ?? 0) }}</div>
                    <div class="text-slate-800 font-bold">Net Change in Cash</div><div class="text-right font-bold">{{ $fmt($cash['net_change_in_cash'] ?? 0) }}</div>
                </div>
            </div>
        @endif
    </div>
</x-app-layout>
