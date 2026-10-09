<x-app-layout>
    <div class="max-w-6xl mx-auto px-4 pt-6 pb-24 space-y-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Vet Visits</h1>
                <p class="text-sm text-slate-500">Each visit and the animals the vet actually examined. Planned animals are not counted as examined.</p>
            </div>
            @can('create', \App\Models\VetVisit::class)<a href="{{ route('vet-visits.create') }}" class="rounded-xl bg-emerald-700 px-4 py-2 text-sm font-semibold text-white">Record a visit</a>@endcan
        </div>
        @if(session('success'))<p role="status" class="rounded-xl bg-emerald-50 p-3 text-sm text-emerald-800">{{ session('success') }}</p>@endif

        <form method="GET" class="grid gap-3 rounded-2xl border border-slate-100 bg-white p-4 shadow-sm sm:grid-cols-6 sm:items-end">
            <label class="text-sm text-slate-700">From<input type="date" name="from" value="{{ request('from') }}" class="mt-1 block w-full rounded-lg border-slate-300 text-sm"></label>
            <label class="text-sm text-slate-700">To<input type="date" name="to" value="{{ request('to') }}" class="mt-1 block w-full rounded-lg border-slate-300 text-sm"></label>
            <label class="text-sm text-slate-700">Vet<input name="vet" value="{{ request('vet') }}" class="mt-1 block w-full rounded-lg border-slate-300 text-sm"></label>
            <label class="text-sm text-slate-700">Animal code<input name="animal" value="{{ request('animal') }}" class="mt-1 block w-full rounded-lg border-slate-300 text-sm"></label>
            <label class="text-sm text-slate-700">Assessment
                <select name="assessment" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                    <option value="">Any</option>
                    @foreach(\App\Models\VetExamination::ASSESSMENTS as $value => $label)<option value="{{ $value }}" @selected(request('assessment') === $value)>{{ $label }}</option>@endforeach
                </select>
            </label>
            <button class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white">Filter</button>
        </form>

        <div class="overflow-x-auto rounded-2xl border border-slate-100 bg-white shadow-sm">
            <table class="w-full min-w-[640px] text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-4 py-3 text-left">Date</th><th class="px-4 py-3 text-left">Vet</th><th class="px-4 py-3 text-left">Reason</th><th class="px-4 py-3 text-right">Examined</th><th class="px-4 py-3 text-right">Planned</th><th class="px-4 py-3 text-left">Status</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($visits as $visit)
                        <tr>
                            <td class="px-4 py-3"><a href="{{ route('vet-visits.show', $visit) }}" class="font-semibold text-emerald-700 hover:underline">{{ $visit->visited_at->format('j M Y, H:i') }}</a></td>
                            <td class="px-4 py-3">{{ $visit->veterinarian_name }}<div class="text-xs text-slate-500">{{ $visit->clinic }}</div></td>
                            <td class="px-4 py-3 text-slate-600">{{ $visit->reason ?: '—' }}</td>
                            <td class="px-4 py-3 text-right font-mono">{{ $visit->examined_count }}</td>
                            <td class="px-4 py-3 text-right font-mono text-slate-500">{{ $visit->planned_animals_count }}</td>
                            <td class="px-4 py-3"><span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $visit->isFinalised() ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">{{ $visit->isFinalised() ? 'Finalised' : 'Draft' }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-10 text-center text-slate-500">No vet visits recorded.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div>{{ $visits->links() }}</div>
    </div>
</x-app-layout>
