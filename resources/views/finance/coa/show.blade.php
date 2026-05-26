<x-app-layout>
    <div class="max-w-7xl mx-auto py-6 px-4">
        <div class="mb-4">
            <a href="{{ route('finance.coa.index') }}" class="text-sm text-slate-500 hover:underline">← Back to Chart of Accounts</a>
        </div>

        <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-6 mb-6">
            <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
                <div>
                    <p class="text-xs text-gray-500 uppercase tracking-wide font-semibold">{{ $account->account_type }}</p>
                    <h1 class="mt-1 text-2xl font-bold text-slate-800">
                        <span class="font-mono text-xl text-slate-500 mr-2">{{ $account->code }}</span>
                        {{ $account->name }}
                    </h1>
                    @if($account->description)
                        <p class="mt-2 text-sm text-gray-600 max-w-2xl">{{ $account->description }}</p>
                    @endif

                    <div class="mt-3 flex flex-wrap gap-2">
                        @if($account->is_control)
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-blue-100 text-blue-800">control account</span>
                        @endif
                        @if($account->is_system)
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-slate-200 text-slate-700">system account</span>
                        @endif
                        @if(! $account->allow_manual_journal)
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-amber-100 text-amber-800">manual journals disallowed</span>
                        @endif
                        @if(! $account->is_active)
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-red-100 text-red-700">inactive</span>
                        @endif
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-slate-100 text-slate-700">
                            {{ $account->isDebitNormal() ? 'Debit-normal' : 'Credit-normal' }}
                        </span>
                    </div>
                </div>

                @if(auth()->user()?->hasPermission('manage_coa') && ! $account->is_system)
                    <div class="flex flex-col gap-2">
                        <a href="{{ route('finance.coa.edit', $account) }}" class="inline-flex items-center justify-center px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-medium rounded-xl">Edit</a>
                        <form method="POST" action="{{ route('finance.coa.toggle-active', $account) }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="w-full inline-flex items-center justify-center px-4 py-2 {{ $account->is_active ? 'bg-amber-100 hover:bg-amber-200 text-amber-800' : 'bg-emerald-100 hover:bg-emerald-200 text-emerald-800' }} text-sm font-medium rounded-xl">
                                {{ $account->is_active ? 'Deactivate' : 'Activate' }}
                            </button>
                        </form>
                    </div>
                @endif
            </div>
        </div>

        <div class="rounded-2xl border border-slate-100 bg-white shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100">
                <h3 class="text-sm font-semibold text-slate-700 uppercase tracking-wide">Recent Activity (last 50 lines)</h3>
            </div>
            @if($recentLines->isEmpty())
                <p class="p-8 text-center text-sm text-gray-500">No journal lines posted to this account yet.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-600">
                            <tr>
                                <th class="px-4 py-3 text-left">Date</th>
                                <th class="px-4 py-3 text-left">Journal</th>
                                <th class="px-4 py-3 text-left">Description</th>
                                <th class="px-4 py-3 text-right">Debit</th>
                                <th class="px-4 py-3 text-right">Credit</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($recentLines as $line)
                                <tr class="hover:bg-slate-50">
                                    <td class="px-4 py-3 text-slate-700">{{ $line->journal?->posting_date?->format('Y-m-d') }}</td>
                                    <td class="px-4 py-3 font-mono text-xs">{{ $line->journal?->journal_number }}</td>
                                    <td class="px-4 py-3 text-slate-700">{{ $line->description ?: '—' }}</td>
                                    <td class="px-4 py-3 text-right text-slate-800">
                                        {{ (float) $line->debit > 0 ? number_format((float) $line->debit, 2) : '' }}
                                    </td>
                                    <td class="px-4 py-3 text-right text-slate-800">
                                        {{ (float) $line->credit > 0 ? number_format((float) $line->credit, 2) : '' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
