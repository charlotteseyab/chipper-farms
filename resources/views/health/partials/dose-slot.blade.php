{{-- One course medicine at one due time: who is due, who has had it, and the actions. --}}
@php
    $line = $slot['line'];
    $open = $slot['doses']->where('status', 'scheduled');
    $late = $open->isNotEmpty() && $slot['due_at']->lt(now()->subHours((int) config('health.reminders.overdue_after_hours', 2)));
    $style = ['scheduled' => 'bg-sky-100 text-sky-800', 'administered' => 'bg-emerald-100 text-emerald-800', 'missed' => 'bg-amber-100 text-amber-800', 'cancelled' => 'bg-slate-100 text-slate-500'];
@endphp
<article class="rounded-2xl border bg-white p-4 shadow-sm {{ $late ? 'border-amber-300' : 'border-slate-100' }}">
    <div class="flex flex-wrap items-start justify-between gap-2">
        <div class="min-w-0">
            <p class="font-mono text-sm {{ $late ? 'font-bold text-amber-800' : 'text-slate-500' }}">{{ $slot['due_at']->format($showDate ?? false ? 'D j M H:i' : 'H:i') }}@if($late) · overdue @endif</p>
            <h3 class="font-bold text-slate-900">{{ $line->medicine?->display_name }}</h3>
            <p class="text-sm text-slate-600">
                {{ rtrim(rtrim((string) $line->dose_amount, '0'), '.') }} {{ $line->dose_unit }} each{{ $line->route ? ' · '.$line->route : '' }} ·
                <a class="underline" href="{{ route('treatment-courses.show', $line->treatment_course_id) }}">course #{{ $line->treatment_course_id }}</a>
                @if($line->course?->responsibleUser) · {{ $line->course->responsibleUser->name }}@endif
            </p>
        </div>
        @if($open->isNotEmpty())
            @can('create', \App\Models\MedicationDosingEvent::class)
                <a href="{{ route('health.create-medication', ['scheduled' => $open->modelKeys()]) }}" class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white">Give to {{ $open->count() }}</a>
            @endcan
        @endif
    </div>
    <ul class="mt-3 divide-y divide-slate-100">
        @foreach($slot['doses'] as $dose)
            <li class="flex flex-wrap items-center justify-between gap-2 py-2 text-sm">
                <span><a class="font-semibold text-slate-900 hover:underline" href="{{ route('health.show', $dose->pig_id) }}">{{ $dose->pig?->code }}</a>
                    <span class="text-xs text-slate-500">{{ $dose->pig?->identityAt() !== $dose->pig?->code ? $dose->pig?->identityAt() : '' }}</span></span>
                <span class="flex items-center gap-2">
                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $style[$dose->status] ?? '' }}">{{ ucfirst($dose->status) }}</span>
                    @if($dose->status_reason)<span class="text-xs text-slate-500">{{ $dose->status_reason }}</span>@endif
                    @can('close', $dose)
                        <details>
                            <summary class="cursor-pointer text-xs font-semibold text-slate-600">Not given…</summary>
                            <form method="POST" action="{{ route('scheduled-doses.close', $dose) }}" class="mt-2 w-64 max-w-full text-left space-y-2 rounded-xl border border-slate-200 bg-slate-50 p-3">
                                @csrf
                                <select name="status" class="block w-full rounded-lg border-slate-300 text-sm"><option value="missed">Missed</option><option value="cancelled">Cancelled</option></select>
                                <input name="reason" required minlength="3" placeholder="Why" class="block w-full rounded-lg border-slate-300 text-sm">
                                <button class="w-full rounded-lg bg-slate-800 px-3 py-1.5 text-xs font-semibold text-white">Save (no stock taken)</button>
                            </form>
                        </details>
                    @endcan
                </span>
            </li>
        @endforeach
    </ul>
</article>
