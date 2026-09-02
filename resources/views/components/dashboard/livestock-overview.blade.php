@php
    $stageMeta = [
        'piglet' => ['label' => 'Piglets', 'color' => 'bg-amber-400', 'tone' => 'text-amber-700'],
        'weaner' => ['label' => 'Weaners', 'color' => 'bg-sky-500', 'tone' => 'text-sky-700'],
        'grower' => ['label' => 'Growers', 'color' => 'bg-violet-500', 'tone' => 'text-violet-700'],
        'finisher' => ['label' => 'Finishers', 'color' => 'bg-emerald-600', 'tone' => 'text-emerald-700'],
        'unclassified' => ['label' => 'Unclassified', 'color' => 'bg-slate-400', 'tone' => 'text-slate-600'],
    ];
    $stageTotal = array_sum($metrics['stages']);
@endphp

<section class="mb-6 grid gap-4 xl:grid-cols-[minmax(260px,.8fr)_minmax(0,2.2fr)]" aria-label="Mortality and growth stage overview">
    <article class="rounded-3xl border border-rose-100 bg-white p-5 shadow-sm sm:p-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <p class="text-xs font-extrabold uppercase tracking-[.15em] text-rose-600">Health outcome</p>
                <h2 class="mt-2 text-lg font-extrabold text-slate-950">Mortality</h2>
            </div>
            <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-rose-50 text-rose-600"><i class="fa-solid fa-heart-pulse"></i></span>
        </div>
        <div class="mt-5 flex items-end justify-between gap-5">
            <div><p class="text-3xl font-black text-slate-950">{{ number_format($metrics['mortality_rate_30_days'], 2) }}%</p><p class="mt-1 text-xs font-semibold text-slate-500">30-day mortality rate</p></div>
            <div class="text-right"><p class="text-2xl font-black text-rose-600">{{ number_format($metrics['deaths_30_days']) }}</p><p class="mt-1 text-xs font-semibold text-slate-500">Deaths in 30 days</p></div>
        </div>
        <div class="mt-5 border-t border-slate-100 pt-4 text-sm text-slate-600">
            <span class="font-bold text-slate-900">{{ number_format($metrics['deaths_all_time']) }}</span> total recorded deaths. Rate = recent deaths ÷ current population plus recent deaths.
        </div>
    </article>

    <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
            <div><p class="text-xs font-extrabold uppercase tracking-[.15em] text-emerald-700">Current herd profile</p><h2 class="mt-2 text-lg font-extrabold text-slate-950">Animals by growth stage</h2></div>
            <div class="sm:text-right"><p class="text-2xl font-black text-slate-950">{{ number_format($metrics['active_animals']) }}</p><p class="text-xs font-semibold text-slate-500">Living animals tracked</p></div>
        </div>

        <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
            @foreach($stageMeta as $key => $meta)
                @if($key !== 'unclassified' || $metrics['stages'][$key] > 0)
                    <div class="min-w-0 rounded-2xl bg-slate-50 px-4 py-3">
                        <div class="flex items-center gap-2"><span class="h-2.5 w-2.5 rounded-full {{ $meta['color'] }}"></span><p class="truncate text-xs font-bold text-slate-600">{{ $meta['label'] }}</p></div>
                        <p class="mt-2 text-2xl font-black {{ $meta['tone'] }}">{{ number_format($metrics['stages'][$key]) }}</p>
                        <p class="mt-1 text-[11px] text-slate-500">{{ $stageTotal > 0 ? number_format(($metrics['stages'][$key] / $stageTotal) * 100, 1) : '0.0' }}% of classified total</p>
                    </div>
                @endif
            @endforeach
        </div>

        @if(!$metrics['stages_configured'])
            <p class="mt-4 rounded-xl bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-800">
                <i class="fa-solid fa-triangle-exclamation mr-1"></i>
                @if(auth()->user()?->isSuperuser())
                    Growth-stage settings are configured for {{ $metrics['configured_organizations'] }} of {{ $metrics['organization_count'] }} farms. Animals from unconfigured farms are shown as unclassified.
                @else
                    Configure the farm’s growth-stage age limits in Breeding Setup to classify its animals automatically.
                @endif
            </p>
        @endif
    </article>
</section>
