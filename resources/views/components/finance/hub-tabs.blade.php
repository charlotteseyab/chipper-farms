@php
    $hub = \App\Support\Features\FinanceHubs::current(auth()->user());
    $open = $hub ? collect($hub['tabs'])->firstWhere('current', true) : null;
@endphp

@if($hub)
    {{-- The finance page this screen belongs to, and its sibling screens. --}}
    <nav aria-label="Finance: {{ $hub['title'] }}" class="mb-5">
        <p class="flex items-center gap-2 text-xs font-bold uppercase tracking-widest text-emerald-700">
            Finance · {{ $hub['title'] }}
            @if($hub['addon'])<span class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] tracking-wide text-amber-800">Add-on</span>@endif
        </p>
        @if(count($hub['tabs']) > 1 || ! empty($open['children']))
        <div class="mt-2 flex gap-1 overflow-x-auto border-b border-slate-200">
            @foreach($hub['tabs'] as $tab)
                <a href="{{ route($tab['route']) }}"
                   @if($tab['current']) aria-current="page" @endif
                   class="-mb-px shrink-0 whitespace-nowrap border-b-2 px-4 py-2.5 text-sm font-semibold transition
                          {{ $tab['current'] ? 'border-emerald-600 text-emerald-800' : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-800' }}">
                    {{ $tab['label'] }}
                </a>
            @endforeach
        </div>
        @endif
        @if($open && count($open['children']) > 1)
            <div class="mt-3 flex flex-wrap gap-2">
                @foreach($open['children'] as $child)
                    <a href="{{ route($child['route']) }}"
                       @if($child['current']) aria-current="page" @endif
                       class="rounded-full px-3 py-1 text-xs font-semibold transition
                              {{ $child['current'] ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                        {{ $child['label'] }}
                    </a>
                @endforeach
            </div>
        @endif
    </nav>
@endif
