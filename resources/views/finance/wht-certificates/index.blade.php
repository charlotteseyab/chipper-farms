<x-app-layout>
    <div class="max-w-7xl mx-auto py-6 px-4">
        <div class="mb-6 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">WHT Certificates</h1>
                <p class="text-sm text-slate-500">Withholding tax certificates issued to suppliers per quarter (or other period).</p>
            </div>
            <a href="{{ route('finance.wht-certificates.create') }}"
               class="inline-flex items-center px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-xl">
                <i class="fa-solid fa-plus mr-2"></i> New Certificate
            </a>
        </div>

        @if (session('success'))
            <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="mb-4 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-800">{{ session('error') }}</div>
        @endif

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4">
            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-4">
                <p class="text-xs text-slate-500 uppercase tracking-wide">Total</p>
                <p class="text-xl font-bold text-slate-800 mt-1">{{ $counts['total'] }}</p>
            </div>
            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-4">
                <p class="text-xs text-slate-500 uppercase tracking-wide">Draft</p>
                <p class="text-xl font-bold text-slate-700 mt-1">{{ $counts['draft'] }}</p>
            </div>
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 shadow-sm p-4">
                <p class="text-xs text-emerald-700 uppercase tracking-wide font-semibold">Issued</p>
                <p class="text-xl font-bold text-emerald-700 mt-1">{{ $counts['issued'] }}</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-slate-50 shadow-sm p-4">
                <p class="text-xs text-slate-600 uppercase tracking-wide">Void</p>
                <p class="text-xl font-bold text-slate-600 mt-1">{{ $counts['void'] }}</p>
            </div>
        </div>

        <form method="GET" class="rounded-2xl border border-slate-100 bg-white shadow-sm p-4 mb-4">
            <div class="grid grid-cols-1 sm:grid-cols-5 gap-3 items-end">
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Status</label>
                    <select name="status" class="w-full rounded-xl border-slate-200 text-sm">
                        <option value="all" @selected($status === 'all')>All</option>
                        <option value="draft" @selected($status === 'draft')>Draft</option>
                        <option value="issued" @selected($status === 'issued')>Issued</option>
                        <option value="void" @selected($status === 'void')>Void</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Year</label>
                    <select name="year" class="w-full rounded-xl border-slate-200 text-sm">
                        <option value="">Any</option>
                        @foreach($years as $y)
                            <option value="{{ $y }}" @selected((int) $year === $y)>{{ $y }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Period</label>
                    <select name="period_index" class="w-full rounded-xl border-slate-200 text-sm">
                        <option value="">Any</option>
                        @for($i = 1; $i <= 4; $i++)
                            <option value="{{ $i }}" @selected((int) $index === $i)>Q{{ $i }}</option>
                        @endfor
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Supplier</label>
                    <select name="supplier_id" class="w-full rounded-xl border-slate-200 text-sm">
                        <option value="">All</option>
                        @foreach($suppliers as $s)
                            <option value="{{ $s->id }}" @selected((int) $supplierId === $s->id)>{{ $s->code }} — {{ $s->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="px-4 py-2 bg-slate-800 hover:bg-slate-900 text-white text-sm font-medium rounded-xl">Filter</button>
                    <a href="{{ route('finance.wht-certificates.index') }}" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-medium rounded-xl">Reset</a>
                </div>
            </div>
        </form>

        @if($certs->isEmpty())
            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-8 text-center">
                <p class="text-slate-600">No certificates yet.</p>
                <p class="text-xs text-slate-400 mt-1">Generate quarterly certificates after each quarter closes.</p>
            </div>
        @else
            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-600">
                            <tr>
                                <th class="px-4 py-3 text-left">Cert #</th>
                                <th class="px-4 py-3 text-left">Supplier</th>
                                <th class="px-4 py-3 text-left">Period</th>
                                <th class="px-4 py-3 text-right">Gross Paid</th>
                                <th class="px-4 py-3 text-right">Withheld</th>
                                <th class="px-4 py-3 text-left">Status</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($certs as $c)
                                @php
                                    $statusBadge = match ($c->status) {
                                        'draft'  => 'bg-slate-100 text-slate-700',
                                        'issued' => 'bg-emerald-100 text-emerald-800',
                                        'void'   => 'bg-slate-300 text-slate-600',
                                        default  => 'bg-slate-100 text-slate-700',
                                    };
                                @endphp
                                <tr class="hover:bg-slate-50">
                                    <td class="px-4 py-3 font-mono text-xs">{{ $c->certificate_number ?: '— (draft)' }}</td>
                                    <td class="px-4 py-3">
                                        <div class="font-medium text-slate-800">{{ $c->counterparty->name }}</div>
                                        <div class="text-xs text-slate-400 font-mono">{{ $c->counterparty->code }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-xs">{{ $c->periodLabel() }}</td>
                                    <td class="px-4 py-3 text-right text-slate-600">{{ number_format((float) $c->total_amount_paid, 2) }}</td>
                                    <td class="px-4 py-3 text-right font-bold text-slate-800">{{ number_format((float) $c->total_withheld, 2) }}</td>
                                    <td class="px-4 py-3">
                                        <span class="inline-block px-2 py-0.5 rounded-full text-xs font-medium {{ $statusBadge }}">
                                            {{ strtoupper($c->status) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        @if($c->isIssued() && $c->pdf_path)
                                            <a href="{{ route('finance.wht-certificates.pdf', $c) }}" class="text-slate-700 hover:underline text-sm mr-3" title="Download PDF">
                                                <i class="fa-solid fa-file-pdf"></i>
                                            </a>
                                        @endif
                                        <a href="{{ route('finance.wht-certificates.show', $c) }}" class="text-emerald-700 hover:underline text-sm">View →</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="mt-4">{{ $certs->links() }}</div>
        @endif
    </div>
</x-app-layout>
