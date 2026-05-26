<x-app-layout>
    <div class="max-w-7xl mx-auto py-6 px-4">
        <div class="mb-6 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Chart of Accounts</h1>
                <p class="text-sm text-gray-500">Manage the general ledger account structure.</p>
            </div>
            @if(auth()->user()?->hasPermission('manage_coa'))
                <a href="{{ route('finance.coa.create') }}"
                   class="inline-flex items-center px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-xl shadow-sm transition">
                    + New Account
                </a>
            @endif
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-5">
                <p class="text-xs text-gray-500 uppercase tracking-wide">Total</p>
                <h3 class="mt-1 text-3xl font-bold text-slate-800">{{ $stats['total'] }}</h3>
            </div>
            <div class="rounded-2xl border border-emerald-100 bg-white shadow-sm p-5">
                <p class="text-xs text-gray-500 uppercase tracking-wide">Active</p>
                <h3 class="mt-1 text-3xl font-bold text-emerald-700">{{ $stats['active'] }}</h3>
            </div>
            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-5">
                <p class="text-xs text-gray-500 uppercase tracking-wide">Inactive</p>
                <h3 class="mt-1 text-3xl font-bold text-slate-500">{{ $stats['inactive'] }}</h3>
            </div>
            <div class="rounded-2xl border border-blue-100 bg-white shadow-sm p-5">
                <p class="text-xs text-gray-500 uppercase tracking-wide">Control Accounts</p>
                <h3 class="mt-1 text-3xl font-bold text-blue-700">{{ $stats['control'] }}</h3>
            </div>
        </div>

        <form method="GET" class="mb-4 flex flex-col sm:flex-row gap-2">
            <input type="text" name="search" value="{{ $search }}" placeholder="Search by code or name..."
                   class="flex-1 rounded-xl border-slate-200 text-sm">

            <select name="type" class="rounded-xl border-slate-200 text-sm">
                <option value="">All types</option>
                @foreach(['asset', 'liability', 'equity', 'revenue', 'expense'] as $t)
                    <option value="{{ $t }}" @selected($type === $t)>{{ ucfirst($t) }}</option>
                @endforeach
            </select>

            <label class="inline-flex items-center gap-2 text-sm text-slate-600">
                <input type="checkbox" name="inactive" value="1" @checked($showInactive)
                       class="rounded border-slate-300 text-emerald-600">
                Show inactive
            </label>

            <button type="submit" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-medium rounded-xl">Filter</button>
        </form>

        <div class="rounded-2xl border border-slate-100 bg-white shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-600">
                        <tr>
                            <th class="px-4 py-3 text-left">Code</th>
                            <th class="px-4 py-3 text-left">Name</th>
                            <th class="px-4 py-3 text-left">Type</th>
                            <th class="px-4 py-3 text-left">Currency</th>
                            <th class="px-4 py-3 text-left">Flags</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($accounts as $acc)
                            <tr class="hover:bg-slate-50 {{ $acc->is_active ? '' : 'opacity-60' }}">
                                <td class="px-4 py-3 font-mono text-xs text-slate-700">{{ $acc->code }}</td>
                                <td class="px-4 py-3 text-slate-800">
                                    <a href="{{ route('finance.coa.show', $acc) }}" class="hover:underline">{{ $acc->name }}</a>
                                    @if($acc->parent_id)
                                        <span class="text-xs text-gray-400 ml-1">(child of {{ $acc->parent->code ?? '?' }})</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <span class="text-xs text-slate-600 capitalize">{{ $acc->account_type }}</span>
                                </td>
                                <td class="px-4 py-3 text-xs text-slate-600">{{ $acc->currency_code }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap gap-1">
                                        @if($acc->is_control)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-blue-100 text-blue-800">control</span>
                                        @endif
                                        @if($acc->is_system)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-slate-200 text-slate-700">system</span>
                                        @endif
                                        @if(! $acc->allow_manual_journal)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-amber-100 text-amber-800">no manual</span>
                                        @endif
                                        @if(! $acc->is_active)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-red-100 text-red-700">inactive</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-right text-xs">
                                    <a href="{{ route('finance.coa.show', $acc) }}" class="text-emerald-700 hover:underline">View</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center px-4 py-8 text-gray-500">No accounts found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-4 py-3 border-t border-slate-100 bg-slate-50">
                {{ $accounts->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
