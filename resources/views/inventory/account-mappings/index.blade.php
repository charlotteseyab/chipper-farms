<x-app-layout>
    <div class="max-w-7xl mx-auto py-6 px-4">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-800">Inventory Account Mappings</h1>
                <p class="text-sm text-slate-500">GL routing policy for inventory movements per item kind and category.</p>
            </div>
            @if(auth()->user()?->hasPermission('manage_inventory_mappings'))
                <a href="{{ route('inventory.account-mappings.create') }}"
                   class="px-3 py-2 text-sm font-semibold bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl">
                    + New mapping
                </a>
            @endif
        </div>

        @if(session('success'))<div class="mb-4 px-4 py-3 rounded-xl bg-emerald-50 text-emerald-700 text-sm">{{ session('success') }}</div>@endif
        @if(session('warning'))<div class="mb-4 px-4 py-3 rounded-xl bg-amber-50 text-amber-700 text-sm">{{ session('warning') }}</div>@endif

        <div class="rounded-2xl border border-slate-100 bg-white shadow-sm overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-3 py-2 text-left">Kind</th>
                        <th class="px-3 py-2 text-left">Category</th>
                        <th class="px-3 py-2 text-left">Inventory</th>
                        <th class="px-3 py-2 text-left">GR/IR</th>
                        <th class="px-3 py-2 text-left">Suspense</th>
                        <th class="px-3 py-2 text-left">Adjustment</th>
                        <th class="px-3 py-2 text-left">COGS</th>
                        <th class="px-3 py-2 text-left">PPV</th>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($mappings as $m)
                        <tr class="hover:bg-slate-50">
                            <td class="px-3 py-2 text-slate-700">{{ str_replace('_', ' ', $m->item_kind) }}</td>
                            <td class="px-3 py-2 text-slate-800 font-medium">
                                @if($m->isDefaultRow())
                                    <span class="px-2 py-0.5 bg-slate-100 text-slate-700 text-xs rounded-full">default</span>
                                @else
                                    {{ $m->category }}
                                @endif
                            </td>
                            <td class="px-3 py-2 text-slate-600 text-xs">{{ optional($m->inventoryAccount)->code ?? '—' }}</td>
                            <td class="px-3 py-2 text-slate-600 text-xs">{{ optional($m->grIrAccount)->code ?? '—' }}</td>
                            <td class="px-3 py-2 text-slate-600 text-xs">{{ optional($m->suspenseAccount)->code ?? '—' }}</td>
                            <td class="px-3 py-2 text-slate-600 text-xs">{{ optional($m->adjustmentAccount)->code ?? '—' }}</td>
                            <td class="px-3 py-2 text-slate-600 text-xs">{{ optional($m->cogsAccount)->code ?? '—' }}</td>
                            <td class="px-3 py-2 text-slate-600 text-xs">{{ optional($m->ppvAccount)->code ?? '—' }}</td>
                            <td class="px-3 py-2 text-right">
                                @if(auth()->user()?->hasPermission('manage_inventory_mappings'))
                                    <a href="{{ route('inventory.account-mappings.edit', $m) }}" class="text-xs text-slate-600 hover:underline mr-2">Edit</a>
                                    <form method="POST" action="{{ route('inventory.account-mappings.destroy', $m) }}" class="inline" onsubmit="return confirm('Delete this mapping?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-xs text-red-600 hover:underline">Delete</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-3 py-10 text-center text-slate-500">No mappings configured — Phase 4 will use fallback COA codes.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
