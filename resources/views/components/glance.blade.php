@props(['cards' => []])
{{-- At-a-glance summary cards, shared by the health and feed pages. Each card: label, value, optional hint, url, tone (neutral, good, warn, alert) and current (a selected filter). --}}
@php
    $tones = [
        'neutral' => 'border-slate-100 bg-white',
        'good' => 'border-emerald-100 bg-emerald-50/60',
        'warn' => 'border-amber-200 bg-amber-50',
        'alert' => 'border-red-200 bg-red-50',
    ];
    $valueTones = ['neutral' => 'text-slate-900', 'good' => 'text-emerald-800', 'warn' => 'text-amber-800', 'alert' => 'text-red-700'];
@endphp
<div {{ $attributes->merge(['class' => 'grid grid-cols-2 gap-3 lg:grid-cols-4']) }}>
    @foreach($cards as $card)
        @php $tone = $card['tone'] ?? 'neutral'; $tag = ! empty($card['url']) ? 'a' : 'div'; @endphp
        <{{ $tag }} @if($tag === 'a') href="{{ $card['url'] }}" @endif
            class="min-w-0 rounded-xl border p-4 shadow-sm {{ $tones[$tone] ?? $tones['neutral'] }} {{ $tag === 'a' ? 'transition hover:shadow' : '' }} {{ ! empty($card['current']) ? 'ring-2 ring-emerald-600' : '' }}"
            @if(! empty($card['current'])) aria-current="true" @endif>
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $card['label'] }}</p>
            <p class="mt-1 truncate text-2xl font-bold tabular-nums {{ $valueTones[$tone] ?? $valueTones['neutral'] }}">{{ $card['value'] }}</p>
            @if(! empty($card['hint']))<p class="mt-0.5 truncate text-xs text-slate-500" title="{{ $card['hint'] }}">{{ $card['hint'] }}</p>@endif
        </{{ $tag }}>
    @endforeach
</div>
