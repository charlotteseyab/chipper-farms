@props(['alerts', 'empty' => 'Nothing due.', 'showState' => false])
{{-- One row per health alert from HealthAlertService. --}}
@php
    $typeTone = [
        'vaccination' => 'bg-sky-50 text-sky-800 ring-sky-200',
        'deworming'   => 'bg-violet-50 text-violet-800 ring-violet-200',
        'treatment'   => 'bg-emerald-50 text-emerald-800 ring-emerald-200',
        'follow_up'   => 'bg-slate-100 text-slate-700 ring-slate-200',
        'withdrawal'  => 'bg-amber-50 text-amber-800 ring-amber-200',
    ];
    $stateTone = ['overdue' => 'text-red-700 font-semibold', 'today' => 'text-amber-800 font-semibold', 'upcoming' => 'text-slate-500'];
@endphp
<div {{ $attributes->merge(['class' => 'space-y-2']) }}>
    @forelse($alerts as $alert)
        @php $tag = $alert['url'] ? 'a' : 'div'; @endphp
        <{{ $tag }} @if($alert['url']) href="{{ $alert['url'] }}" @endif
            class="flex items-start justify-between gap-3 rounded-2xl border bg-white p-4 shadow-sm {{ $alert['url'] ? 'hover:bg-slate-50' : '' }} {{ $alert['state'] === 'overdue' ? 'border-red-200' : 'border-slate-100' }}">
            <span class="min-w-0">
                <span class="mb-1 inline-flex rounded-full px-2 py-0.5 text-xs font-semibold ring-1 {{ $typeTone[$alert['type']] ?? $typeTone['follow_up'] }}">{{ \App\Services\Health\HealthAlertService::TYPES[$alert['type']] }}</span>
                <span class="block font-semibold text-slate-900">{{ $alert['title'] }}</span>
                @if($alert['detail'])<span class="block text-sm text-slate-500">{{ $alert['detail'] }}</span>@endif
            </span>
            <span class="whitespace-nowrap text-right text-sm {{ $stateTone[$alert['state']] }}">
                @if($showState)<span class="block text-xs uppercase tracking-wide">{{ \App\Services\Health\HealthAlertService::STATES[$alert['state']] }}</span>@endif
                {{ $alert['due']->isToday() ? 'Today' : ($alert['due']->isTomorrow() ? 'Tomorrow' : $alert['due']->format('D j M')) }}@if($alert['type'] !== 'withdrawal' && $alert['type'] !== 'follow_up' && $alert['due']->format('H:i') !== '00:00') {{ $alert['due']->format('H:i') }}@endif
            </span>
        </{{ $tag }}>
    @empty
        <p class="rounded-2xl border border-dashed border-slate-200 p-6 text-center text-sm text-slate-500">{{ $empty }}</p>
    @endforelse
</div>
