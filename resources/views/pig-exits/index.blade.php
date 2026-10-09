<x-app-layout>
    <div class="max-w-6xl mx-auto px-4 pt-6 pb-24">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-slate-900">Transfers &amp; Culls</h1>
            <p class="text-sm text-slate-500">Animals that left the herd without a sale or a death. Start one from the pig's page.</p>
        </div>
        @if(session('success'))<p role="status" class="mb-4 rounded-xl bg-emerald-50 p-3 text-sm text-emerald-800">{{ session('success') }}</p>@endif

        <div class="mb-4 flex gap-2 text-sm">
            @foreach(['' => 'All'] + \App\Models\PigExit::TYPES as $value => $label)
                <a href="{{ route('pig-exits.index', array_filter(['type' => $value])) }}"
                   class="rounded-lg px-3 py-1.5 font-semibold {{ (string) request('type') === (string) $value ? 'bg-slate-900 text-white' : 'border text-slate-600' }}">{{ $label }}</a>
            @endforeach
        </div>

        <div class="overflow-x-auto rounded-2xl border border-slate-100 bg-white shadow-sm">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3 text-left">Date</th>
                        <th class="px-4 py-3 text-left">Pig</th>
                        <th class="px-4 py-3 text-left">Type</th>
                        <th class="px-4 py-3 text-left">Reason</th>
                        <th class="px-4 py-3 text-left">Destination / disposal</th>
                        <th class="px-4 py-3 text-right">Weight</th>
                        <th class="px-4 py-3 text-left">By</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($exits as $exit)
                        <tr>
                            <td class="px-4 py-3">{{ $exit->exit_date->format('j M Y') }}</td>
                            <td class="px-4 py-3">
                                <a href="{{ route('pigs.show', $exit->pig_id) }}" class="font-semibold text-emerald-700 hover:underline">{{ $exit->pig?->code }}</a>
                                <div class="text-xs text-slate-500">{{ $exit->pig?->identityAt($exit->exit_date) }}</div>
                            </td>
                            <td class="px-4 py-3"><span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $exit->exit_type === 'cull' ? 'bg-amber-100 text-amber-800' : 'bg-sky-100 text-sky-800' }}">{{ $exit->type_label }}</span></td>
                            <td class="px-4 py-3">{{ $exit->reason_label }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $exit->destination ?? $exit->disposal_label ?? '—' }}</td>
                            <td class="px-4 py-3 text-right font-mono">{{ $exit->final_weight !== null ? number_format((float) $exit->final_weight, 1).' kg' : '—' }}</td>
                            <td class="px-4 py-3 text-xs text-slate-500">{{ $exit->recorder?->name ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-10 text-center text-slate-500">No transfers or culls recorded.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $exits->links() }}</div>
    </div>
</x-app-layout>
