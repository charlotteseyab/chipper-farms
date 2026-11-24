@php
    $t = $health['tasks'];
    $c = $health['cases'];
    $w = $health['withdrawal'];
    $p = $health['prevention'];
    $m = $health['medicine'];
    $days = $health['days'];
    $typeTone = [
        'vaccination' => 'bg-sky-50 text-sky-800', 'deworming' => 'bg-violet-50 text-violet-800', 'treatment' => 'bg-emerald-50 text-emerald-800',
        'follow_up' => 'bg-slate-100 text-slate-700', 'withdrawal' => 'bg-amber-50 text-amber-800',
    ];
    $tile = fn (int $n, string $alert) => $n > 0 ? $alert : 'text-slate-400';
@endphp

<section class="mb-6" aria-labelledby="dashboard-health-heading">
    <div class="mb-3 flex flex-wrap items-end justify-between gap-2">
        <div>
            <p class="text-xs font-extrabold uppercase tracking-[.15em] text-emerald-700">Herd health</p>
            <h2 id="dashboard-health-heading" class="mt-1 text-lg font-extrabold text-slate-950">Health work, sick animals and sale restrictions</h2>
        </div>
        <div class="flex flex-wrap gap-3 text-sm font-semibold">
            <a href="{{ route('health.alerts') }}" class="text-emerald-700 hover:underline">Health alerts</a>
            <a href="{{ route('health.worklist') }}" class="text-emerald-700 hover:underline">Worklist</a>
            <a href="{{ route('health-programmes.index') }}" class="text-emerald-700 hover:underline">Programme</a>
        </div>
    </div>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        {{-- 1. Health work due --}}
        <article class="rounded-3xl border {{ $t['overdue'] ? 'border-red-200' : 'border-slate-200' }} bg-white p-5 shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <div><p class="text-xs font-extrabold uppercase tracking-[.15em] text-slate-500">Health work</p><h3 class="mt-1 font-extrabold text-slate-950">Doses and follow-ups</h3></div>
                <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-700"><i class="fa-solid fa-syringe" aria-hidden="true"></i></span>
            </div>
            <div class="mt-4 grid grid-cols-3 gap-2 text-center">
                <a href="{{ route('health.alerts', ['state' => 'overdue']) }}" class="rounded-2xl bg-slate-50 px-2 py-3 hover:bg-red-50">
                    <p class="text-2xl font-black {{ $tile($t['overdue'], 'text-red-700') }}">{{ $t['overdue'] }}</p><p class="text-[11px] font-bold text-slate-600">Overdue</p>
                </a>
                <a href="{{ route('health.alerts', ['state' => 'today']) }}" class="rounded-2xl bg-slate-50 px-2 py-3 hover:bg-amber-50">
                    <p class="text-2xl font-black {{ $tile($t['today'], 'text-amber-700') }}">{{ $t['today'] }}</p><p class="text-[11px] font-bold text-slate-600">Due today</p>
                </a>
                <a href="{{ route('health.alerts', ['state' => 'upcoming']) }}" class="rounded-2xl bg-slate-50 px-2 py-3 hover:bg-slate-100">
                    <p class="text-2xl font-black {{ $tile($t['upcoming'], 'text-slate-900') }}">{{ $t['upcoming'] }}</p><p class="text-[11px] font-bold text-slate-600">Next {{ $days }} days</p>
                </a>
            </div>
            <dl class="mt-4 space-y-1 text-sm">
                @if($t['overdue'])<div class="flex gap-2"><dt class="font-bold text-red-700">Overdue:</dt><dd class="text-slate-600">{{ $t['overdue_detail'] }}</dd></div>@endif
                @if($t['today'])<div class="flex gap-2"><dt class="font-bold text-amber-700">Today:</dt><dd class="text-slate-600">{{ $t['today_detail'] }}</dd></div>@endif
                @if($t['upcoming'])<div class="flex gap-2"><dt class="font-bold text-slate-700">Coming up:</dt><dd class="text-slate-600">{{ $t['upcoming_detail'] }}</dd></div>@endif
                @if(! $t['overdue'] && ! $t['today'] && ! $t['upcoming'])<p class="text-slate-500">Nothing due in the next {{ $days }} days.</p>@endif
            </dl>
        </article>

        {{-- 2. What to do now --}}
        <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <div><p class="text-xs font-extrabold uppercase tracking-[.15em] text-slate-500">Do now</p><h3 class="mt-1 font-extrabold text-slate-950">Overdue and due today</h3></div>
                <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-amber-50 text-amber-700"><i class="fa-solid fa-list-check" aria-hidden="true"></i></span>
            </div>
            <ul class="mt-4 space-y-2">
                @forelse($health['next'] as $alert)
                    <li>
                        <a href="{{ $alert['url'] ?? route('health.alerts') }}" class="flex items-start justify-between gap-2 rounded-xl px-2 py-1.5 hover:bg-slate-50">
                            <span class="min-w-0">
                                <span class="mr-1 rounded-full px-2 py-0.5 text-[11px] font-bold {{ $typeTone[$alert['type']] ?? $typeTone['follow_up'] }}">{{ \App\Services\Health\HealthAlertService::TYPES[$alert['type']] }}</span>
                                <span class="text-sm font-semibold text-slate-800">{{ \Illuminate\Support\Str::after($alert['title'], ': ') ?: $alert['title'] }}</span>
                                @if($alert['animals'])<span class="block text-xs text-slate-500">{{ $alert['animals'] }} {{ \Illuminate\Support\Str::plural('animal', $alert['animals']) }}</span>@endif
                            </span>
                            <span class="whitespace-nowrap text-xs font-bold {{ $alert['state'] === 'overdue' ? 'text-red-700' : 'text-amber-700' }}">{{ $alert['state'] === 'overdue' ? $alert['due']->format('j M') : 'Today' }}</span>
                        </a>
                    </li>
                @empty
                    <li class="rounded-xl bg-emerald-50 px-3 py-3 text-sm font-semibold text-emerald-800">Nothing overdue or due today.</li>
                @endforelse
            </ul>
            @if($t['overdue'] + $t['today'] > $health['next']->count())
                <a href="{{ route('health.alerts') }}" class="mt-2 inline-block text-sm font-semibold text-emerald-700 hover:underline">See all {{ $t['overdue'] + $t['today'] }} →</a>
            @endif
        </article>

        {{-- 3. Sick animals --}}
        <article class="rounded-3xl border {{ $c['severe_animals'] ? 'border-red-200' : 'border-slate-200' }} bg-white p-5 shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <div><p class="text-xs font-extrabold uppercase tracking-[.15em] text-slate-500">Sick animals</p><h3 class="mt-1 font-extrabold text-slate-950">Open health cases</h3></div>
                <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-rose-50 text-rose-600"><i class="fa-solid fa-stethoscope" aria-hidden="true"></i></span>
            </div>
            <div class="mt-4 flex items-end justify-between gap-4">
                <div><p class="text-3xl font-black {{ $c['sick_animals'] ? 'text-slate-950' : 'text-slate-400' }}">{{ $c['sick_animals'] }}</p><p class="text-xs font-semibold text-slate-500">animals in {{ $c['open_cases'] }} open {{ \Illuminate\Support\Str::plural('case', $c['open_cases']) }}</p></div>
                <div class="text-right"><p class="text-2xl font-black {{ $tile($c['severe_animals'], 'text-red-700') }}">{{ $c['severe_animals'] }}</p><p class="text-xs font-semibold text-slate-500">severe</p></div>
            </div>
            <div class="mt-4 grid grid-cols-2 gap-2 text-sm">
                <a href="{{ route('health.index') }}" class="rounded-xl bg-slate-50 px-3 py-2 hover:bg-slate-100"><span class="font-black text-slate-900">{{ $c['new_this_week'] }}</span> <span class="text-slate-600">new this week</span></a>
                <a href="{{ route('health.reports', ['tab' => 'cases', 'status' => 'active']) }}" class="rounded-xl px-3 py-2 {{ $c['need_check'] ? 'bg-amber-50 hover:bg-amber-100' : 'bg-slate-50 hover:bg-slate-100' }}"><span class="font-black {{ $c['need_check'] ? 'text-amber-800' : 'text-slate-900' }}">{{ $c['need_check'] }}</span> <span class="text-slate-600">not checked in 7 days</span></a>
            </div>
            @if($c['top_conditions'] !== [])
                <p class="mt-4 text-xs font-bold uppercase tracking-wide text-slate-500">Most seen, last {{ \App\Services\Dashboard\DashboardHealthMetrics::LOOKBACK_DAYS }} days</p>
                <div class="mt-1 flex flex-wrap gap-1.5">
                    @foreach($c['top_conditions'] as $condition => $count)
                        <span class="rounded-full bg-rose-50 px-2.5 py-1 text-xs font-semibold text-rose-800">{{ $condition }} · {{ $count }}</span>
                    @endforeach
                </div>
            @endif
        </article>

        {{-- 4. Withdrawal --}}
        <article class="rounded-3xl border {{ $w['animals'] ? 'border-amber-200' : 'border-slate-200' }} bg-white p-5 shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <div><p class="text-xs font-extrabold uppercase tracking-[.15em] text-slate-500">Sale restrictions</p><h3 class="mt-1 font-extrabold text-slate-950">Medicine withdrawal</h3></div>
                <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-amber-50 text-amber-700"><i class="fa-solid fa-ban" aria-hidden="true"></i></span>
            </div>
            <div class="mt-4 flex items-end justify-between gap-4">
                <div><p class="text-3xl font-black {{ $tile($w['animals'], 'text-amber-700') }}">{{ $w['animals'] }}</p><p class="text-xs font-semibold text-slate-500">{{ \Illuminate\Support\Str::plural('animal', $w['animals']) }} that must not be sold for food</p></div>
                <div class="text-right"><p class="text-2xl font-black {{ $tile($w['clearing_soon'], 'text-emerald-700') }}">{{ $w['clearing_soon'] }}</p><p class="text-xs font-semibold text-slate-500">clear in {{ $days }} days</p></div>
            </div>
            <p class="mt-4 text-sm text-slate-600">
                @if($w['animals'])
                    The shop and sales already refuse these animals. The last restriction ends {{ \Carbon\CarbonImmutable::parse($w['longest_until'])->format('j M Y') }}.
                @else
                    No animal is inside a withdrawal period.
                @endif
            </p>
            <a href="{{ route('health.reports', ['tab' => 'stock']) }}#withdrawal" class="mt-2 inline-block text-sm font-semibold text-emerald-700 hover:underline">Which animals →</a>
        </article>

        {{-- 5. Prevention --}}
        <article class="rounded-3xl border {{ $p['rate'] !== null && $p['rate'] < 90 ? 'border-amber-200' : 'border-slate-200' }} bg-white p-5 shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <div><p class="text-xs font-extrabold uppercase tracking-[.15em] text-slate-500">Prevention, last {{ \App\Services\Dashboard\DashboardHealthMetrics::LOOKBACK_DAYS }} days</p><h3 class="mt-1 font-extrabold text-slate-950">Vaccination and deworming</h3></div>
                <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-sky-50 text-sky-700"><i class="fa-solid fa-shield-virus" aria-hidden="true"></i></span>
            </div>
            @if($p['due'] > 0)
                <div class="mt-4 flex items-end justify-between gap-4">
                    <p class="text-3xl font-black {{ $p['rate'] >= 90 ? 'text-emerald-700' : ($p['rate'] >= 70 ? 'text-amber-700' : 'text-red-700') }}">{{ $p['rate'] }}%</p>
                    <p class="text-right text-xs font-semibold text-slate-500">{{ $p['given'] }} of {{ $p['due'] }} doses given</p>
                </div>
                <div class="mt-2 h-2.5 overflow-hidden rounded-full bg-slate-100" role="img" aria-label="{{ $p['rate'] }}% of vaccination and deworming doses given">
                    <div class="h-full rounded-full {{ $p['rate'] >= 90 ? 'bg-emerald-600' : ($p['rate'] >= 70 ? 'bg-amber-500' : 'bg-red-600') }}" style="width: {{ $p['rate'] }}%"></div>
                </div>
                <p class="mt-3 text-sm text-slate-600">
                    @if($p['unrecorded'])<span class="font-bold text-red-700">{{ $p['unrecorded'] }} not recorded yet</span>@endif
                    @if($p['unrecorded'] && $p['not_given']) · @endif
                    @if($p['not_given'])<span class="font-bold text-slate-800">{{ $p['not_given'] }} skipped or missed</span>@endif
                    @if(! $p['unrecorded'] && ! $p['not_given'])Every dose due was given.@endif
                </p>
            @else
                <p class="mt-4 text-sm text-slate-600">No vaccination or deworming doses were due. Set up the routine ones once in the herd health programme and they are scheduled for every animal automatically.</p>
            @endif
            <a href="{{ route('health-programmes.index') }}" class="mt-2 inline-block text-sm font-semibold text-emerald-700 hover:underline">Herd health programme →</a>
        </article>

        {{-- 6. Medicine stock --}}
        @php $stockProblems = $m['out'] + $m['short'] + $m['expired']; @endphp
        <article class="rounded-3xl border {{ $stockProblems ? 'border-red-200' : 'border-slate-200' }} bg-white p-5 shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <div><p class="text-xs font-extrabold uppercase tracking-[.15em] text-slate-500">Medicine stock</p><h3 class="mt-1 font-extrabold text-slate-950">Ready for the doses due?</h3></div>
                <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-violet-50 text-violet-700"><i class="fa-solid fa-pills" aria-hidden="true"></i></span>
            </div>
            <div class="mt-4 grid grid-cols-2 gap-2 text-sm">
                <div class="rounded-xl px-3 py-2 {{ $m['short'] ? 'bg-red-50' : 'bg-slate-50' }}"><span class="font-black {{ $tile($m['short'], 'text-red-700') }}">{{ $m['short'] }}</span> <span class="text-slate-600">short for doses due</span></div>
                <div class="rounded-xl px-3 py-2 {{ $m['out'] ? 'bg-red-50' : 'bg-slate-50' }}"><span class="font-black {{ $tile($m['out'], 'text-red-700') }}">{{ $m['out'] }}</span> <span class="text-slate-600">out of stock</span></div>
                <div class="rounded-xl px-3 py-2 {{ $m['low'] ? 'bg-amber-50' : 'bg-slate-50' }}"><span class="font-black {{ $tile($m['low'], 'text-amber-700') }}">{{ $m['low'] }}</span> <span class="text-slate-600">running low</span></div>
                <div class="rounded-xl px-3 py-2 {{ $m['expiring'] + $m['expired'] ? 'bg-amber-50' : 'bg-slate-50' }}"><span class="font-black {{ $tile($m['expiring'] + $m['expired'], 'text-amber-700') }}">{{ $m['expiring'] + $m['expired'] }}</span> <span class="text-slate-600">{{ $m['expired'] ? 'expired or expiring' : 'expiring in 30 days' }}</span></div>
            </div>
            <p class="mt-3 text-sm text-slate-600">
                @if($m['short'])<span class="font-bold text-red-700">Not enough {{ $m['short_names'] }} for the next {{ $days }} days.</span>
                @elseif($m['out'])<span class="font-bold text-red-700">Out: {{ $m['out_names'] }}.</span>
                @else Enough in-date stock for the doses scheduled.
                @endif
            </p>
            <a href="{{ route('medicines.index') }}" class="mt-2 inline-block text-sm font-semibold text-emerald-700 hover:underline">Medicine stock →</a>
        </article>
    </div>
</section>
