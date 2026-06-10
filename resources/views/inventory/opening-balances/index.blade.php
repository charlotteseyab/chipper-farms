<x-app-layout>
    <div class="max-w-7xl mx-auto py-6 px-4">
        <div class="mb-6 flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-800">Inventory Opening Balances</h1>
                <p class="text-sm text-slate-500">Audit log of cutover imports.</p>
            </div>
            @if(auth()->user()?->hasPermission('manage_opening_balances'))
                <div class="flex gap-2">
                    <a href="{{ route('finance.inventory.opening-balances.create') }}"
                       class="px-3 py-2 text-sm font-semibold bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl">
                        + Manual entry
                    </a>
                    <a href="{{ route('finance.inventory.opening-balances.import.show') }}"
                       class="px-3 py-2 text-sm font-semibold bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 rounded-xl">
                        Upload CSV
                    </a>
                </div>
            @endif
        </div>

        @if(session('success'))<div class="mb-4 px-4 py-3 rounded-xl bg-emerald-50 text-emerald-700 text-sm">{{ session('success') }}</div>@endif
        @if(session('warning'))<div class="mb-4 px-4 py-3 rounded-xl bg-amber-50 text-amber-700 text-sm">{{ session('warning') }}</div>@endif
        @if(session('error'))<div class="mb-4 px-4 py-3 rounded-xl bg-red-50 text-red-700 text-sm">{{ session('error') }}</div>@endif

        <div class="rounded-2xl border border-slate-100 bg-white shadow-sm overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-3 py-2 text-left">When</th>
                        <th class="px-3 py-2 text-left">Kind</th>
                        <th class="px-3 py-2 text-left">Cutover</th>
                        <th class="px-3 py-2 text-right">Rows</th>
                        <th class="px-3 py-2 text-left">Mode</th>
                        <th class="px-3 py-2 text-left">By</th>
                        <th class="px-3 py-2 text-left">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($imports as $import)
                        <tr class="hover:bg-slate-50">
                            <td class="px-3 py-2 text-slate-600">
                                <a href="{{ route('finance.inventory.opening-balances.show', $import) }}" class="text-emerald-700 hover:underline">
                                    {{ $import->created_at?->format('Y-m-d H:i') }}
                                </a>
                            </td>
                            <td class="px-3 py-2">{{ str_replace('_', ' ', $import->item_kind) }}</td>
                            <td class="px-3 py-2">{{ $import->cutover_date?->format('Y-m-d') }}</td>
                            <td class="px-3 py-2 text-right">{{ $import->row_count }}</td>
                            <td class="px-3 py-2">{{ $import->import_mode }}</td>
                            <td class="px-3 py-2 text-xs">{{ optional($import->performedBy)->name ?? '—' }}</td>
                            <td class="px-3 py-2">
                                @php
                                    $badge = match($import->status) {
                                        'completed' => 'bg-emerald-50 text-emerald-700',
                                        'pending'   => 'bg-amber-50 text-amber-700',
                                        'failed'    => 'bg-red-50 text-red-700',
                                        'archived'  => 'bg-slate-100 text-slate-500',
                                    };
                                @endphp
                                <span class="px-2 py-0.5 text-xs font-semibold rounded-full {{ $badge }}">{{ $import->status }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-3 py-10 text-center text-slate-500">No imports yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $imports->links() }}</div>
    </div>
</x-app-layout>
