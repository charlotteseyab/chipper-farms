<x-app-layout>
    <div class="max-w-3xl mx-auto px-4 pt-6 pb-24 space-y-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Treatment Worklist</h1>
            <p class="text-sm text-slate-500">Doses due and follow-ups for {{ $isToday ? 'today' : $day->format('l j F') }}. Record what was given; mark anything not given as missed or cancelled with a reason.</p>
        </div>
        @if(session('success'))<p role="status" class="rounded-xl bg-emerald-50 p-3 text-sm text-emerald-800">{{ session('success') }}</p>@endif
        @if(session('error'))<p role="alert" class="rounded-xl bg-red-50 p-3 text-sm text-red-700">{{ session('error') }}</p>@endif

        <form method="GET" class="flex flex-wrap items-center gap-2">
            <a href="{{ route('health.worklist', ['date' => $day->subDay()->toDateString(), 'mine' => $mine ?: null]) }}" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm" aria-label="Previous day">←</a>
            <input type="date" name="date" value="{{ $day->toDateString() }}" onchange="this.form.submit()" class="rounded-lg border-slate-300 text-sm">
            <a href="{{ route('health.worklist', ['date' => $day->addDay()->toDateString(), 'mine' => $mine ?: null]) }}" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm" aria-label="Next day">→</a>
            @unless($isToday)<a href="{{ route('health.worklist', ['mine' => $mine ?: null]) }}" class="text-sm font-semibold text-emerald-700">Today</a>@endunless
            <label class="ml-auto inline-flex items-center gap-2 text-sm text-slate-600">
                <input type="checkbox" name="mine" value="1" @checked($mine) onchange="this.form.submit()" class="rounded border-slate-300"> Only mine
            </label>
        </form>

        @if($overdue->isNotEmpty())
            <section class="space-y-3">
                <h2 class="font-bold text-amber-800">Overdue from earlier days ({{ $overdue->sum(fn ($s) => $s['doses']->count()) }})</h2>
                @foreach($overdue as $slot) @include('health.partials.dose-slot', ['slot' => $slot, 'showDate' => true]) @endforeach
            </section>
        @endif

        <section class="space-y-3">
            <h2 class="font-bold text-slate-900">Doses due {{ $isToday ? 'today' : $day->format('j M') }}</h2>
            @forelse($slots as $slot)
                @include('health.partials.dose-slot', ['slot' => $slot])
            @empty
                <p class="rounded-2xl border border-dashed border-slate-200 p-6 text-center text-sm text-slate-500">No doses scheduled{{ $mine ? ' for courses you are responsible for' : '' }}.</p>
            @endforelse
        </section>

        <section id="follow-ups" class="space-y-3">
            <h2 class="font-bold text-slate-900">Follow-ups due</h2>
            @forelse($followUps as $item)
                <a href="{{ $item['url'] }}" class="flex items-start justify-between gap-3 rounded-2xl border bg-white p-4 shadow-sm hover:bg-slate-50 {{ $item['due_on']->lt($day) ? 'border-amber-300' : 'border-slate-100' }}">
                    <span class="min-w-0"><span class="block font-semibold text-slate-900">{{ $item['title'] }}</span>@if($item['detail'])<span class="block text-sm text-slate-500">{{ $item['detail'] }}</span>@endif</span>
                    <span class="whitespace-nowrap text-sm {{ $item['due_on']->lt($day) ? 'font-semibold text-amber-800' : 'text-slate-500' }}">{{ $item['due_on']->format('j M') }}</span>
                </a>
            @empty
                <p class="rounded-2xl border border-dashed border-slate-200 p-6 text-center text-sm text-slate-500">No follow-ups due.</p>
            @endforelse
        </section>
    </div>
</x-app-layout>
