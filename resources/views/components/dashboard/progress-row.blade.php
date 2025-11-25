@props([
    'label' => '',
    'value' => '',
    'helper' => '',
    'color' => 'bg-emerald-500',
    'progress' => 0,
])

@php
    $clamped = max(0, min((int) $progress, 100));
@endphp

<div class="space-y-1">
    <div class="flex items-center justify-between">
        <div>
            <p class="text-sm font-semibold text-slate-600">{{ $label }}</p>
            @if($helper)
                <p class="text-xs text-slate-400">{{ $helper }}</p>
            @endif
        </div>
        <span class="text-sm font-semibold text-slate-900">{{ $value }}</span>
    </div>
    <div class="h-2 w-full rounded-full bg-slate-100">
        <div class="h-2 rounded-full {{ $color }}" style="width: {{ $clamped }}%;"></div>
    </div>
</div>

