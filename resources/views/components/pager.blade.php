@props(['paginator', 'label' => 'records'])
{{-- The table's count, and Previous · page numbers · Next when there is more
     than one page. One page: just "2 doses" (no "Showing 1–2 of 2 · Page 1 of
     1", which says the same thing three times). More: "Showing 26–50 of 60
     doses", with the page shown by the buttons. Shared by the health and feed
     pages. --}}
@if($paginator->total() > 0)
    <nav role="navigation" aria-label="Pagination" {{ $attributes->merge(['class' => 'flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between']) }}>
        <p class="text-sm text-slate-600">
            @if($paginator->hasPages())
                Showing <span class="font-semibold text-slate-900 tabular-nums">{{ $paginator->firstItem() }}–{{ $paginator->lastItem() }}</span>
                of <span class="font-semibold text-slate-900 tabular-nums">{{ $paginator->total() }}</span> {{ $label }}
            @else
                <span class="font-semibold text-slate-900 tabular-nums">{{ $paginator->total() }}</span> {{ $label }}
            @endif
        </p>
        @if($paginator->hasPages())
            @php
                $current = $paginator->currentPage();
                $last = $paginator->lastPage();
                $start = max(1, $current - 2);
                $end = min($last, $current + 2);
                $btn = 'inline-flex min-w-[2.5rem] items-center justify-center rounded-lg border px-3 py-1.5 text-sm font-medium transition';
                $idle = $btn.' border-slate-200 bg-white text-slate-700 hover:border-emerald-300 hover:bg-emerald-50 hover:text-emerald-800';
                $off = $btn.' cursor-not-allowed border-slate-100 bg-slate-50 text-slate-400';
            @endphp
            <div class="flex flex-wrap items-center gap-1">
                @if($paginator->onFirstPage())
                    <span class="{{ $off }}" aria-disabled="true">‹ Previous</span>
                @else
                    <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="{{ $idle }}">‹ Previous</a>
                @endif

                @if($start > 1)
                    <a href="{{ $paginator->url(1) }}" class="{{ $idle }}">1</a>
                    @if($start > 2)<span class="px-1 text-slate-400">…</span>@endif
                @endif
                @for($page = $start; $page <= $end; $page++)
                    @if($page === $current)
                        <span class="{{ $btn }} border-emerald-700 bg-emerald-700 text-white shadow-sm" aria-current="page">{{ $page }}</span>
                    @else
                        <a href="{{ $paginator->url($page) }}" class="{{ $idle }}">{{ $page }}</a>
                    @endif
                @endfor
                @if($end < $last)
                    @if($end < $last - 1)<span class="px-1 text-slate-400">…</span>@endif
                    <a href="{{ $paginator->url($last) }}" class="{{ $idle }}">{{ $last }}</a>
                @endif

                @if($paginator->hasMorePages())
                    <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="{{ $idle }}">Next ›</a>
                @else
                    <span class="{{ $off }}" aria-disabled="true">Next ›</span>
                @endif
            </div>
        @endif
    </nav>
@endif
