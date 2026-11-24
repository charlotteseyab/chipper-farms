<x-app-layout>
    <x-feed-hub hub="store" />
    @php
        $qty = fn ($value) => \App\Support\Qty::kg((float) $value);
    @endphp
    <div class="max-w-5xl mx-auto px-4 pt-6 pb-24">
        <div class="mb-6">
            <a href="{{ route('stock-counts.index') }}" class="text-sm text-slate-500 hover:text-slate-700">← Back to stock counts</a>
            <h1 class="mt-2 text-2xl font-bold text-slate-900">Count Stock</h1>
            <p class="text-sm text-slate-500">Weigh or count what is physically in the store and enter it. Leave an item blank to skip it. Saving sets the system stock to what you counted. If stock moved after the count time, recount that item before saving.</p>
        </div>

        @if($errors->any())
            <ul role="alert" class="mb-4 list-disc list-inside rounded-xl bg-red-50 p-4 text-sm text-red-800">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        @endif

        <form method="POST" action="{{ route('stock-counts.store') }}" class="space-y-5"
              x-data="{ counts: @js((object) old('counts', [])) }">
            @csrf
            <input type="hidden" name="submission_key" value="{{ old('submission_key', $submissionKey) }}">

            <div class="grid gap-4 rounded-2xl border border-slate-100 bg-white p-4 shadow-sm sm:grid-cols-2">
                <label class="text-xs font-semibold uppercase text-slate-500">Counted at
                    <input type="datetime-local" name="counted_at" step="1" value="{{ old('counted_at', now()->format('Y-m-d\TH:i:s')) }}" max="{{ now()->format('Y-m-d\TH:i:s') }}"
                           class="mt-1 block w-full rounded-lg border-slate-200 text-sm font-normal normal-case text-slate-800">
                </label>
                <label class="text-xs font-semibold uppercase text-slate-500">Reference (optional)
                    <input type="text" name="reference" value="{{ old('reference') }}" maxlength="200" placeholder="e.g. Month-end count"
                           class="mt-1 block w-full rounded-lg border-slate-200 text-sm font-normal normal-case text-slate-800">
                </label>
            </div>

            @foreach($sections as $kind => $section)
                <section class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm">
                    <h2 class="border-b border-slate-100 px-4 py-3 font-bold text-slate-900">{{ $section['title'] }}</h2>
                    @forelse($section['items'] as $item)
                        @php $key = $kind.'.'.$item['id']; @endphp
                        <div class="grid grid-cols-[1fr_8rem] items-center gap-3 border-b border-slate-50 px-4 py-3 last:border-b-0 sm:grid-cols-[1fr_8rem_8rem_8rem]"
                             x-data="{ system: {{ (float) $item['system'] }}, get counted() { const v = (counts['{{ $kind }}'] ?? {})['{{ $item['id'] }}']; return v === undefined || v === '' ? null : parseFloat(v); } }">
                            <div>
                                <p class="font-semibold text-slate-900">{{ $item['name'] }}</p>
                                <p class="text-xs text-slate-500 sm:hidden">System: {{ $qty($item['system']) }} {{ $item['unit'] }}</p>
                            </div>
                            <p class="hidden text-right text-sm text-slate-500 sm:block">
                                <span class="block text-[11px] uppercase tracking-wide">System</span>
                                <span class="font-mono text-slate-700">{{ $qty($item['system']) }}</span> {{ $item['unit'] }}
                            </p>
                            <label class="relative block">
                                <span class="sr-only">Counted {{ $item['name'] }} in {{ $item['unit'] }}</span>
                                <input type="number" step="0.0001" min="0" inputmode="decimal" name="counts[{{ $kind }}][{{ $item['id'] }}]"
                                       value="{{ old('counts.'.$key) }}"
                                       @input="counts['{{ $kind }}'] = { ...(counts['{{ $kind }}'] ?? {}), '{{ $item['id'] }}': $event.target.value }"
                                       placeholder="Counted"
                                       class="w-full rounded-lg border-slate-200 pr-12 text-right font-mono text-sm">
                                <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs text-slate-400">{{ \Illuminate\Support\Str::limit($item['unit'], 5, '') }}</span>
                            </label>
                            <p class="col-span-2 text-right text-sm font-semibold sm:col-span-1">
                                <template x-if="counted !== null">
                                    <span :class="Math.abs(counted - system) < 0.00005 ? 'text-emerald-700' : (counted < system ? 'text-red-700' : 'text-amber-700')"
                                          x-text="Math.abs(counted - system) < 0.00005 ? 'Matches' : ((counted > system ? '+' : '') + (counted - system).toFixed(2) + ' {{ $item['unit'] }}')"></span>
                                </template>
                            </p>
                        </div>
                    @empty
                        <p class="px-4 py-6 text-sm text-slate-500">Nothing to count.</p>
                    @endforelse
                </section>
            @endforeach

            <label class="block text-xs font-semibold uppercase text-slate-500">Notes (optional)
                <textarea name="notes" rows="2" maxlength="2000" class="mt-1 block w-full rounded-lg border-slate-200 text-sm font-normal normal-case text-slate-800">{{ old('notes') }}</textarea>
            </label>

            <p class="text-sm text-slate-500">Shortages and surpluses are valued at average cost and recorded as stock adjustments.</p>
            <button class="rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700">Save count</button>
        </form>
    </div>
</x-app-layout>
