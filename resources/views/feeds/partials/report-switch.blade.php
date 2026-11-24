{{-- The two feed reports share one Reports tab; this switches between them. --}}
<nav aria-label="Feed reports" class="inline-flex rounded-xl bg-slate-100 p-1 text-sm font-semibold">
    @foreach([['feed.planned-vs-actual', 'Planned vs actual'], ['feed-performance.index', 'Feed conversion']] as [$reportRoute, $reportLabel])
        <a href="{{ route($reportRoute) }}" @if(request()->routeIs($reportRoute)) aria-current="page" @endif
           class="rounded-lg px-3 py-1.5 {{ request()->routeIs($reportRoute) ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-800' }}">{{ $reportLabel }}</a>
    @endforeach
</nav>
