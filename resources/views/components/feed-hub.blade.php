@props(['hub'])

@php
    $tabs = \App\Support\FeedHubs::tabsFor($hub, auth()->user());
@endphp

{{-- The page this screen belongs to, and its sibling tabs. --}}
<nav aria-label="{{ \App\Support\FeedHubs::title($hub) }}" class="mx-auto mb-4 max-w-7xl px-4 pt-5">
    <p class="text-xs font-bold uppercase tracking-widest text-emerald-700">{{ \App\Support\FeedHubs::title($hub) }}</p>
    <div class="mt-2 flex gap-1 overflow-x-auto border-b border-slate-200">
        @foreach($tabs as $tab)
            <a href="{{ route($tab['route']) }}"
               @if($tab['current']) aria-current="page" @endif
               class="-mb-px shrink-0 whitespace-nowrap border-b-2 px-4 py-2.5 text-sm font-semibold transition
                      {{ $tab['current'] ? 'border-emerald-600 text-emerald-800' : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-800' }}">
                {{ $tab['label'] }}
            </a>
        @endforeach
    </div>
    @php $current = collect($tabs)->firstWhere('current', true); @endphp
    @if($current && ! empty($current['hint']))<p class="mt-2 text-xs text-slate-500">{{ $current['hint'] }}</p>@endif
</nav>
