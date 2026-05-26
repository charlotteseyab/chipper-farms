<x-app-layout>
    <div class="max-w-7xl mx-auto py-6 px-4">
        <div class="mb-6 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Customers</h1>
                <p class="text-sm text-gray-500">Customer master — billing details, contacts, credit terms.</p>
            </div>
            @if(auth()->user()?->hasPermission('manage_customers'))
                <a href="{{ route('finance.customers.create') }}"
                   class="inline-flex items-center px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-xl shadow-sm transition">
                    + New Customer
                </a>
            @endif
        </div>

        @if(session('success'))
            <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="mb-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ session('error') }}</div>
        @endif

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-5">
                <p class="text-xs text-gray-500 uppercase tracking-wide">Total Customers</p>
                <h3 class="mt-1 text-3xl font-bold text-slate-800">{{ $stats['total'] }}</h3>
            </div>
            <div class="rounded-2xl border border-emerald-100 bg-white shadow-sm p-5">
                <p class="text-xs text-gray-500 uppercase tracking-wide">Active</p>
                <h3 class="mt-1 text-3xl font-bold text-emerald-700">{{ $stats['active'] }}</h3>
            </div>
            <div class="rounded-2xl border border-red-100 bg-white shadow-sm p-5">
                <p class="text-xs text-gray-500 uppercase tracking-wide">On Credit Hold</p>
                <h3 class="mt-1 text-3xl font-bold {{ $stats['on_hold'] > 0 ? 'text-red-700' : 'text-slate-400' }}">{{ $stats['on_hold'] }}</h3>
            </div>
            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-5">
                <p class="text-xs text-gray-500 uppercase tracking-wide">Walk-in (system)</p>
                <h3 class="mt-1 text-3xl font-bold text-slate-500">{{ $stats['walkin_count'] }}</h3>
            </div>
        </div>

        <form method="GET" class="mb-4 flex flex-wrap items-end gap-2">
            <div class="flex-1 min-w-[200px]">
                <label class="block text-xs text-slate-600 mb-1">Search</label>
                <input type="text" name="search" value="{{ $search }}" placeholder="Name, code, phone, email, TIN..."
                       class="w-full rounded-xl border-slate-200 text-sm">
            </div>
            <div>
                <label class="block text-xs text-slate-600 mb-1">Category</label>
                <select name="category" class="rounded-xl border-slate-200 text-sm">
                    <option value="">All categories</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}" @selected((int) $categoryId === (int) $cat->id)>{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>
            <label class="inline-flex items-center gap-2 text-sm text-slate-600 pt-5">
                <input type="checkbox" name="hold" value="1" @checked($onHoldOnly) class="rounded border-slate-300 text-red-600">
                Hold only
            </label>
            <label class="inline-flex items-center gap-2 text-sm text-slate-600 pt-5">
                <input type="checkbox" name="inactive" value="1" @checked($showInactive) class="rounded border-slate-300">
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
                            <th class="px-4 py-3 text-left">Category</th>
                            <th class="px-4 py-3 text-left">Phone / Email</th>
                            <th class="px-4 py-3 text-left">Terms</th>
                            <th class="px-4 py-3 text-right">Credit Limit</th>
                            <th class="px-4 py-3 text-left">Status</th>
                            <th class="px-4 py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($customers as $c)
                            <tr class="hover:bg-slate-50 {{ $c->is_active ? '' : 'opacity-60' }}">
                                <td class="px-4 py-3 font-mono text-xs text-slate-600">{{ $c->code }}</td>
                                <td class="px-4 py-3">
                                    <a href="{{ route('finance.customers.show', $c) }}" class="text-slate-800 hover:underline font-medium">{{ $c->name }}</a>
                                    @if($c->legal_name && $c->legal_name !== $c->name)
                                        <p class="text-xs text-slate-400">{{ $c->legal_name }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-xs text-slate-600">{{ $c->category?->name ?? '—' }}</td>
                                <td class="px-4 py-3 text-xs text-slate-600">
                                    @if($c->phone)<div>{{ $c->phone }}</div>@endif
                                    @if($c->email)<div class="text-slate-400">{{ $c->email }}</div>@endif
                                </td>
                                <td class="px-4 py-3 text-xs text-slate-600">
                                    {{ $c->effectivePaymentTermsDays() !== null ? 'Net ' . $c->effectivePaymentTermsDays() : 'COD' }}
                                </td>
                                <td class="px-4 py-3 text-right text-slate-700">
                                    @php $limit = $c->effectiveCreditLimit(); @endphp
                                    {{ $limit !== null ? $c->currency_code . ' ' . number_format((float) $limit, 2) : '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-col gap-1">
                                        @if(! $c->is_active)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-slate-200 text-slate-700 w-fit">inactive</span>
                                        @endif
                                        @if($c->on_credit_hold)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-red-100 text-red-800 w-fit">on hold</span>
                                        @endif
                                        @if($c->is_active && ! $c->on_credit_hold)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-emerald-100 text-emerald-800 w-fit">active</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-right text-xs">
                                    <a href="{{ route('finance.customers.show', $c) }}" class="text-emerald-700 hover:underline font-medium">View</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center px-4 py-8 text-gray-500">No customers found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-4 py-3 border-t border-slate-100 bg-slate-50">
                {{ $customers->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
