<x-app-layout>
    <div class="max-w-7xl mx-auto py-6 px-4">
        <div class="mb-6 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Suppliers</h1>
                <p class="text-sm text-gray-500">Vendor master — feed suppliers, vets, utilities, capex, services.</p>
            </div>
            @if(auth()->user()?->hasPermission('manage_suppliers'))
                <a href="{{ route('finance.suppliers.create') }}"
                   class="inline-flex items-center px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-xl shadow-sm">
                    + New Supplier
                </a>
            @endif
        </div>

        @if(session('success'))<div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="mb-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ session('error') }}</div>@endif

        <div class="grid grid-cols-3 gap-4 mb-4">
            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-5">
                <p class="text-xs text-slate-500 uppercase tracking-wide">Total</p>
                <h3 class="mt-1 text-3xl font-bold text-slate-700">{{ $counts['total'] }}</h3>
            </div>
            <div class="rounded-2xl border border-emerald-100 bg-white shadow-sm p-5">
                <p class="text-xs text-slate-500 uppercase tracking-wide">Active</p>
                <h3 class="mt-1 text-3xl font-bold text-emerald-700">{{ $counts['active'] }}</h3>
            </div>
            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-5">
                <p class="text-xs text-slate-500 uppercase tracking-wide">Inactive</p>
                <h3 class="mt-1 text-3xl font-bold text-slate-500">{{ $counts['inactive'] }}</h3>
            </div>
        </div>

        <form method="GET" class="rounded-2xl border border-slate-100 bg-white shadow-sm p-4 mb-4">
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-3 items-end">
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-slate-600 mb-1">Search</label>
                    <input type="text" name="search" value="{{ $search }}" placeholder="Name, code, phone, email, tax ID..." class="w-full rounded-xl border-slate-200 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Category</label>
                    <select name="category" class="w-full rounded-xl border-slate-200 text-sm">
                        <option value="">All</option>
                        @foreach($categories as $c)
                            <option value="{{ $c->id }}" @selected($categoryId == $c->id)>{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex flex-col gap-1">
                    <label class="text-xs font-medium text-slate-600">
                        <input type="checkbox" name="inactive" value="1" @checked($showInactive) class="rounded border-slate-300"> Show inactive
                    </label>
                    <button type="submit" class="px-3 py-1.5 bg-slate-800 hover:bg-slate-900 text-white text-sm font-medium rounded-xl">Filter</button>
                </div>
            </div>
        </form>

        <div class="rounded-2xl border border-slate-100 bg-white shadow-sm overflow-hidden">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-600">
                    <tr>
                        <th class="px-4 py-3 text-left">Code / Name</th>
                        <th class="px-4 py-3 text-left">Category</th>
                        <th class="px-4 py-3 text-left">Contact</th>
                        <th class="px-4 py-3 text-right">Terms</th>
                        <th class="px-4 py-3 text-right">WHT</th>
                        <th class="px-4 py-3 text-right">Total Spend</th>
                        <th class="px-4 py-3 text-left">Status</th>
                        <th class="px-4 py-3 text-right"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($suppliers as $s)
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3">
                                <a href="{{ route('finance.suppliers.show', $s) }}" class="font-medium text-slate-800 hover:underline">{{ $s->name }}</a>
                                <div class="text-xs text-slate-400 font-mono">{{ $s->code }}</div>
                                @if($s->type === 'both')<span class="inline-block mt-0.5 px-1.5 py-0.5 rounded-full bg-purple-100 text-purple-800 text-xs">also customer</span>@endif
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-600">
                                {{ $s->supplierCategory?->name ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-xs">
                                @if($s->phone)<div class="text-slate-700">{{ $s->phone }}</div>@endif
                                @if($s->email)<div class="text-slate-500">{{ $s->email }}</div>@endif
                                @if(! $s->phone && ! $s->email)<span class="text-slate-300">—</span>@endif
                            </td>
                            <td class="px-4 py-3 text-right text-xs text-slate-600">{{ $s->payment_terms_days ?? '—' }}d</td>
                            <td class="px-4 py-3 text-right text-xs">
                                @if($s->withholding_exempt)
                                    <span class="text-emerald-700">exempt</span>
                                @elseif($s->withholding_percent !== null)
                                    {{ $s->withholding_percent }}%
                                @else
                                    <span class="text-slate-300">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right text-xs">
                                @if((float) $s->total_purchases_value > 0)
                                    {{ $s->currency_code }} {{ number_format((float) $s->total_purchases_value, 2) }}
                                @else
                                    <span class="text-slate-300">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @if($s->is_active)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">active</span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-600">inactive</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right text-xs">
                                <a href="{{ route('finance.suppliers.show', $s) }}" class="text-emerald-700 hover:underline font-medium">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center px-4 py-8 text-gray-500">No suppliers match your filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <div class="px-4 py-3 border-t border-slate-100 bg-slate-50">{{ $suppliers->links() }}</div>
        </div>
    </div>
</x-app-layout>
