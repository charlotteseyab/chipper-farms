<x-app-layout>
    <div class="max-w-3xl mx-auto px-4 pt-6 pb-24 space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Health alerts</h1>
                <p class="text-sm text-slate-500">Vaccinations, deworming, treatments and follow-ups that are overdue, due today or due in the next {{ $days }} days, and animals whose withdrawal is ending.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('health.worklist') }}" class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white">Treatment worklist</a>
                <a href="{{ route('health-programmes.index') }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700">Herd health programme</a>
            </div>
        </div>

        <div class="grid grid-cols-3 gap-3">
            @foreach(\App\Services\Health\HealthAlertService::STATES as $key => $label)
                <a href="{{ route('health.alerts', array_filter(['type' => $type, 'state' => $state === $key ? null : $key, 'days' => $days])) }}"
                   class="rounded-xl border bg-white p-4 shadow-sm transition hover:shadow {{ $state === $key ? 'border-emerald-400' : 'border-slate-100' }}">
                    <p class="text-3xl font-bold tabular-nums {{ ($states[$key] ?? 0) === 0 ? 'text-slate-400' : ($key === 'overdue' ? 'text-red-700' : 'text-slate-900') }}">{{ $states[$key] ?? 0 }}</p>
                    <p class="mt-1 text-sm text-slate-600">{{ $label }}</p>
                </a>
            @endforeach
        </div>

        <form method="GET" class="flex flex-wrap items-center gap-2">
            <a href="{{ route('health.alerts', array_filter(['state' => $state, 'days' => $days])) }}"
               class="rounded-full px-3 py-1.5 text-sm font-semibold {{ $type === null ? 'bg-slate-900 text-white' : 'bg-white text-slate-700 ring-1 ring-slate-200' }}">All ({{ $total }})</a>
            @foreach(\App\Services\Health\HealthAlertService::TYPES as $key => $label)
                <a href="{{ route('health.alerts', array_filter(['type' => $key, 'state' => $state, 'days' => $days])) }}"
                   class="rounded-full px-3 py-1.5 text-sm font-semibold {{ $type === $key ? 'bg-slate-900 text-white' : 'bg-white text-slate-700 ring-1 ring-slate-200' }}">{{ $label }} ({{ $counts[$key] ?? 0 }})</a>
            @endforeach
            <label class="ml-auto inline-flex items-center gap-2 text-sm text-slate-600">
                Look ahead
                <select name="days" onchange="this.form.submit()" class="rounded-lg border-slate-300 text-sm">
                    @foreach([3, 7, 14, 30] as $option)<option value="{{ $option }}" @selected($days === $option)>{{ $option }} days</option>@endforeach
                </select>
            </label>
            @if($type)<input type="hidden" name="type" value="{{ $type }}">@endif
            @if($state)<input type="hidden" name="state" value="{{ $state }}">@endif
        </form>

        @foreach(\App\Services\Health\HealthAlertService::STATES as $key => $label)
            @continue($state !== null && $state !== $key)
            <section class="space-y-3" id="{{ $key }}">
                <h2 class="font-bold {{ $key === 'overdue' ? 'text-red-800' : 'text-slate-900' }}">{{ $label }} ({{ ($alerts[$key] ?? collect())->count() }})</h2>
                <x-health.alert-list :alerts="$alerts[$key] ?? collect()" :empty="match ($key) { 'overdue' => 'Nothing overdue.', 'today' => 'Nothing else due today.', default => 'Nothing due in the next '.$days.' days.' }" />
            </section>
        @endforeach
    </div>
</x-app-layout>
