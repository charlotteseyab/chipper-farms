@props([
    'title' => '',
    'subtitle' => '',
    'route' => null,
    'link' => null,
])

<div {{ $attributes->class('rounded-3xl border border-slate-200 bg-white p-6 shadow-sm') }}>
    <div class="flex items-center justify-between">
        <div>
            <h3 class="text-lg font-semibold text-slate-900">{{ $title }}</h3>
            @if($subtitle)
                <p class="text-xs uppercase tracking-wide text-slate-500">{{ $subtitle }}</p>
            @endif
        </div>
        @if($route && $link)
            <a href="{{ $route }}" class="text-sm font-semibold text-emerald-600">
                {{ $link }}
            </a>
        @endif
    </div>
    <div class="mt-4">
        {{ $slot }}
    </div>
</div>

