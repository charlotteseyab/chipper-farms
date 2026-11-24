<x-app-layout>
    @php
        $hour = now()->hour;
        $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
        $name = auth()->user()->first_name ?: auth()->user()->name;
        $done = $tasks->where('count', 0)->count();
        $pending = $tasks->where('count', '>', 0);

        $icons = [
            'feed'      => 'M4 7h16M6 7l1.5 11a2 2 0 0 0 2 1.7h5a2 2 0 0 0 2-1.7L18 7M9 7V5a3 3 0 0 1 6 0v2',
            'dose'      => 'M10.5 20.5 3.5 13.5a4.95 4.95 0 0 1 7-7l7 7a4.95 4.95 0 0 1-7 7ZM8.5 8.5l7 7',
            'weight'    => 'M3 6h18M7 6l-3 8a3 3 0 0 0 6 0L7 6Zm10 0-3 8a3 3 0 0 0 6 0l-3-8ZM12 3v18',
            'case'      => 'M12 8v8m4-4H8m13 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
            'mating'    => 'M4.3 6.3a4.5 4.5 0 0 0 0 6.4L12 20.4l7.7-7.7a4.5 4.5 0 0 0-6.4-6.4L12 7.6l-1.3-1.3a4.5 4.5 0 0 0-6.4 0Z',
            'pig'       => 'M12 5v14m7-7H5',
            'import'    => 'M12 3v12m0 0 4-4m-4 4-4-4M4 21h16',
            'purchase'  => 'M3 3h2l.4 2M7 13h10l4-8H5.4M7 13 5.4 5M7 13l-2.3 2.3c-.6.6-.2 1.7.7 1.7H17m0 0a2 2 0 1 0 0 4 2 2 0 0 0 0-4Zm-8 2a2 2 0 1 1-4 0 2 2 0 0 1 4 0Z',
            'clipboard' => 'M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2M9 5a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2M9 5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2m-6 9 2 2 4-4',
            'clock'     => 'M12 8v4l3 3m6-3a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
            'alert'     => 'M12 9v3m0 4h.01M4.93 19h14.14c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.2 16c-.77 1.33.19 3 1.73 3Z',
            'check'     => 'm5 13 4 4L19 7',
        ];

        // The same gradients as the farm manager dashboard's KPI cards.
        $kpi = [
            'emerald' => ['bg' => 'from-green-500 to-green-600', 'icon' => 'clipboard'],
            'sky'     => ['bg' => 'from-blue-500 to-blue-600', 'icon' => 'weight'],
            'amber'   => ['bg' => 'from-amber-500 to-amber-600', 'icon' => 'clock'],
            'rose'    => ['bg' => 'from-red-500 to-red-600', 'icon' => 'alert'],
            'violet'  => ['bg' => 'from-purple-500 to-violet-600', 'icon' => 'purchase'],
        ];

        // One colour per kind of work, used for its icon, accent and dot.
        $taskStyle = [
            'feed'      => ['icon' => 'feed', 'chip' => 'bg-amber-100 text-amber-700', 'bar' => 'bg-amber-500', 'btn' => 'bg-amber-600 hover:bg-amber-700'],
            'health'    => ['icon' => 'dose', 'chip' => 'bg-rose-100 text-rose-700', 'bar' => 'bg-rose-500', 'btn' => 'bg-rose-600 hover:bg-rose-700'],
            'weights'   => ['icon' => 'weight', 'chip' => 'bg-blue-100 text-blue-700', 'bar' => 'bg-blue-500', 'btn' => 'bg-blue-600 hover:bg-blue-700'],
            'pregnancy' => ['icon' => 'mating', 'chip' => 'bg-purple-100 text-purple-700', 'bar' => 'bg-purple-500', 'btn' => 'bg-purple-600 hover:bg-purple-700'],
            'purchases' => ['icon' => 'purchase', 'chip' => 'bg-emerald-100 text-emerald-700', 'bar' => 'bg-emerald-500', 'btn' => 'bg-emerald-700 hover:bg-emerald-800'],
        ];
        $quickStyle = [
            'feed' => 'bg-amber-500', 'dose' => 'bg-rose-500', 'weight' => 'bg-blue-500', 'case' => 'bg-red-500',
            'mating' => 'bg-purple-500', 'pig' => 'bg-green-600', 'import' => 'bg-sky-600', 'purchase' => 'bg-violet-600',
        ];
        $entryDot = ['Weight' => 'bg-blue-500', 'Feeding' => 'bg-amber-500', 'Dose' => 'bg-rose-500', 'New pig' => 'bg-green-500', 'Mating' => 'bg-purple-500'];
    @endphp

    {{-- Data entry dashboard: the farm manager dashboard's look, today's work first. --}}
    <div class="-mx-4 min-h-screen bg-[#f3f4f6] px-4 sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8">
        <div class="mx-auto max-w-[1500px] pb-14 pt-2">

            {{-- Header --}}
            <header class="mb-5 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="text-[1.85rem] font-black leading-none text-slate-900">Data Entry Dashboard</h1>
                    <p class="mt-2 text-sm text-slate-500">{{ $greeting }}, {{ $name }}. Record today's work, then clear missing details.</p>
                </div>
                <div class="flex flex-wrap items-center gap-2.5">
                    <span class="rounded-full border border-slate-200 bg-white px-3.5 py-2.5 text-xs font-bold text-slate-600 shadow-sm">{{ now()->format('D, M j, Y') }}</span>
                    <span class="rounded-full border border-slate-200 bg-white px-3.5 py-2.5 text-xs font-bold text-slate-600 shadow-sm">{{ number_format($entriesToday) }} {{ str('entry')->plural($entriesToday) }} today</span>
                    @if($quickEntry->firstWhere('icon', 'feed'))
                        <a href="{{ $quickEntry->firstWhere('icon', 'feed')['route'] }}" class="rounded-full bg-amber-600 px-3.5 py-2.5 text-xs font-bold text-white shadow-sm hover:bg-amber-700">+ Record feeding</a>
                    @endif
                    @if($quickEntry->firstWhere('icon', 'weight'))
                        <a href="{{ $quickEntry->firstWhere('icon', 'weight')['route'] }}" class="rounded-full bg-green-700 px-3.5 py-2.5 text-xs font-bold text-white shadow-sm hover:bg-green-800">+ Record weight</a>
                    @endif
                </div>
            </header>

            {{-- Stat cards --}}
            @if($stats->isNotEmpty())
                <section class="mb-4 grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-5" aria-label="Today's figures">
                    @foreach($stats as $card)
                        @php $style = $kpi[$card['tone']]; @endphp
                        <a href="{{ $card['url'] }}" class="group relative overflow-hidden rounded-[22px] bg-gradient-to-br {{ $style['bg'] }} p-[18px] text-white shadow-[0_10px_24px_rgba(15,23,42,.10)] transition hover:-translate-y-0.5 hover:shadow-[0_14px_30px_rgba(15,23,42,.16)] focus:outline-none focus-visible:ring-4 focus-visible:ring-slate-400">
                            <span class="pointer-events-none absolute -bottom-8 -right-8 h-28 w-28 rounded-full bg-white/10" aria-hidden="true"></span>
                            <div class="relative flex items-start justify-between gap-2">
                                <span class="text-[.78rem] font-bold text-white/90">{{ $card['label'] }}</span>
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-white/20">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icons[$style['icon']] }}"/></svg>
                                </span>
                            </div>
                            <p class="relative mt-1 text-[1.75rem] font-black leading-tight tabular-nums">{{ number_format($card['value']) }}</p>
                            <p class="relative mt-2 text-[.74rem] text-white/85">{{ $card['sub'] }}</p>
                        </a>
                    @endforeach
                </section>
            @endif

            {{-- Needs attention: today's jobs not done yet, as on the manager dashboard --}}
            @if($pending->isNotEmpty())
                <section class="mb-4 overflow-hidden rounded-[18px] border border-red-200 bg-[#fff7f7]" aria-labelledby="attention-title">
                    <div class="flex items-center justify-between gap-3 border-b border-red-100 px-[18px] py-3.5">
                        <div>
                            <h2 id="attention-title" class="text-[.9rem] font-black text-red-800">Still to do today</h2>
                            <p class="mt-0.5 text-xs text-slate-500">Jobs waiting for an entry</p>
                        </div>
                        <span class="rounded-full bg-red-100 px-2.5 py-1 text-xs font-black text-red-700">{{ $pending->count() }} {{ str('job')->plural($pending->count()) }}</span>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 {{ [1 => 'lg:grid-cols-1', 2 => 'lg:grid-cols-2', 3 => 'lg:grid-cols-3', 4 => 'lg:grid-cols-4', 5 => 'lg:grid-cols-5'][min(5, max(1, $pending->count()))] }}">
                        @foreach($pending as $task)
                            <a href="{{ $task['route'] }}" class="flex items-center justify-between gap-3 border-b border-red-100 px-4 py-3.5 transition hover:bg-red-50 sm:border-r last:border-r-0">
                                <span class="text-[.78rem] font-extrabold text-slate-700">{{ $task['title'] }}</span>
                                <span class="inline-flex h-7 min-w-[28px] items-center justify-center rounded-full bg-red-600 px-2 text-xs font-black text-white">{{ $task['count'] }}</span>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif

            {{-- Today's work + quick entry --}}
            <div class="mb-4 grid gap-4 lg:grid-cols-[minmax(0,2fr)_minmax(300px,.95fr)]">
                <section class="overflow-hidden rounded-[20px] border border-slate-200 bg-white shadow-[0_4px_18px_rgba(15,23,42,.05)]" aria-labelledby="today-title">
                    <div class="flex items-center justify-between gap-3 px-[22px] pb-3 pt-5">
                        <div>
                            <h2 id="today-title" class="text-base font-extrabold text-slate-800">Today's work</h2>
                            <p class="mt-1 text-[.8rem] text-slate-500">Each job, and whether it is done</p>
                        </div>
                        @if($tasks->isNotEmpty())
                            <div class="flex items-center gap-3">
                                <span class="text-sm font-bold text-slate-600">{{ $done }} of {{ $tasks->count() }} done</span>
                                <div class="hidden h-2.5 w-32 overflow-hidden rounded-full bg-slate-100 sm:block" aria-hidden="true">
                                    <div class="h-full rounded-full bg-gradient-to-r from-green-500 to-emerald-600" style="width: {{ round($done / max(1, $tasks->count()) * 100) }}%"></div>
                                </div>
                            </div>
                        @endif
                    </div>
                    <ul class="px-[22px] pb-4">
                        @forelse($tasks as $task)
                            @php $isDone = $task['count'] === 0; $style = $taskStyle[$task['key']]; @endphp
                            <li class="relative flex flex-col gap-3 border-t border-slate-100 py-4 pl-4 sm:flex-row sm:items-center sm:gap-4">
                                <span class="absolute inset-y-4 left-0 w-1 rounded-full {{ $isDone ? 'bg-green-500' : $style['bar'] }}" aria-hidden="true"></span>
                                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl {{ $isDone ? 'bg-green-100 text-green-700' : $style['chip'] }}" aria-hidden="true">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icons[$isDone ? 'check' : $style['icon']] }}"/></svg>
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="flex flex-wrap items-center gap-2 font-extrabold text-slate-900">
                                        {{ $task['title'] }}
                                        @if($isDone)
                                            <span class="rounded-full bg-green-100 px-2 py-0.5 text-[11px] font-bold text-green-700">Done</span>
                                        @else
                                            <span class="rounded-full {{ $style['chip'] }} px-2 py-0.5 text-[11px] font-bold">{{ $task['count'] }} to do</span>
                                        @endif
                                    </p>
                                    <p class="mt-0.5 text-sm {{ $isDone ? 'text-slate-500' : 'text-slate-700' }}">{{ $isDone ? $task['done_detail'] : $task['detail'] }}</p>
                                    @if($task['progress'])<p class="mt-0.5 text-xs text-slate-400">{{ $task['progress'] }}</p>@endif
                                </div>
                                <a href="{{ $task['route'] }}" @class([
                                    'shrink-0 rounded-xl px-4 py-2 text-center text-sm font-bold transition',
                                    'text-white shadow-sm '.$style['btn'] => ! $isDone,
                                    'border border-slate-200 text-slate-600 hover:bg-slate-50' => $isDone,
                                ])>{{ $task['action'] }}</a>
                            </li>
                        @empty
                            <li class="border-t border-slate-100 py-8 text-center text-sm text-slate-500">Nothing to do today.</li>
                        @endforelse
                    </ul>
                </section>

                @if($quickEntry->isNotEmpty())
                    <section class="overflow-hidden rounded-[20px] border border-slate-200 bg-white shadow-[0_4px_18px_rgba(15,23,42,.05)]" aria-labelledby="quick-title">
                        <div class="px-[22px] pb-3 pt-5">
                            <h2 id="quick-title" class="text-base font-extrabold text-slate-800">Quick entry</h2>
                            <p class="mt-1 text-[.8rem] text-slate-500">Go straight to a form</p>
                        </div>
                        <div class="grid grid-cols-2 gap-2.5 px-[22px] pb-5">
                            @foreach($quickEntry as $item)
                                <a href="{{ $item['route'] }}" class="group flex flex-col items-start gap-2 rounded-2xl border border-slate-100 bg-slate-50 p-3.5 transition hover:-translate-y-0.5 hover:border-slate-200 hover:bg-white hover:shadow-md">
                                    <span class="flex h-10 w-10 items-center justify-center rounded-xl {{ $quickStyle[$item['icon']] }} text-white shadow-sm">
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icons[$item['icon']] }}"/></svg>
                                    </span>
                                    <span class="text-sm font-bold text-slate-800">{{ $item['label'] }}</span>
                                </a>
                            @endforeach
                        </div>
                    </section>
                @endif
            </div>

            {{-- Missing details + latest entries --}}
            @if($completeness === null && $entries->isEmpty() && $stats->isEmpty() && $tasks->isEmpty() && $quickEntry->isEmpty())
                <section class="rounded-[20px] border border-slate-200 bg-white px-[22px] py-10 text-center shadow-[0_4px_18px_rgba(15,23,42,.05)]">
                    <h2 class="text-base font-extrabold text-slate-800">Nothing is assigned to your role yet</h2>
                    <p class="mt-1 text-sm text-slate-500">Ask your farm admin to give your role access in Settings → Roles &amp; access.</p>
                </section>
            @endif
            <div class="grid gap-4 lg:grid-cols-2">
                @if($completeness !== null)
                <section class="overflow-hidden rounded-[20px] border border-slate-200 bg-white shadow-[0_4px_18px_rgba(15,23,42,.05)]" aria-labelledby="missing-title">
                    <div class="flex items-center justify-between gap-4 px-[22px] pb-3 pt-5">
                        <div>
                            <h2 id="missing-title" class="text-base font-extrabold text-slate-800">Fix missing details</h2>
                            <p class="mt-1 text-[.8rem] text-slate-500">Pig records that are not complete yet</p>
                        </div>
                        <div class="text-right">
                            <span class="block text-xl font-black {{ $completeness >= 90 ? 'text-green-600' : ($completeness >= 70 ? 'text-amber-600' : 'text-red-600') }}">{{ $completeness }}%</span>
                            <span class="text-xs text-slate-500">complete</span>
                        </div>
                    </div>
                    <div class="mx-[22px] mb-2 h-2 overflow-hidden rounded-full bg-slate-100" aria-hidden="true">
                        <div class="h-full rounded-full {{ $completeness >= 90 ? 'bg-green-500' : ($completeness >= 70 ? 'bg-amber-500' : 'bg-red-500') }}" style="width: {{ $completeness }}%"></div>
                    </div>
                    <ul class="px-[22px] pb-4">
                        @forelse($missing as $row)
                            <li class="flex items-center justify-between gap-4 border-t border-slate-100 py-3">
                                <div class="flex min-w-0 items-center gap-3">
                                    <span class="h-2.5 w-2.5 shrink-0 rounded-full bg-amber-500" aria-hidden="true"></span>
                                    <div class="min-w-0">
                                        <p class="font-bold text-slate-900">{{ $row['label'] }}</p>
                                        <p class="text-xs text-slate-500">Fix in: {{ $row['where'] }}</p>
                                    </div>
                                </div>
                                <div class="flex shrink-0 items-center gap-3">
                                    <span class="rounded-full bg-amber-100 px-2.5 py-0.5 text-sm font-black tabular-nums text-amber-800">{{ number_format($row['count']) }}</span>
                                    @if($row['route'])
                                        <a href="{{ $row['route'] }}" class="rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-bold text-white hover:bg-slate-800">Fix</a>
                                    @endif
                                </div>
                            </li>
                        @empty
                            <li class="border-t border-slate-100 py-8 text-center text-sm text-slate-500">Every pig record is complete.</li>
                        @endforelse
                    </ul>
                </section>
                @endif

                @if($entries->isNotEmpty() || $completeness !== null)
                <section class="overflow-hidden rounded-[20px] border border-slate-200 bg-white shadow-[0_4px_18px_rgba(15,23,42,.05)]" aria-labelledby="latest-title">
                    <div class="px-[22px] pb-3 pt-5">
                        <h2 id="latest-title" class="text-base font-extrabold text-slate-800">Latest entries</h2>
                        <p class="mt-1 text-[.8rem] text-slate-500">The most recent records saved on the farm</p>
                    </div>
                    <ul class="px-[22px] pb-4">
                        @forelse($entries as $entry)
                            <li class="flex items-start gap-3 border-t border-slate-100 py-2.5">
                                <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full {{ $entryDot[$entry['type']] ?? 'bg-slate-400' }}" aria-hidden="true"></span>
                                <div class="min-w-0 flex-1">
                                    @if($entry['route'])
                                        <a href="{{ $entry['route'] }}" class="block truncate text-sm font-bold text-slate-900 hover:text-green-700">{{ $entry['what'] }}</a>
                                    @else
                                        <span class="block truncate text-sm font-bold text-slate-900">{{ $entry['what'] }}</span>
                                    @endif
                                    <span class="text-xs text-slate-500">{{ $entry['type'] }}</span>
                                </div>
                                <span class="shrink-0 text-xs tabular-nums text-slate-400">{{ $entry['time']->isToday() ? $entry['time']->format('H:i') : ($entry['time']->isYesterday() ? 'Yesterday '.$entry['time']->format('H:i') : $entry['time']->format('j M H:i')) }}</span>
                            </li>
                        @empty
                            <li class="border-t border-slate-100 py-8 text-center text-sm text-slate-500">Nothing has been entered yet.</li>
                        @endforelse
                    </ul>
                </section>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
