@props([
    'title' => '',
    'value' => '',
    'subtitle' => '',
    'iconBg' => 'bg-slate-50',
    'iconColor' => 'text-slate-600',
])

<div {{ $attributes->class('rounded-2xl border border-slate-200 bg-white p-6 shadow-sm') }}>
    <div class="flex items-center justify-between">
        <div>
            <p class="text-sm font-semibold text-slate-500">{{ $title }}</p>
            <p class="mt-1 text-3xl font-bold text-slate-900">{{ $value }}</p>
            @if($subtitle)
                <p class="text-xs text-slate-500">{{ $subtitle }}</p>
            @endif
        </div>
        <span class="rounded-full p-3 {{ $iconBg }} {{ $iconColor }}">
            {{ $slot }}
        </span>
    </div>
</div>

